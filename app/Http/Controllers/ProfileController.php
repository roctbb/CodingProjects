<?php

namespace App\Http\Controllers;

use App\CoinTransaction;
use App\CompletedCourse;
use App\Course;
use App\CourseActivity;
use App\Achievement;
use App\Http\Controllers\Controller;
use App\Rank;
use App\RoomCharacterGeneration;
use App\Services\CoinWallet;
use App\Jobs\GenerateAchievementTrophy;
use App\Solution;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Auth;


class ProfileController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('self')->except(['index', 'details', 'updateAchievement', 'deleteAchievement', 'addMoney']);
        $this->middleware('teacher')->only(['addMoney']);
        $this->middleware('admin')->only(['deleteCourse', 'course', 'deleteCurrentCourse']);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = User::with('manual_rank')->orderBy('name')->get();
        $solutionScores = DB::query()
            ->fromSub(
                Solution::query()
                    ->select('user_id', 'task_id')
                    ->selectRaw('MAX(COALESCE(mark, 0)) as mark')
                    ->groupBy('user_id', 'task_id'),
                'best_marks'
            )
            ->select('user_id')
            ->selectRaw('SUM(mark) as score')
            ->groupBy('user_id')
            ->pluck('score', 'user_id');

        $ranks = Rank::orderBy('from')->get();
        $fallbackRank = $ranks->first();

        $users->each(function ($user) use ($solutionScores, $ranks, $fallbackRank) {
            if ($user->rank_id && $user->manual_rank) {
                $user->setComputedScore($user->manual_rank->to - 1);
                $user->setComputedRank($user->manual_rank);
                return;
            }

            $score = (int) ($solutionScores[$user->id] ?? 0);
            $rank = $ranks->first(function ($rank) use ($score) {
                return $rank->from <= $score && $rank->to > $score;
            });

            $user->setComputedScore($score);
            $user->setComputedRank($rank ?: $fallbackRank);
        });

        return view('profile.index', compact('users'));
    }

    public function details($id = null)
    {
        $guest = User::findOrFail(Auth::User()->id);
        $user = null;
        if ($id == null) {
            $user = User::with([
                'manual_rank',
                'managed_courses.students',
                'managed_courses.teachers',
                'courses.program',
                'courses.students',
                'courses.teachers',
                'completedCourses.course.students',
                'completedCourses.course.teachers',
                'orders.good',
                'achievements.course.teachers',
                'achievements.task',
            ])->findOrFail($guest->id);
        } else {
            $user = User::with([
                'manual_rank',
                'managed_courses.students',
                'managed_courses.teachers',
                'courses.program',
                'courses.students',
                'courses.teachers',
                'completedCourses.course.students',
                'completedCourses.course.teachers',
                'orders.good',
                'achievements.course.teachers',
                'achievements.task',
            ])->findOrFail($id);
        }

        $canManageMoney = $this->canManageMoney($guest, $user);
        $canViewMoneyHistory = $guest->id == $user->id || $canManageMoney || $guest->role == 'admin';
        $coinTransactions = $canViewMoneyHistory
            ? $user->transactions()->latest()->take(20)->get()
            : collect();
        $coinBalance = $user->balance();
        $avatarFrames = User::avatarFrames();
        $activeAvatarFrame = $user->activeAvatarFrame();
        $customAvatarFrameCost = $user->customAvatarFrameCost();
        $customAvatarFrameDefaults = User::sanitizeCustomAvatarFrameConfig(old('avatar_frame_config', $user->avatar_frame_config ?: User::customAvatarFrameDefaults()));
        $learningAvatarData = $user->learningAvatarRenderData();
        $roomCharacterAvailable = $guest->id == $user->id && Schema::hasTable('room_character_generations');
        $roomCharacterGeneration = $roomCharacterAvailable
            ? RoomCharacterGeneration::where('user_id', $user->id)->latest('id')->first() : null;
        $achievements = $user->achievements
            ->where('status', \App\Achievement::STATUS_PUBLISHED)
            ->sortByDesc('published_at')
            ->values();

        // Use cached sticker retrieval and descriptions
        $stickers = $user->getStickers();
        $sticker_description = $user->getStickerDescriptions();

        return view('profile.details', compact('user', 'guest', 'stickers', 'sticker_description', 'coinTransactions', 'coinBalance', 'canViewMoneyHistory', 'canManageMoney', 'avatarFrames', 'activeAvatarFrame', 'customAvatarFrameCost', 'customAvatarFrameDefaults', 'learningAvatarData', 'achievements', 'roomCharacterAvailable', 'roomCharacterGeneration'));
    }

    public function updateAchievement($user_id, $achievement_id, Request $request)
    {
        $achievement = Achievement::with('course.teachers')
            ->where('user_id', $user_id)
            ->findOrFail($achievement_id);

        $manager = Auth::user();
        if (!$this->canManageAchievement($manager, $achievement)) {
            abort(403);
        }

        if ($request->has('regenerate_trophy') && (!$manager || $manager->role != 'admin')) {
            abort(403);
        }

        $request->merge([
            'title' => is_string($request->title) ? trim(strip_tags($request->title)) : $request->title,
            'description' => is_string($request->description) ? trim(strip_tags($request->description)) : $request->description,
        ]);
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'description' => 'required|string|max:1000',
            'regenerate_trophy' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($achievement, $data) {
            $achievement->title = $data['title'];
            $achievement->description = $data['description'];
            $achievement->save();
            CourseActivity::recordAchievementEarned($achievement);
            if (!empty($data['regenerate_trophy'])) {
                GenerateAchievementTrophy::dispatch($achievement->id, true)->afterCommit();
            }
        });

        $message = $request->boolean('regenerate_trophy')
            ? 'Изменения сохранены. Новый кубок появится после генерации.'
            : 'Изменения сохранены в профиле и пульсе.';
        $this->make_success_alert('Достижение обновлено', $message);

        return redirect('/insider/profile/' . $achievement->user_id . '#achievement-' . $achievement->id);
    }

    public function deleteAchievement($user_id, $achievement_id)
    {
        $user = Auth::user();
        if (!$user || $user->role != 'admin') {
            abort(403);
        }

        $achievement = Achievement::where('user_id', $user_id)->findOrFail($achievement_id);
        $profileUserId = $achievement->user_id;

        CourseActivity::where('type', CourseActivity::TYPE_AI_ACHIEVEMENT_EARNED)
            ->where('solution_id', $achievement->solution_id)
            ->where('task_id', $achievement->task_id)
            ->where('user_id', $achievement->user_id)
            ->delete();

        $achievement->delete();

        $this->make_success_alert('Достижение удалено', 'Оно больше не показывается в профиле и пульсе.');

        return redirect('/insider/profile/' . $profileUserId . '#achievements');
    }

    private function canManageAchievement($user, Achievement $achievement)
    {
        if (!$user) {
            return false;
        }

        if ($user->role == 'admin') {
            return true;
        }

        return $achievement->course && $achievement->course->teachers->contains('id', $user->id);
    }

    private function canManageMoney($manager, User $profileUser)
    {
        if (!$manager) {
            return false;
        }

        if ($manager->role == 'admin') {
            return true;
        }

        if ($manager->role != 'teacher') {
            return false;
        }

        return Course::whereHas('teachers', function ($query) use ($manager) {
                $query->where('users.id', $manager->id);
            })
            ->whereHas('students', function ($query) use ($profileUser) {
                $query->where('users.id', $profileUser->id);
            })
            ->exists();
    }

    public function editView($id)
    {
        $guest = User::findOrFail(Auth::User()->id);
        $user = User::findOrFail($id);
        return view('profile.edit', compact('user', 'guest'));
    }

    public function deleteCourse($id)
    {
        $course = CompletedCourse::findOrFail($id);
        $course->delete();
        return redirect()->back();
    }

    public function deleteCurrentCourse($user_id, $course_id)
    {
        $user = User::findOrFail($user_id);
        $user->courses()->detach($course_id);
        return redirect()->back();
    }

    public function course($id, Request $request)
    {
        $user = User::findOrFail($id);
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'mark' => 'required|string|max:255',
        ]);
        $course = new CompletedCourse();
        $course->name = $request->name;
        $course->mark = $request->mark;
        $course->user_id = $id;
        $course->save();

        $user->rescore();

        return redirect()->back();
    }

    public function addMoney($id, Request $request)
    {
        $user = User::findOrFail($id);

        if (!$this->canManageMoney(Auth::user(), $user)) {
            abort(403);
        }

        $this->validate($request, [
            'description' => 'required|string|max:255',
            'amount' => 'integer|not_in:0|min:-10000|max:10000|required'
        ]);

        $amount = (int) $request->amount;
        $description = clean($request->description);
        $isWriteOff = $amount < 0;
        $notificationText = $isWriteOff
            ? 'Списано ' . abs($amount) . ' GC: ' . $description
            : 'Начислено ' . $amount . ' GC: ' . $description;

        CoinTransaction::register(
            $user->id,
            $amount,
            $description,
            $notificationText,
            $isWriteOff ? 'warning' : 'success',
            $isWriteOff ? 'fas fa-minus-circle' : 'fas fa-coins'
        );

        $this->make_success_alert(
            'Операция сохранена',
            $isWriteOff ? 'GC списаны с баланса ученика.' : 'GC начислены ученику.'
        );

        return redirect()->back();
    }

    public function buyCustomTitle($id, Request $request)
    {
        $user = User::findOrFail($id);

        $this->validate($request, [
            'custom_title' => 'required|string|max:32',
        ]);

        $title = trim(strip_tags($request->custom_title));
        $title = preg_replace('/\s+/u', ' ', $title);

        if ($title === '') {
            $this->make_error_alert('Звание пустое', 'Введите короткое звание для профиля.');
            return redirect()->back()->withInput();
        }

        $cost = $user->customTitleCost();
        $user = app(CoinWallet::class)->transaction($user->id, function (User $lockedUser) use ($cost, $title) {
            if ($lockedUser->balance() < $cost) {
                return null;
            }
            $startsAt = $lockedUser->hasActiveCustomTitle() ? $lockedUser->custom_title_expires_at : Carbon::now();
            $lockedUser->custom_title = $title;
            $lockedUser->custom_title_expires_at = $startsAt->copy()->addDays($lockedUser->customTitleDurationDays());
            $lockedUser->save();
            CoinTransaction::register($lockedUser->id, -$cost, 'Custom title User #' . $lockedUser->id);
            return $lockedUser;
        });
        if (!$user) {
            $this->make_error_alert('Не хватает GC', 'Кастомное звание стоит ' . $cost . ' GC.');
            return redirect()->back()->withInput();
        }

        $this->make_success_alert('Звание активно', 'Звание будет показываться в профиле до ' . $user->custom_title_expires_at->format('d.m.Y') . '.');

        if ($request->input('return_to') === 'market') {
            return redirect('/insider/market#market-digital');
        }

        return redirect('/insider/profile/' . $user->id);
    }

    public function buyAvatarFrame($id, Request $request)
    {
        $user = User::findOrFail($id);

        $this->validate($request, [
            'avatar_frame' => 'required|string',
        ]);

        $frames = User::avatarFrames();
        $frameKey = $request->avatar_frame;

        if ($frameKey !== 'custom' && !array_key_exists($frameKey, $frames)) {
            $this->make_error_alert('Рамка не найдена', 'Выберите рамку из списка.');
            return redirect()->back();
        }

        $customFrameConfig = $frameKey === 'custom'
            ? User::sanitizeCustomAvatarFrameConfig($request->input('avatar_frame_config', []))
            : null;
        $frame = $frameKey === 'custom'
            ? [
                'name' => !empty($customFrameConfig['animated']) ? 'Своя живая рамка' : 'Своя статичная рамка',
                'cost' => $user->customAvatarFrameCost(!empty($customFrameConfig['animated'])),
                'days' => $user->customAvatarFrameDurationDays(),
            ]
            : $frames[$frameKey];
        $cost = (int) $frame['cost'];

        $user = app(CoinWallet::class)->transaction($user->id, function (User $lockedUser) use ($cost, $frameKey, $customFrameConfig) {
            if ($lockedUser->balance() < $cost) {
                return null;
            }
            $startsAt = $lockedUser->hasActiveAvatarFrame() && $lockedUser->avatar_frame === $frameKey
                ? $lockedUser->avatar_frame_expires_at : Carbon::now();
            $lockedUser->avatar_frame = $frameKey;
            $lockedUser->avatar_frame_config = $customFrameConfig;
            $lockedUser->avatar_frame_expires_at = $startsAt->copy()->addDays($lockedUser->avatarFrameDurationDays($frameKey));
            $lockedUser->save();
            CoinTransaction::register($lockedUser->id, -$cost, 'Avatar frame ' . $frameKey . ' User #' . $lockedUser->id);
            return $lockedUser;
        });
        if (!$user) {
            $this->make_error_alert('Не хватает GC', 'Рамка "' . $frame['name'] . '" стоит ' . $cost . ' GC.');
            return redirect()->back();
        }

        $this->make_success_alert(
            'Рамка активна',
            'Рамка "' . $frame['name'] . '" будет показываться до ' . $user->avatar_frame_expires_at->format('d.m.Y') . '.'
        );

        if ($request->input('return_to') === 'market') {
            return redirect('/insider/market#market-digital');
        }

        return redirect('/insider/profile/' . $user->id);
    }

    public function updateLearningAvatar($id, Request $request)
    {
        $user = User::findOrFail($id);
        $manifestKeys = array_keys(User::learningAvatarManifests());

        $this->validate($request, [
            'manifest' => ['nullable', 'string', Rule::in($manifestKeys)],
            'appearance' => 'nullable|array',
            'equipped' => 'nullable|array',
        ]);

        $manifestKey = $request->input('manifest', $user->learningAvatarConfig()['manifest'] ?? 'room-system');
        $requestedEquipped = $request->input('equipped', []);
        $requestedAppearance = $request->input('appearance', []);

        DB::transaction(function () use ($user, $manifestKey, $requestedEquipped, $requestedAppearance) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedUser->learning_avatar_config = $lockedUser->learningAvatarConfigFromOwnedSelection($manifestKey, $requestedEquipped, $requestedAppearance);
            $lockedUser->save();
        });

        $this->make_success_alert('Комната сохранена', 'Выбранные предметы обновлены.');

        return redirect('/insider/profile/' . $user->id . '#learning-avatar');
    }

    public function edit($id, Request $request)
    {
        $guest = User::findOrFail(Auth::User()->id);
        $user = User::findOrFail($id);
        $canEditBirthday = $guest->role == 'teacher' || $guest->role == 'admin';

        $rules = [
            'name' => 'required|string|max:255',
            'school' => 'required|string|max:255',
            'grade' => 'integer|min:1|max:12|required',
            'gender' => ['nullable', 'string', Rule::in(array_keys(User::learningAvatarGenders()))],
            'git' => 'nullable|string|max:255',
            'telegram' => 'nullable|string|max:255',
            'hobbies' => 'required|string|max:10000',
            'interests' => 'required|string|max:10000',
            'comments' => 'nullable|string|max:10000',
            'birthday_hidden' => 'nullable|boolean',
            'image' => 'image|max:10240'
        ];
        if ($canEditBirthday) {
            $rules['birthday'] = 'required|date|date_format:Y-m-d';
        }
        if ($guest->role == 'admin') {
            $rules['role'] = ['required', 'string', Rule::in(array_keys(User::roleLabels()))];
        }
        $this->validate($request, $rules);

        $user->name = $request->name;
        $user->git = $request->git;
        $user->telegram = $request->telegram;
        $user->hobbies = $request->hobbies;
        $user->interests = $request->interests;
        $user->school = $request->school;
        if ($guest->role == 'admin' && $request->filled('gender')) {
            $user->gender = $request->gender;
        }
        if ($guest->role == 'admin') {
            $user->role = $request->role;
        }
        if ($canEditBirthday) {
            $user->birthday = Carbon::createFromFormat('Y-m-d', $request->birthday);
        }
        $user->birthday_hidden = $request->boolean('birthday_hidden');
        $user->setGrade($request->grade);

        if ($request->password != "") {
            $this->validate($request, ['password' => 'required|string|min:6|max:72|confirmed']);
            $user->password = bcrypt($request->password);
        }

        if ($request->hasFile('image')) {
            $extn = '.' . $request->file('image')->guessClientExtension();
            $path = $request->file('image')->storeAs('user_avatars', $user->id . $extn);
            $user->image = $path;
        }

        if ($guest->role == 'teacher')
            $user->comments = $request->comments;
        $user->save();
        Cache::forget(User::nearbyBirthdaysCacheKey());

        return redirect('/insider/profile/' . $id);
    }

    public function telegramLink($id)
    {
        $user = User::findOrFail($id);
        $botUsername = app(\App\Services\TelegramSettings::class)->botUsername();

        if (!$botUsername) {
            $this->make_error_alert('Telegram не настроен', 'Администратор ещё не указал имя бота в настройках Telegram.', $destination = 'head');
            return redirect()->back();
        }

        $botUsername = ltrim($botUsername, '@');
        $user->telegram_link_token = Str::random(40);
        $user->telegram_link_token_expires_at = Carbon::now()->addMinutes(30);
        $user->save();

        return redirect()->away('https://t.me/' . $botUsername . '?start=bind_' . $user->telegram_link_token);
    }

    public function telegramUnlink($id)
    {
        $user = User::findOrFail($id);
        $user->telegram_chat_id = null;
        $user->telegram_link_token = null;
        $user->telegram_link_token_expires_at = null;
        $user->save();

        $this->make_success_alert('Telegram отключен', 'Уведомления в Telegram больше не будут отправляться.');

        return redirect()->back();
    }
}
