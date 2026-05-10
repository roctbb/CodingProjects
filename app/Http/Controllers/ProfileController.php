<?php

namespace App\Http\Controllers;

use App\CoinTransaction;
use App\CompletedCourse;
use App\Course;
use App\Http\Controllers\Controller;
use App\Rank;
use App\Solution;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $this->middleware('self')->except(['index', 'details']);
        $this->middleware('teacher')->only(['addMoney']);
        $this->middleware('admin')->only(['deleteCourse', 'course', 'deleteCurrentCourse', 'addMoney']);
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

        $completedCourseScores = CompletedCourse::query()
            ->select('user_id')
            ->selectRaw("
                SUM(CASE mark
                    WHEN 'S' THEN 2000
                    WHEN 'A+' THEN 1500
                    WHEN 'A' THEN 1200
                    WHEN 'A-' THEN 1000
                    WHEN 'B+' THEN 800
                    WHEN 'B' THEN 600
                    WHEN 'B-' THEN 400
                    WHEN 'C+' THEN 300
                    WHEN 'C' THEN 200
                    WHEN 'C-' THEN 100
                    WHEN 'D+' THEN 50
                    WHEN 'D' THEN 50
                    WHEN 'D-' THEN 50
                    ELSE 600
                END) as score
            ")
            ->groupBy('user_id')
            ->pluck('score', 'user_id');

        $ranks = Rank::orderBy('from')->get();
        $fallbackRank = $ranks->first();

        $users->each(function ($user) use ($solutionScores, $completedCourseScores, $ranks, $fallbackRank) {
            if ($user->rank_id && $user->manual_rank) {
                $user->setComputedScore($user->manual_rank->to - 1);
                $user->setComputedRank($user->manual_rank);
                return;
            }

            $score = (int) ($solutionScores[$user->id] ?? 0) + (int) ($completedCourseScores[$user->id] ?? 0);
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
                'courses.students',
                'courses.teachers',
                'completedCourses.course.students',
                'orders.good',
                'achievements.course',
                'achievements.task',
            ])->findOrFail($guest->id);
        } else {
            $user = User::with([
                'manual_rank',
                'managed_courses.students',
                'managed_courses.teachers',
                'courses.students',
                'courses.teachers',
                'completedCourses.course.students',
                'orders.good',
                'achievements.course',
                'achievements.task',
            ])->findOrFail($id);
        }

        $canViewMoneyHistory = $guest->id == $user->id || $guest->role == 'teacher' || $guest->role == 'admin';
        $coinTransactions = $canViewMoneyHistory
            ? $user->transactions()->latest()->take(20)->get()
            : collect();
        $coinBalance = $user->balance();
        $avatarFrames = User::avatarFrames();
        $activeAvatarFrame = $user->activeAvatarFrame();
        $achievements = $user->achievements
            ->where('status', \App\Achievement::STATUS_PUBLISHED)
            ->sortByDesc('published_at')
            ->values();

        // Use cached sticker retrieval and descriptions
        $stickers = $user->getStickers();
        $sticker_description = $user->getStickerDescriptions();

        return view('profile.details', compact('user', 'guest', 'stickers', 'sticker_description', 'coinTransactions', 'coinBalance', 'canViewMoneyHistory', 'avatarFrames', 'activeAvatarFrame', 'achievements'));
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
        $oldRank = $user->rank();
        $this->validate($request, [
            'name' => 'required|string',
            'mark' => 'required|string',
        ]);
        $course = new CompletedCourse();
        $course->name = $request->name;
        $course->mark = $request->mark;
        $course->user_id = $id;
        $course->save();

        $user->rescore();
        $user->awardRankPromotionIfNeeded($oldRank);

        return redirect()->back();
    }

    public function addMoney($id, Request $request)
    {
        $this->validate($request, [
            'description' => 'required|string',
            'amount' => 'integer|min:-100|max:100|required'
        ]);

        CoinTransaction::register($id, $request->amount, clean($request->description));

        $this->make_success_alert('Успех!', 'Деньги начислены.');

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
        if ($user->balance() < $cost) {
            $this->make_error_alert('Не хватает GC', 'Кастомное звание стоит ' . $cost . ' GC.');
            return redirect()->back()->withInput();
        }

        $startsAt = $user->hasActiveCustomTitle()
            ? $user->custom_title_expires_at
            : Carbon::now();

        $user->custom_title = $title;
        $user->custom_title_expires_at = $startsAt->copy()->addDays($user->customTitleDurationDays());
        $user->save();

        CoinTransaction::register($user->id, -1 * $cost, 'Custom title User #' . $user->id);

        $this->make_success_alert('Звание активно', 'Звание будет показываться в профиле до ' . $user->custom_title_expires_at->format('d.m.Y') . '.');

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

        if (!array_key_exists($frameKey, $frames)) {
            $this->make_error_alert('Рамка не найдена', 'Выберите рамку из списка.');
            return redirect()->back();
        }

        $frame = $frames[$frameKey];
        $cost = (int) $frame['cost'];

        if ($user->balance() < $cost) {
            $this->make_error_alert('Не хватает GC', 'Рамка "' . $frame['name'] . '" стоит ' . $cost . ' GC.');
            return redirect()->back();
        }

        $startsAt = $user->hasActiveAvatarFrame() && $user->avatar_frame === $frameKey
            ? $user->avatar_frame_expires_at
            : Carbon::now();

        $user->avatar_frame = $frameKey;
        $user->avatar_frame_expires_at = $startsAt->copy()->addDays($user->avatarFrameDurationDays($frameKey));
        $user->save();

        CoinTransaction::register($user->id, -1 * $cost, 'Avatar frame ' . $frameKey . ' User #' . $user->id);

        $this->make_success_alert(
            'Рамка активна',
            'Рамка "' . $frame['name'] . '" будет показываться до ' . $user->avatar_frame_expires_at->format('d.m.Y') . '.'
        );

        return redirect('/insider/profile/' . $user->id);
    }

    public function edit($id, Request $request)
    {
        $guest = User::findOrFail(Auth::User()->id);
        $user = User::findOrFail($id);

        $this->validate($request, [
            'name' => 'required|string',
            'school' => 'required|string',
            'grade' => 'integer|min:1|max:11|required',
            'hobbies' => 'required|string',
            'interests' => 'required|string',
            'image' => 'image|max:4000'
        ]);

        $user->name = $request->name;
        $user->git = $request->git;
        $user->telegram = $request->telegram;
        $user->hobbies = $request->hobbies;
        $user->interests = $request->interests;
        $user->school = $request->school;
        if (Auth::User()->role == 'teacher' || Auth::User()->role == 'admin') {
            $user->birthday = Carbon::createFromFormat('Y-m-d', $request->birthday);
        }
        $user->setGrade($request->grade);

        if ($request->password != "") {
            $this->validate($request, ['password' => 'required|string|min:6|confirmed']);
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

        return redirect('/insider/profile/' . $id);
    }

    public function telegramLink($id)
    {
        $user = User::findOrFail($id);
        $botUsername = trim((string) config('services.telegram.bot_username'));

        if (!$botUsername) {
            $this->make_error_alert('Telegram не настроен', 'Администратор ещё не указал TELEGRAM_BOT_USERNAME.', $destination = 'head');
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
