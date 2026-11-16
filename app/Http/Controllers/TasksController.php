<?php

namespace App\Http\Controllers;

use App\CoinTransaction;
use App\Course;
use App\CourseActivity;
use App\CourseStudentPoints;
use App\Achievement;
use App\Jobs\GenerateSolutionAchievement;
use App\Jobs\RecalculateCoursePoints;
use App\Jobs\RecalculateCourseStudentPoints;
use App\LessonStudentStats;
use App\ProgramStep;
use App\Http\Controllers\Controller;
use App\Question;
use App\QuestionVariant;
use App\Services\ChatGptService;
use App\Services\SolutionAchievementGenerator;
use App\Solution;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Auth;
use Notification;

class TasksController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('task');
        $this->middleware('teacher')->only('waiveDeadlinePenalty');
        $this->middleware('teacher')->only(['createForm', 'create', 'delete', 'editForm', 'edit', 'reviewSolutions', 'estimateSolution', 'phantomSolution', 'blockStudent', 'skipSolutionReview', 'skipStudentReviews', 'showSolutionAchievementPreview', 'previewSolutionAchievement', 'awardSolutionAchievement', 'aiTaskSummary']);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */

    public function createForm($course_id, $id)
    {
        $course = Course::findOrFail($course_id);
        $step = ProgramStep::with('lesson')->findOrFail($id);
        abort_unless((int) $step->lesson->program_id === (int) $course->program_id, 404);
        return view('steps.create_task', compact('course', 'step'));
    }

    private function normalizeAnswerMode(Request $request): void
    {
        if (!$request->has('answer_mode')) return;
        $request->merge(['is_code' => $request->answer_mode === 'code' ? 'on' : '0',
            'answer' => $request->answer_mode === 'short' ? $request->input('answer') : null]);
    }

    public function create($course_id, $id, Request $request)
    {
        return DB::transaction(fn () => $this->createTask($course_id, $id, $request));
    }

    private function saveNativeChecker(Task $task, Request $request): void
    {
        if ($request->answer_mode !== 'native') return;
        try {
            $config = json_decode($request->input('checker_config', '{}'), true, 64, JSON_THROW_ON_ERROR);
            abort_unless(is_array($config), 422, 'Настройки проверки должны быть объектом.');
            app(\App\Services\CodeChecking\Configuration::class)->save($task, $config, (int) $request->user()->id);
            $task->update(['is_quiz' => false]);
        } catch (\Illuminate\Validation\ValidationException $error) {
            throw \Illuminate\Validation\ValidationException::withMessages(['checker_config' => collect($error->errors())->flatten()->all()]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
            throw \Illuminate\Validation\ValidationException::withMessages(['checker_config' => $error->getMessage()]);
        }
    }

    private function createTask($course_id, $id, Request $request)
    {
        $step = ProgramStep::findOrFail($id);
        $this->validate($request, [
            'text' => 'required|string',
            'name' => 'required|string|max:255',
            'answer_mode' => 'sometimes|required|in:manual,code,short,native',
            'checker_config' => 'required_if:answer_mode,native|nullable|json',
            'answer' => 'required_if:answer_mode,short|nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'max_mark' => 'required|integer|min:0|max:1000',
            'ai_achievement_instruction' => 'nullable|string|max:1000'
        ]);


        $this->normalizeAnswerMode($request);

        $order = 100;
        if ($step->lesson->steps->count() != 0)
            $order = $step->lesson->steps->last()->sort_index + 1;

        if (!$request->has('price') or $request->price == null) $price = 0;
        else $price = $request->price;

        $task = Task::create(['text' => $request->text, 'step_id' => $step->id, 'name' => $request->name, 'max_mark' => $request->max_mark, 'sort_index' => $order,
            'is_star' => $request->is_star == 'on' ? true : false,
            'is_hidden' => $request->is_hidden == 'on' ? true : false,
            'xp_booster_enabled' => $request->xp_booster_enabled == 'on' ? true : false,
            'generates_ai_achievement' => $request->generates_ai_achievement == 'on' ? true : false,
            'ai_achievement_instruction' => $request->generates_ai_achievement == 'on' ? trim((string) $request->ai_achievement_instruction) : null,
            'only_remote' => $request->only_remote == 'on' ? true : false,
            'only_class' => $request->only_class == 'on' ? true : false]);
        $task->solution = $request->solution;
        $task->price = $price;

        if ($request->has('answer') and $request->answer != "") {
            $task->is_quiz = true;
            $task->answer = $request->answer;
        } else if ($request->has('is_code') and $request->is_code == 'on') {
            $task->is_code = true;
        }
        $task->save();
        $this->saveNativeChecker($task, $request);
        $course = Course::findOrFail($course_id);
        CourseActivity::recordTaskCreated($course, $task, Auth::user());

        // Recalculate points for all students after adding new task
        RecalculateCoursePoints::dispatch($course_id);

        return redirect('/insider/courses/' . $course_id . '/steps/' . $step->id . '#task' . $task->id);
    }

    public function delete($course_id, $id)
    {
        $task = Task::findOrFail($id);
        $step_id = $task->step_id;
        $task->delete();

        // Recalculate points for all students after deleting task
        RecalculateCoursePoints::dispatch($course_id);

        return redirect('/insider/courses/' . $course_id . '/steps/' . $step_id);
    }

    public function editForm($course_id, $id)
    {
        $task = Task::findOrFail($id);
        $course = Course::findOrFail($course_id);
        return view('steps.edit_task', compact('task', 'course'));
    }

    public function edit($course_id, $id, Request $request)
    {
        return DB::transaction(fn () => $this->editTask($course_id, $id, $request));
    }

    private function editTask($course_id, $id, Request $request)
    {
        $task = Task::findOrFail($id);
        $this->validate($request, [
            'text' => 'required|string',
            'name' => 'required|string|max:255',
            'answer_mode' => 'sometimes|required|in:manual,code,short,native',
            'checker_config' => 'required_if:answer_mode,native|nullable|json',
            'answer' => 'required_if:answer_mode,short|nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'max_mark' => 'required|integer|min:0|max:1000',
            'ai_achievement_instruction' => 'nullable|string|max:1000'
        ]);

        $this->normalizeAnswerMode($request);

        $task->text = $request->text;
        $task->max_mark = $request->max_mark;
        $task->name = $request->name;
        if (!$request->has('price')) $request->price = 0;
        $task->price = $request->price;
        $task->solution = $request->solution;
        if ($request->is_star == 'on') {
            $task->is_star = true;
        } else {
            $task->is_star = false;
        }
        if ($request->is_hidden == 'on') {
            $task->is_hidden = true;
        } else {
            $task->is_hidden = false;
        }
        if ($request->xp_booster_enabled == 'on') {
            $task->xp_booster_enabled = true;
        } else {
            $task->xp_booster_enabled = false;
        }
        if ($request->generates_ai_achievement == 'on') {
            $task->generates_ai_achievement = true;
            $task->ai_achievement_instruction = trim((string) $request->ai_achievement_instruction);
        } else {
            $task->generates_ai_achievement = false;
            $task->ai_achievement_instruction = null;
        }
        if ($request->only_class == 'on') {
            $task->only_class = true;
        } else {
            $task->only_class = false;
        }
        if ($request->only_remote == 'on') {
            $task->only_remote = true;
        } else {
            $task->only_remote = false;
        }
        if ($request->has('answer') and $request->answer != "") {
            $task->is_quiz = true;
            $task->answer = $request->answer;
        } else {
            $task->is_quiz = false;
        }
        if ($request->has('is_code') and $request->is_code == "on") {
            $task->is_quiz = false;
            $task->is_code = true;
        } else {
            $task->is_code = false;
        }

        $task->save();

        $this->saveNativeChecker($task, $request);
        if ($checker = $this->nativeChecker($task->id)) {
            $task->update(['is_code' => $checker->config['check_type'] !== 'manual', 'is_quiz' => false]);
        }
        // Recalculate points for all students after editing task
        RecalculateCoursePoints::dispatch($course_id);

        $step_id = $task->step_id;
        return redirect('/insider/courses/' . $course_id . '/steps/' . $step_id . '#task' . $id);
    }

    public function phantomSolution($course_id, $id, Request $request)
    {
        $task = Task::findOrFail($id);
        $course = Course::findOrFail($course_id);
        foreach ($course->students as $user) {
            $solution = new Solution();
            $solution->task_id = $id;
            $solution->course_id = $course_id;
            $solution->user_id = $user->id;
            $solution->submitted = Carbon::now();
            $solution->text = " ";
            $solution->save();
        }

        // Recalculate cached points since hidden task is now visible for all students
        RecalculateCoursePoints::dispatch($course_id);

        return redirect('/insider/courses/' . $course_id . '/steps/' . $task->step->id . '#task' . $id);
    }


    public function postSolution($course_id, $id, Request $request)
    {
        return app(\App\Services\CoinWallet::class)->transaction(Auth::id(),
            fn () => $this->submitSolution($course_id, $id, $request));
    }

    private function submitSolution($course_id, $id, Request $request)
    {
        $task = Task::findOrFail($id);
        abort_if($this->nativeChecker($task->id), 409, 'Для этой задачи используется API сдач Coding Projects.');
        $user = User::findOrFail(Auth::User()->id);
        $step_id = $task->step_id;
        $autoXpBoosterDelta = 0;

        $responseData = [
            'mark' => 0,
            'comment' => null,
        ];

        // Blocked users cannot submit
        if ($task->isBlocked($user->id, $course_id)) {
            $responseData['comment'] = 'Задача заблокирована для вас. Обратитесь к преподавателю.';

            return $request->expectsJson()
                ? $responseData
                : redirect('/insider/courses/' . $course_id . '/steps/' . $step_id . '#task' . $id);
        }

        $this->validate($request, [
            'text' => 'required|string',
        ]);

        $course = Course::findOrFail($course_id);

        $taskReward = null;
        $solution = new Solution();
        $solution->task_id = $id;
        $solution->user_id = Auth::User()->id;
        $solution->course_id = $course_id;
        $solution->submitted = Carbon::now();
        $solution->text = clean($request->text);

        if ($task->is_quiz) {
            $old_rank = $user->rank();
            if ($task->answer == $request->text) {
                $deadline = $task->getDeadline($course_id);
                $solution->applyDeadlinePenalty($task->max_mark, $deadline);
                $solution->comment = $solution->hasActiveDeadlinePenalty()
                    ? "Правильно. Сдано с опозданием. Штраф: -{$solution->deadline_penalty_amount} XP."
                    : "Правильно.";

                if ($task->price > 0 && $solution->qualifiesForTaskPriceReward() && !$task->hasRewardableFullSolution($user->id)) {
                    $taskReward = CoinTransaction::register($user->id, $user->taskCoinReward($task->price), "Task #" . $task->id);
                }

            } else {
                $solution->setEvaluatedMark(0);
                $solution->mark = $solution->normalizeSubmittedMark(0);
                $solution->raw_mark = $solution->mark;
                $solution->deadline_penalty_amount = 0;
                $solution->deadline_penalty_days = 0;
                $solution->comment = "Неверный ответ.";
            }

            if (count($course->Teachers) > 0) {
                $solution->teacher_id = $course->Teachers->first()->id;
            } else {
                $solution->teacher_id = 1;
            }
            $solution->checked = Carbon::now();
            $autoXpBoosterDelta = $this->applyDailyPetXpBoosterIfPossible($solution, $user, $task);
        }

        $solution->save();
        if ($taskReward && \Illuminate\Support\Facades\Schema::hasColumn('coin_transactions', 'solution_id')) $taskReward->forceFill(['solution_id'=>$solution->id])->save();
        CourseActivity::recordSolutionSubmitted($solution);
        if ($autoXpBoosterDelta > 0) {
            CourseActivity::recordXpBoosterUsed(
                $solution,
                0,
                $autoXpBoosterDelta,
                $user->activeLearningAvatarPetName(),
                $user->activeLearningAvatarPetKey()
            );
        }

        if ($task->is_quiz) {
            // Recalculate cached points after auto-grading quiz
            CourseStudentPoints::recalculate($course_id, $user->id);
            LessonStudentStats::recalculateForStudent($course_id, $user->id);
            $task->loadMissing('step.lesson');
            CourseActivity::recordProgressMilestones($course, $user, $task->step ? $task->step->lesson : null);

            $user->rescore();
            CourseActivity::recordXpMilestones($user);
            $user->awardRankPromotionIfNeeded($old_rank);
        }

        if (!$task->is_quiz && !$task->is_code) {
            $when = \Carbon\Carbon::now()->addSeconds(1);
            Notification::send($course->teachers, (new \App\Notifications\NewSolution($solution))->delay($when));
        }

        // If this is the first solution for a hidden task, recalculate cached points
        // because the task just became visible for this student
        if ($task->is_hidden && !$task->is_quiz) {
            CourseStudentPoints::recalculate($course_id, $user->id);
            LessonStudentStats::recalculateForStudent($course_id, $user->id);
        }


        $responseData['mark'] = $solution->mark;
        $responseData['comment'] = $solution->comment;
        $responseData['score_badge_class'] = $solution->scoreBadgeClass('bg-body');

        return $request->expectsJson()
            ? $responseData
            : redirect('/insider/courses/' . $course_id . '/steps/' . $step_id . '#task' . $id);
    }

    public function askForRecheck(Request $request, $course_id, $id, $solution_id) {
        $request->merge(['recheck_comment' => is_string($request->input('recheck_comment')) ? trim($request->input('recheck_comment')) : $request->input('recheck_comment')]);
        $request->validate([
            'recheck_comment' => ['required', 'string', 'min:10', 'max:1000'],
            'recheck_solution_id' => ['nullable', 'integer'],
        ], [
            'recheck_comment.required' => 'Напишите, с чем именно вы не согласны.',
            'recheck_comment.min' => 'Комментарий должен быть чуть подробнее.',
            'recheck_comment.max' => 'Комментарий слишком длинный.',
        ]);

        $solution = Solution::where('id', $solution_id)
            ->where('course_id', $course_id)
            ->where('task_id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $status = app(\App\Services\SolutionRecheck::class)->request($solution, trim($request->input('recheck_comment')));
        if ($request->expectsJson()) return response()->json($status);

        return redirect()->back();
    }

    public function reviewSolutions($course_id, $id, $student_id, Request $request)
    {
        $task = Task::findOrFail($id);
        $student = User::findOrFail($student_id);
        $request->validate(['legacy_attempt'=>'sometimes|integer|min:1']);
        $query=Solution::where('course_id',$course_id)->where('task_id',$task->id)->where('user_id',$student->id)->whereNotNull('submitted');
        $solution=$request->filled('legacy_attempt')?$query->findOrFail($request->integer('legacy_attempt')):$query->orderByDesc('submitted')->orderByDesc('id')->first();
        $location=app(\App\Services\TaskLocation::class);
        return redirect()->to($solution?$location->solution($solution):$location->task((int)$course_id,$task,(int)$student->id));
    }

    public function skipSolutionReview($course_id, $id, $solution_id, Request $request)
    {
        $request->validate(['review_token' => ['sometimes', 'string', 'regex:/^[a-f0-9]{64}$/D']]);
        $solution = Solution::where('course_id', $course_id)
            ->where('task_id', $id)
            ->findOrFail($solution_id);

        $skipped = $solution->skipPendingReview($request->input('review_token'));
        if ($request->expectsJson()) return response()->json(['skipped'=>$skipped]);
        if ($skipped) {
            $this->make_success_alert('Решение пропущено', 'Оно больше не будет висеть в очереди проверки.');
        } else {
            $this->make_info_alert('Без изменений', 'Это решение уже проверено, пропущено или ещё не отправлено.');
        }

        return redirect()->back();
    }

    public function skipStudentReviews($course_id, $id, $student_id)
    {
        $solutionIds = Solution::where('course_id', $course_id)
            ->where('task_id', $id)
            ->where('user_id', $student_id)
            ->whereNotNull('submitted')
            ->where(function ($query) {
                $query->whereNull('mark')
                    ->orWhere('recheck_requested', true);
            })
            ->where(function ($query) {
                $query->where('review_skipped', false)
                    ->orWhereNull('review_skipped');
            })
            ->pluck('id');

        $updated = 0;
        if ($solutionIds->isNotEmpty()) {
            $updated = Solution::whereIn('id', $solutionIds)->update([
                'review_skipped' => true,
                'recheck_requested' => false,
                'recheck_comment' => null,
            ]);
        }

        $this->make_success_alert('Решения пропущены', 'Снято с проверки: ' . $updated . '.');

        return redirect()->back();
    }

    public function previewSolutionAchievement($course_id, $id, $solution_id, SolutionAchievementGenerator $generator)
    {
        $solution = $this->achievementSolution($course_id, $id, $solution_id);

        $existingAchievement = Achievement::where('course_id', $course_id)
            ->where('task_id', $id)
            ->where('user_id', $solution->user_id)
            ->first();

        if ($existingAchievement) {
            $this->make_info_alert('Достижение уже есть', 'У ученика уже есть достижение за эту задачу. Его можно отредактировать в профиле.');

            return redirect('/insider/profile/' . $solution->user_id . '#achievement-' . $existingAchievement->id);
        }

        try {
            $preview = $generator->previewForSolution($solution, true);
        } catch (\Throwable $e) {
            \Log::error('Manual achievement preview failed', [
                'solution_id' => $solution->id,
                'course_id' => $course_id,
                'task_id' => $id,
                'user_id' => $solution->user_id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->make_error_alert('Предпросмотр не получился', 'Не удалось сгенерировать достижение для этого решения. Попробуйте позже.');

            return redirect()->back();
        }

        if (!$preview) {
            $this->make_error_alert('Предпросмотр не получился', 'Не удалось получить текст решения для генерации достижения.');

            return redirect()->back();
        }

        session()->put('achievement_previews.' . $solution->id, $preview);

        return redirect('/insider/courses/' . $course_id . '/tasks/' . $id . '/solution/' . $solution->id . '/achievement-preview');
    }

    public function showSolutionAchievementPreview($course_id, $id, $solution_id)
    {
        $solution = $this->achievementSolution($course_id, $id, $solution_id);

        $existingAchievement = Achievement::where('course_id', $course_id)
            ->where('task_id', $id)
            ->where('user_id', $solution->user_id)
            ->first();

        if ($existingAchievement) {
            $this->make_info_alert('Достижение уже есть', 'У ученика уже есть достижение за эту задачу. Его можно отредактировать в профиле.');

            return redirect('/insider/profile/' . $solution->user_id . '#achievement-' . $existingAchievement->id);
        }

        $achievementPreview = session('achievement_previews.' . $solution->id);
        if (!$achievementPreview) {
            $this->make_info_alert('Предпросмотр не найден', 'Сначала запустите генерацию достижения у нужного решения.');

            return redirect('/insider/courses/' . $course_id . '/tasks/' . $id . '/student/' . $solution->user_id . '#solution-' . $solution->id);
        }

        $course = $solution->course;
        $task = $solution->task;
        $student = $solution->user;
        $achievementPreviewVariants = $achievementPreview['variants'];

        return view('steps.achievement_preview', compact(
            'course',
            'task',
            'student',
            'solution',
            'achievementPreviewVariants'
        ));
    }

    public function awardSolutionAchievement($course_id, $id, $solution_id, Request $request, SolutionAchievementGenerator $generator)
    {
        $solution = $this->achievementSolution($course_id, $id, $solution_id);

        $existingAchievement = Achievement::where('course_id', $course_id)
            ->where('task_id', $id)
            ->where('user_id', $solution->user_id)
            ->first();

        if ($existingAchievement) {
            $this->make_info_alert('Достижение уже есть', 'У ученика уже есть достижение за эту задачу. Его можно отредактировать в профиле.');

            return redirect('/insider/profile/' . $solution->user_id . '#achievement-' . $existingAchievement->id);
        }

        $request->merge([
            'title' => is_string($request->title) ? trim(strip_tags($request->title)) : $request->title,
            'description' => is_string($request->description) ? trim(strip_tags($request->description)) : $request->description,
        ]);
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'description' => 'required|string|max:1000',
            'coin_reward' => 'nullable|integer|min:0|max:1000',
        ]);

        try {
            $achievement = $generator->createForSolution($solution, $data, true);
        } catch (\Throwable $e) {
            \Log::error('Manual achievement generation failed', [
                'solution_id' => $solution->id,
                'message' => $e->getMessage(),
            ]);

            $this->make_error_alert('Достижение не получилось', 'Не удалось сгенерировать достижение для этого решения. Попробуйте позже.');

            return redirect()->back();
        }

        if (!$achievement) {
            $this->make_error_alert('Достижение не получилось', 'Не удалось получить текст решения для генерации достижения.');

            return redirect()->back();
        }

        session()->forget('achievement_previews.' . $solution->id);
        $coinReward = (int) ($achievement->payload['coin_reward'] ?? 0);
        $this->make_success_alert(
            'Достижение выдано',
            'Оно появилось в профиле ученика и в пульсе. Кубок появится после генерации.' . ($coinReward > 0 ? ' Начислено ' . $coinReward . ' GC.' : '')
        );

        return redirect('/insider/profile/' . $solution->user_id . '#achievement-' . $achievement->id);
    }

    private function achievementSolution($courseId, $taskId, $solutionId): Solution
    {
        $solution = Solution::with('task.step.lesson', 'course.teachers', 'user')
            ->where('course_id', $courseId)
            ->where('task_id', $taskId)
            ->findOrFail($solutionId);
        $user = Auth::user();
        if ($user->role != 'admin' && (!$solution->course || !$solution->course->teachers->contains('id', $user->id))) {
            abort(403);
        }

        return $solution;
    }

    public function blockStudent($course_id, $id, $student_id)
    {
        return DB::transaction(fn () => $this->blockTaskStudent($course_id, $id, $student_id));
    }

    private function blockTaskStudent($course_id, $id, $student_id)
    {
        $task = Task::findOrFail($id);
        $course = Course::findOrFail($course_id);
        $student = User::findOrFail($student_id);
        abort_unless($course->students()->where('users.id', $student_id)->exists(), 404);

        // Create block record if not exists
        if (!\App\BlockedTask::where('task_id', $id)
            ->where('user_id', $student_id)
            ->where('course_id', $course_id)->exists()) {
            \App\BlockedTask::create([
                'task_id' => $id,
                'user_id' => $student_id,
                'course_id' => $course_id,
                'blocked_at' => Carbon::now(),
                'reason' => 'plagiarism'
            ]);
            $this->recordBlockEvent($course_id, $id, $student_id, 'blocked');
        }

        // Zero out all existing marks for this task/user/course
        $solutions = Solution::where('task_id', $id)
            ->where('user_id', $student_id)
            ->where('course_id', $course_id)
            ->get();
        foreach ($solutions as $solution) {
            $solution->mark = 0;
            $solution->setEvaluatedMark(0);
            $solution->raw_mark = 0;
            $solution->deadline_penalty_amount = 0;
            $solution->deadline_penalty_days = 0;
            $solution->deadline_penalty_paid_at = null;
            $solution->deadline_penalty_waived_at = null;
            $solution->deadline_penalty_waived_by = null;
            $solution->xp_booster_amount = 0;
            $solution->xp_booster_used_at = null;
            $solution->comment = 'Решение заблокировано (плагиат).';
            $solution->teacher_id = Auth::User()->id;
            if ($solution->checked == null) {
                $solution->checked = Carbon::now();
            }
            $solution->save();
        }

        // Invalidate cached score
        $student->rescore();

        // Recalculate cached points for the student in this course
        CourseStudentPoints::recalculate($course_id, $student_id);

        // Recalculate lesson stats for all lessons this student is enrolled in
        LessonStudentStats::recalculateForStudent($course_id, $student_id);

        return redirect()->back();
    }

    public function unblockStudent($course_id, $id, $student_id)
    {
        return DB::transaction(fn () => $this->unblockTaskStudent($course_id, $id, $student_id));
    }

    private function unblockTaskStudent($course_id, $id, $student_id)
    {
        // Remove block records for this task/user/course
        $removed = \App\BlockedTask::where('task_id', $id)
            ->where('user_id', $student_id)
            ->where('course_id', $course_id)
            ->delete();
        if ($removed) $this->recordBlockEvent($course_id, $id, $student_id, 'unblocked');

        // Recalculate cached points for the student in this course
        CourseStudentPoints::recalculate($course_id, $student_id);

        // Recalculate lesson stats for all lessons this student is enrolled in
        LessonStudentStats::recalculateForStudent($course_id, $student_id);

        // Do not modify marks here; just allow new submissions
        return redirect()->back();
    }

    private function recordBlockEvent(int $course, int $task, int $student, string $action): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('task_block_events')) DB::table('task_block_events')->insert([
            'course_id'=>$course,'task_id'=>$task,'student_id'=>$student,'actor_id'=>Auth::id(),
            'action'=>$action,'reason'=>$action === 'blocked' ? 'plagiarism' : null,'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function estimateSolution($course_id, $id, Request $request)
    {
        $solution = Solution::where('course_id', $course_id)->findOrFail($id);
        return app(\App\Services\CoinWallet::class)->transaction($solution->user_id,
            fn () => $this->gradeSolution($course_id, $id, $request));
    }

    private function gradeSolution($course_id, $id, Request $request)
    {
        $solution = Solution::findOrFail($id);
        $this->validate($request, [
            'mark' => 'required|integer|min:0|max:' . $solution->task->max_mark,
            'comment' => 'nullable|string|max:20000',
        ]);

        $old_rank = $solution->user->rank();
        $deadline = $solution->task->getDeadline($course_id);

        $solution->setEvaluatedMark($request->mark);
        $solution->applyDeadlinePenalty($request->mark, $deadline);
        $editableComment = (string) ($request->input('comment') ?? '');
        $comment = $editableComment;
        if ($solution->hasActiveDeadlinePenalty()) {
            $comment = trim("Сдано с опозданием. Штраф: -{$solution->deadline_penalty_amount} XP.\n\n" . $comment);
        }
        $solution->comment = $comment;

        if ($solution->task->price > 0 && $solution->qualifiesForTaskPriceReward() && !$solution->task->hasRewardableFullSolution($solution->user_id)) {
            CoinTransaction::register($solution->user_id, $solution->user->taskCoinReward($solution->task->price), "Task #" . $solution->task->id, solutionId: $solution->id);
        }

        $autoXpBoosterDelta = $this->applyDailyPetXpBoosterIfPossible($solution, $solution->user, $solution->task);

        $solution->teacher_id = Auth::User()->id;
        $solution->checked = Carbon::now();
        $solution->recheck_requested = false;
        $solution->save();
        $feedback = CourseActivity::recordSolutionChecked($solution);
        if ($feedback) {
            // Explicit provenance: legacy automatic checks also had teacher_id.
            $feedback->payload = array_replace($feedback->payload ?? [], [
                'feedback_source' => 'teacher',
                'feedback_sha256' => hash('sha256', (string) $solution->comment),
                'editable_comment' => $editableComment,
            ]);
            $feedback->save();
        }
        if ($autoXpBoosterDelta > 0) {
            CourseActivity::recordXpBoosterUsed(
                $solution,
                0,
                $autoXpBoosterDelta,
                $solution->user->activeLearningAvatarPetName(),
                $solution->user->activeLearningAvatarPetKey()
            );
        }
        $this->dispatchAiAchievementGeneration($solution);

        // Recalculate cached points for the student in this course
        CourseStudentPoints::recalculate($course_id, $solution->user_id);

        // Recalculate lesson stats for all lessons this student is enrolled in
        LessonStudentStats::recalculateForStudent($course_id, $solution->user_id);
        $solution->loadMissing('task.step.lesson', 'course', 'user');
        CourseActivity::recordProgressMilestones($solution->course, $solution->user, $solution->task && $solution->task->step ? $solution->task->step->lesson : null);

        $solution->user->rescore();
        CourseActivity::recordXpMilestones($solution->user);

        $when = \Carbon\Carbon::now()->addSeconds(1);
        Notification::send($solution->user, (new \App\Notifications\NewMark($solution))->delay($when));

        $solution->user->awardRankPromotionIfNeeded($old_rank);

        if($request->expectsJson())return response()->json(app(\App\Services\CodeChecking\History::class)->event($request->user(),(int)$course_id,$solution->task,(int)$solution->id))->header('Cache-Control','private, no-store');
        return redirect()->back();
    }

    private function dispatchAiAchievementGeneration(Solution $solution)
    {
        if (!$solution->isEligibleForAiAchievement()) {
            return;
        }

        try {
            GenerateSolutionAchievement::dispatch($solution->id)->afterCommit();
        } catch (\Throwable $e) {
            \Log::warning('AI achievement dispatch failed', [
                'solution_id' => $solution->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function applyDailyPetXpBoosterIfPossible(Solution $solution, ?User $student = null, ?Task $task = null): int
    {
        $student = $student ?: ($solution->relationLoaded('user') ? $solution->user : User::find($solution->user_id));
        $task = $task ?: ($solution->relationLoaded('task') ? $solution->task : Task::find($solution->task_id));

        if (!$student || !$task) {
            return 0;
        }

        $solution->setRelation('user', $student);
        $solution->setRelation('task', $task);

        if (!$student->canUseDailyAutoXpBoosterToday()
            || !$task->xp_booster_enabled
            || $solution->hasXpBooster()
            || $solution->mark === null
            || (int) $solution->mark <= 0
            || (int) $solution->mark >= (int) $task->max_mark) {
            return 0;
        }

        $markBeforeBooster = (int) $solution->mark;
        $previousBoosterUsedAt = $solution->xp_booster_used_at;
        $previousBoosterAmount = (int) $solution->xp_booster_amount;

        $solution->xp_booster_used_at = Carbon::now();
        $solution->applyDeadlinePenalty(
            $solution->raw_mark === null ? $markBeforeBooster : $solution->raw_mark,
            $task->getDeadline($solution->course_id)
        );

        if ((int) $solution->mark <= $markBeforeBooster) {
            $solution->mark = $markBeforeBooster;
            $solution->xp_booster_used_at = $previousBoosterUsedAt;
            $solution->xp_booster_amount = $previousBoosterAmount;

            return 0;
        }

        if (!$student->consumeDailyAutoXpBoosterToday()) {
            $solution->mark = $markBeforeBooster;
            $solution->xp_booster_used_at = $previousBoosterUsedAt;
            $solution->xp_booster_amount = $previousBoosterAmount;

            return 0;
        }

        $delta = (int) $solution->mark - $markBeforeBooster;
        $petName = $student->activeLearningAvatarPetName() ?: 'питомец';
        $student->notify(new \App\Notifications\PetEvent('Ваш питомец ' . $petName . ' автоматически применил бесплатный XP-бустер: +' . $delta . ' XP.', 'success'));

        return $delta;
    }

    public function aiTaskSummary($course_id, $id, Request $request, ChatGptService $chatGpt)
    {
        $course = Course::with('teachers')->findOrFail($course_id);
        $task = Task::with('step.lesson')->findOrFail($id);
        $user = Auth::user();

        if ($user->role != 'admin' && !$course->teachers->contains('id', $user->id)) {
            abort(403);
        }

        $answers = ($task->is_code || $this->nativeChecker($task->id))
            ? $this->buildNativeTaskSummaryAnswers($course, $task)
            : $this->buildLocalTaskSummaryAnswers($course, $task);

        if ($answers === null) {
            $this->make_error_alert('Не удалось получить решения', 'Сначала импортируйте решения и настройте проверку в Coding Projects.');
            return redirect()->back();
        }

        if (trim($answers) === '') {
            $this->make_error_alert('Нет решений', 'Пока нечего пересказывать: у задачи нет отправленных решений.');
            return redirect()->back();
        }

        $instruction = trim((string) $request->input('summary_instruction', ''));
        $instruction = Str::limit($instruction, 1000, '');

        $prompt = 'Ты редактор школьного медиа. По решениям учеников сделай живую короткую новость для ленты курса. '
            . 'Не оценивай и не ранжируй учеников. Не выдумывай деталей, которых нет в ответах. '
            . 'Собери общие идеи, необычные находки и общий тон работ. ';

        if ($task->is_code) {
            $prompt .= 'Это автопроверяемая задача с кодом: пересказывай задумки, механики и получившиеся проекты, а не делай ревью кода. ';
        }

        if ($instruction !== '') {
            $prompt .= 'Учитель добавил фокус пересказа, обязательно учти его: ' . $instruction . ' ';
        }

        $prompt .= 'Пиши на русском, 2-4 коротких абзаца, без markdown-заголовков, без списка, до 1200 символов.';

        try {
            $instructionBlock = $instruction !== ''
                ? "Фокус учителя:\n{$instruction}\n\n"
                : '';
            $summary = $chatGpt->generate([
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => "Курс: {$course->name}\nЗадача: {$task->name}\n{$instructionBlock}Условие задачи:\n" . Str::limit(strip_tags((string) $task->text), 3000) . "\n\nРешения учеников:\n" . $answers],
            ], ['command' => 'task_summary']);
        } catch (\Throwable $e) {
            \Log::error('Task AI summary failed', [
                'course_id' => $course->id,
                'task_id' => $task->id,
                'message' => $e->getMessage(),
            ]);
            $this->make_error_alert('Пересказ не получился', 'Не удалось получить ответ от ChatGPT. Попробуйте позже.');
            return redirect()->back();
        }

        CourseActivity::recordTaskAiSummary($course, $task, $user, $summary, $instruction);
        $this->make_success_alert('Новость добавлена в пульс', 'Полный пересказ теперь показан на странице задачи.');

        return redirect('/insider/courses/' . $course->id . '/steps/' . $task->step_id . '#task-ai-summary-' . $task->id);
    }

    private function buildLocalTaskSummaryAnswers(Course $course, Task $task): string
    {
        $solutions = Solution::with('user:id,name')
            ->where('course_id', $course->id)
            ->where('task_id', $task->id)
            ->whereNotNull('submitted')
            ->orderBy('submitted', 'desc')
            ->get()
            ->unique('user_id')
            ->take(30)
            ->values();

        if ($solutions->isEmpty()) {
            return '';
        }

        return $solutions->map(function ($solution, $index) {
            $studentName = optional($solution->user)->name ?: 'Ученик ' . ($index + 1);
            $text = trim(strip_tags((string) $solution->text));

            return ($index + 1) . '. ' . $studentName . ":\n" . Str::limit($text, 1600);
        })->implode("\n\n");
    }

    private function buildNativeTaskSummaryAnswers(Course $course, Task $task): ?string
    {
        if ($this->nativeChecker($task->id)) {
            $seen = []; $parts = [];
            foreach (\App\CodeSubmission::forCourseStudents($course->id)->where('task_id', $task->id)->orderByDesc('created_at')->cursor() as $submission) {
                if (isset($seen[$submission->user_id])) continue;
                $seen[$submission->user_id] = true;
                $source = app(\App\Services\CodeChecking\Integrity::class)->source($submission) ?? $submission->answer_text ?? '';
                $parts[] = 'Ученик '.$submission->user_id.":\n".Str::limit($source, 2200);
                if (count($parts) >= 30) break;
            }
            return implode("\n\n", $parts);
        }
        return null;
    }

    public function waiveDeadlinePenalty($course_id, $id, $solution_id)
    {
        $teacher = Auth::user();
        $course = Course::findOrFail($course_id);
        abort_unless($teacher->role === 'admin'
            || ($teacher->role === 'teacher' && $course->teachers()->where('users.id', $teacher->id)->exists()), 403);

        $result = DB::transaction(function () use ($course_id, $id, $solution_id, $teacher) {
            $solution = Solution::where('course_id', $course_id)
                ->where('task_id', $id)
                ->lockForUpdate()->findOrFail($solution_id);

            if (!$solution->hasActiveDeadlinePenalty() || $solution->mark === null) {
                return [$solution, false];
            }

            $student = $solution->user;
            $oldRank = $student->rank();
            $rawMark = $solution->raw_mark ?? max(0, $solution->mark + $solution->deadline_penalty_amount - $solution->xp_booster_amount);
            $solution->deadline_penalty_waived_at = Carbon::now();
            $solution->deadline_penalty_waived_by = $teacher->id;
            $solution->applyDeadlinePenalty($rawMark);
            $shouldReward = $solution->task->price > 0
                && $solution->qualifiesForTaskPriceReward()
                && !$solution->task->hasRewardableFullSolution($student->id);
            $solution->save();

            CourseStudentPoints::recalculate($course_id, $student->id);
            LessonStudentStats::recalculateForStudent($course_id, $student->id);
            CourseActivity::recordDeadlinePenaltyWaived($solution, $teacher);

            if ($shouldReward) {
                CoinTransaction::registerOnce($student->id, $student->taskCoinReward($solution->task->price), 'Task #' . $solution->task_id, solutionId: $solution->id);
            }

            return [$solution, true, $oldRank];
        });

        [$solution, $changed] = $result;
        if ($changed) {
            $solution->user->rescore();
            CourseActivity::recordXpMilestones($solution->user);
            $solution->user->awardRankPromotionIfNeeded($result[2]);
            $this->dispatchAiAchievementGeneration($solution);
            $this->make_success_alert('Штраф снят', 'XP за решение восстановлен без списания GC у ученика.');
        } else {
            $this->make_info_alert('Без изменений', 'У этого решения нет активного штрафа за дедлайн.');
        }

        if (request()->expectsJson()) return $this->solutionAwardResponse($solution);
        return redirect('/insider/courses/' . $course_id . '/tasks/' . $id . '/student/' . $solution->user_id . '#solution-' . $solution->id);
    }

    public function payDeadlinePenalty($course_id, $id, $solution_id)
    {
        return app(\App\Services\CoinWallet::class)->transaction(Auth::id(),
            fn () => $this->payDeadlinePenaltyLocked($course_id, $id, $solution_id));
    }

    private function payDeadlinePenaltyLocked($course_id, $id, $solution_id)
    {
        $solution = Solution::where('id', $solution_id)
            ->where('task_id', $id)
            ->where('course_id', $course_id)->lockForUpdate()
            ->firstOrFail();
        $user = Auth::user();

        if ($solution->user_id != $user->id) {
            abort(403);
        }

        if (!$solution->hasActiveDeadlinePenalty()) {
            if (request()->expectsJson()) return $this->solutionAwardResponse($solution);
            $this->make_info_alert('Штраф уже снят', 'У этого решения нет активного штрафа за дедлайн.');
            return redirect()->back();
        }

        $cost = $solution->deadlinePenaltyCost();
        $this->checkExpectedAwardPrice($cost);

        if ($user->balance() < $cost) {
            abort_if(request()->expectsJson(), 409, 'Недостаточно GC для снятия штрафа.');
            $this->make_error_alert('Не хватает GC', 'Чтобы снять штраф, нужно ' . $cost . ' GC.');
            return redirect()->back();
        }

        $old_rank = $user->rank();

        $solution->deadline_penalty_paid_at = Carbon::now();
        $solution->applyDeadlinePenalty($solution->raw_mark === null ? $solution->mark : $solution->raw_mark, $solution->task->getDeadline($course_id));
        $shouldRewardTaskPrice = $solution->task->price > 0
            && $solution->qualifiesForTaskPriceReward()
            && !$solution->task->hasRewardableFullSolution($solution->user_id);

        CoinTransaction::register($user->id, -1 * $cost, 'Снятие штрафа за дедлайн. Решение #' . $solution->id);

        $solution->save();
        CourseActivity::recordDeadlinePenaltyPaid($solution, $cost);
        $this->dispatchAiAchievementGeneration($solution);

        if ($shouldRewardTaskPrice) {
            CoinTransaction::register($solution->user_id, $user->taskCoinReward($solution->task->price), "Task #" . $solution->task->id, solutionId: $solution->id);
        }

        CourseStudentPoints::recalculate($course_id, $user->id);
        LessonStudentStats::recalculateForStudent($course_id, $user->id);

        $user->rescore();
        CourseActivity::recordXpMilestones($user);
        $user->awardRankPromotionIfNeeded($old_rank);

        $this->make_success_alert('Штраф снят', 'XP за решение пересчитан, со счета списано ' . $cost . ' GC.');

        if (request()->expectsJson()) return $this->solutionAwardResponse($solution);
        return redirect()->back();
    }

    public function useXpBooster($course_id, $id, $solution_id)
    {
        return app(\App\Services\CoinWallet::class)->transaction(Auth::id(),
            fn () => $this->useXpBoosterLocked($course_id, $id, $solution_id));
    }

    private function useXpBoosterLocked($course_id, $id, $solution_id)
    {
        $solution = Solution::where('id', $solution_id)
            ->where('task_id', $id)
            ->where('course_id', $course_id)->lockForUpdate()
            ->firstOrFail();
        $user = Auth::user();

        if ($solution->user_id != $user->id) {
            abort(403);
        }

        if (!$solution->task->xp_booster_enabled) {
            abort_if(request()->expectsJson(), 409, 'Бустер недоступен для этой задачи.');
            $this->make_info_alert('Бустер недоступен', 'Для этой задачи нельзя применить XP-бустер.');
            return redirect()->back();
        }

        if ($solution->hasXpBooster()) {
            if (request()->expectsJson()) return $this->solutionAwardResponse($solution);
            $this->make_info_alert('Бустер уже применен', 'К этому решению уже применяли XP-бустер.');
            return redirect()->back();
        }

        if ($solution->mark === null || $solution->mark >= $solution->task->max_mark) {
            abort_if(request()->expectsJson(), 409, 'Бустер не увеличит результат этой сдачи.');
            $this->make_info_alert('Бустер не нужен', 'Это решение уже набрало максимум XP или еще не проверено.');
            return redirect()->back();
        }

        $cost = $solution->xpBoosterCost($user);
        $this->checkExpectedAwardPrice($cost);

        if ($cost > 0 && $user->balance() < $cost) {
            abort_if(request()->expectsJson(), 409, 'Недостаточно GC для бустера.');
            $this->make_error_alert('Не хватает GC', 'Чтобы применить бустер, нужно ' . $cost . ' GC.');
            return redirect()->back();
        }

        $old_rank = $user->rank();
        $markBeforeBooster = $solution->mark;

        $solution->xp_booster_used_at = Carbon::now();
        $solution->applyDeadlinePenalty($solution->raw_mark === null ? $solution->mark : $solution->raw_mark, $solution->task->getDeadline($course_id));

        if ($solution->mark <= $markBeforeBooster) {
            abort_if(request()->expectsJson(), 409, 'Бустер применяется до штрафа и не увеличит зачтённый XP.');
            $solution->xp_booster_used_at = null;
            $solution->xp_booster_amount = 0;
            $this->make_info_alert('Бустер не сработает', 'Бустер применяется до штрафа за дедлайн и не увеличит итоговый XP для этого решения.');
            return redirect()->back();
        }

        if ($cost > 0) {
            CoinTransaction::register($user->id, -1 * $cost, 'XP booster Solution #' . $solution->id);
        } else {
            $user->consumeFreeXpBooster();
        }

        $solution->save();
        CourseActivity::recordXpBoosterUsed($solution, $cost, $solution->mark - $markBeforeBooster);

        CourseStudentPoints::recalculate($course_id, $user->id);
        LessonStudentStats::recalculateForStudent($course_id, $user->id);

        $user->rescore();
        CourseActivity::recordXpMilestones($user);
        $user->awardRankPromotionIfNeeded($old_rank);

        $this->make_success_alert('Бустер применен', 'Решение получило +' . ($solution->mark - $markBeforeBooster) . ' XP.' . ($cost > 0 ? ' Со счета списано ' . $cost . ' GC.' : ' Использован бесплатный бустер от питомца.'));

        if (request()->expectsJson()) return $this->solutionAwardResponse($solution);
        return redirect()->back();
    }

    private function solutionAwardResponse(Solution $solution)
    {
        $solution->refresh();
        return response()->json(['awarded_xp' => $solution->mark,
            'award_breakdown' => app(\App\Services\CodeChecking\SolutionAward::class)->view($solution, Auth::user())])->header('Cache-Control', 'private, no-store');
    }

    private function checkExpectedAwardPrice(int $cost): void
    {
        if (!request()->expectsJson()) return;
        request()->validate(['expected_price' => 'sometimes|integer|min:0']);
        abort_if(request()->has('expected_price') && request()->integer('expected_price') !== $cost, 409, 'Цена изменилась. Обновите страницу перед покупкой.');
    }

    public function buyGeekPasteExtraAttempt($course_id, $id)
    {
        $task = Task::findOrFail($id);
        $course = Course::findOrFail($course_id);
        $user = Auth::user();

        if (!$task->is_code) {
            $this->make_info_alert('Попытка недоступна', 'Дополнительные попытки доступны только для задач с кодом.');
            return redirect()->back();
        }

        if ($task->isBlocked($user->id, $course->id)) {
            $this->make_error_alert('Задача заблокирована', 'Для этой задачи новые сдачи запрещены.');
            return redirect()->back();
        }

        if ($course->teachers->contains('id', $user->id) || $user->role != 'student') {
            abort(403);
        }

        $cost = $user->geekPasteExtraAttemptCost();
        if ($cost > 0 && $user->balance() < $cost) {
            $this->make_error_alert('Не хватает GC', 'Дополнительная попытка стоит ' . $cost . ' GC.');
            return redirect()->back();
        }

        if ($this->nativeChecker($task->id)) {
            app(\App\Services\CodeChecking\Attempts::class)->buy($user, $task, (int)$course_id);
            $this->make_success_alert('Попытка добавлена', 'Можно отправить ещё одно решение в Coding Projects.');
            return redirect()->back();
        }
        abort(409, 'Сначала импортируйте задачу и настройте проверку в Coding Projects.');
    }

    public function makeLower($course_id, $id, Request $request)
    {
        $task = Task::findOrFail($id);
        $task->sort_index -= 1;
        $task->save();
        return redirect('/insider/courses/' . $course_id . '/steps/' . $task->step->id . '#task' . $id);
    }

    public function makeDeadline($course_id, $id, Request $request)
    {
        $course = Course::findOrFail($course_id);
        abort_unless($request->user()->role === 'admin' || $course->teachers()->where('users.id', $request->user()->id)->exists(), 403);
        app(\App\Services\CourseDeadlineService::class)->set(
            $course, Task::findOrFail($id), $request->deadline ?: null, $request->penalty
        );
        return back();
    }

    public function makeUpper($course_id, $id, Request $request)
    {
        $task = Task::findOrFail($id);
        $task->sort_index += 1;
        $task->save();
        return redirect('/insider/courses/' . $course_id . '/steps/' . $task->step->id . '#task' . $id);
    }

    public function toNextTask($course_id, $id, Request $request)
    {
        $task = Task::findOrFail($id);
        $next = $task->step->nextStep();
        if ($next != null) {
            $task->step_id = $next->id;
            $task->save();
            return redirect('/insider/courses/' . $course_id . '/steps/' . $next->id . '#task' . $id);
        }

        return redirect('/insider/courses/' . $course_id . '/steps/' . $task->step->id . '#task' . $id);
    }

    public function toPreviousTask($course_id, $id, Request $request)
    {
        $task = Task::findOrFail($id);
        $previous = $task->step->previousStep();
        if ($previous != null) {
            $task->step_id = $previous->id;
            $task->save();
            return redirect('/insider/courses/' . $course_id . '/steps/' . $previous->id . '#task' . $id);
        }

        return redirect('/insider/courses/' . $course_id . '/steps/' . $task->step->id . '#task' . $id);
    }

    public function recheckAllSolutions($course_id, $id)
    {
        $task = Task::findOrFail($id);
        $course = Course::findOrFail($course_id);

        if ($this->nativeChecker($task->id)) {
            $result = app(\App\Services\CodeChecking\Submissions::class)->recheckLatest(Auth::user(), (int) $course_id, (int) $id, false);
            return redirect()->back()->with('success', 'Отправлено на перепроверку решений: '.count($result['execution_ids']));
        }

        if (!$task->is_code) {
            return redirect()->back()->with('error', 'Перепроверка доступна только для задач с кодом.');
        }

        return redirect()->back()->with('error', 'Сначала импортируйте решения и настройте проверку в Coding Projects.');
    }

    private function nativeChecker(int $taskId): ?\App\CodeTaskChecker
    {
        return \Illuminate\Support\Facades\Schema::hasTable('code_task_checkers') ? \App\CodeTaskChecker::find($taskId) : null;
    }
}
