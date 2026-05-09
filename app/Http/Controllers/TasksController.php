<?php

namespace App\Http\Controllers;

use App\CoinTransaction;
use App\Course;
use App\CourseActivity;
use App\CourseStudentPoints;
use App\Jobs\RecalculateCoursePoints;
use App\Jobs\RecalculateCourseStudentPoints;
use App\LessonStudentStats;
use App\ProgramStep;
use App\Http\Controllers\Controller;
use App\Question;
use App\QuestionVariant;
use App\Services\GeekPasteClient;
use App\Solution;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Auth;
use Notification;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

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
        $this->middleware('teacher')->only(['create', 'delete', 'editForm', 'edit', 'reviewSolutions', 'estimateSolution', 'phantomSolution', 'blockStudent']);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */

    public function create($course_id, $id, Request $request)
    {
        $step = ProgramStep::findOrFail($id);
        $this->validate($request, [
            'text' => 'required|string',
            'name' => 'required|string',
            'price' => 'nullable|numeric|min:0',
            'max_mark' => 'required|integer|min:0|max:1000'
        ]);


        $order = 100;
        if ($step->lesson->steps->count() != 0)
            $order = $step->lesson->steps->last()->sort_index + 1;

        if (!$request->has('price') or $request->price == null) $price = 0;
        else $price = $request->price;

        $task = Task::create(['text' => $request->text, 'step_id' => $step->id, 'name' => $request->name, 'max_mark' => $request->max_mark, 'sort_index' => $order,
            'is_star' => $request->is_star == 'on' ? true : false,
            'is_hidden' => $request->is_hidden == 'on' ? true : false,
            'xp_booster_enabled' => $request->xp_booster_enabled == 'on' ? true : false,
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
        $task = Task::findOrFail($id);
        $this->validate($request, [
            'text' => 'required|string',
            'name' => 'required|string',
            'price' => 'nullable|numeric|min:0',
            'max_mark' => 'required|integer|min:0|max:1000'
        ]);

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
        $task = Task::findOrFail($id);
        $user = User::findOrFail(Auth::User()->id);
        $step_id = $task->step_id;

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
                    CoinTransaction::register($user->id, $task->price, "Task #" . $task->id);
                }

            } else {
                $solution->mark = 0;
                $solution->raw_mark = 0;
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
        }

        $solution->save();
        CourseActivity::recordSolutionSubmitted($solution);

        if ($task->is_quiz) {
            // Recalculate cached points after auto-grading quiz
            CourseStudentPoints::recalculate($course_id, $user->id);
            LessonStudentStats::recalculateForStudent($course_id, $user->id);

            $user->rescore();
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

    public function askForRecheck($course_id, $id, $solution_id) {
        $solution = Solution::findOrFail($solution_id);

        if (!$solution->recheck_requested and $solution->task->is_code) {
            $solution->recheck_requested = true;
            $solution->save();

            $when = \Carbon\Carbon::now()->addSeconds(1);
            \Notification::send($solution->course->teachers, (new \App\Notifications\NewSolution($solution))->delay($when));
        }

        return redirect()->back();
    }

    public function reviewSolutions($course_id, $id, $student_id, Request $request)
    {
        $task = Task::findOrFail($id);
        $student = User::findOrFail($student_id);
        $course = Course::findOrFail($course_id);
        $solutions = $task->solutions->filter(function ($value) use ($student) {
            return $value->user_id == $student->id;
        });
        return view('steps.review', compact('task', 'student', 'solutions', 'course'));
    }

    public function blockStudent($course_id, $id, $student_id)
    {
        $task = Task::findOrFail($id);
        $course = Course::findOrFail($course_id);
        $student = User::findOrFail($student_id);

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
        }

        // Zero out all existing marks for this task/user/course
        $solutions = Solution::where('task_id', $id)
            ->where('user_id', $student_id)
            ->where('course_id', $course_id)
            ->get();
        foreach ($solutions as $solution) {
            $solution->mark = 0;
            $solution->raw_mark = 0;
            $solution->deadline_penalty_amount = 0;
            $solution->deadline_penalty_days = 0;
            $solution->deadline_penalty_paid_at = null;
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
        // Remove block records for this task/user/course
        \App\BlockedTask::where('task_id', $id)
            ->where('user_id', $student_id)
            ->where('course_id', $course_id)
            ->delete();

        // Recalculate cached points for the student in this course
        CourseStudentPoints::recalculate($course_id, $student_id);

        // Recalculate lesson stats for all lessons this student is enrolled in
        LessonStudentStats::recalculateForStudent($course_id, $student_id);

        // Do not modify marks here; just allow new submissions
        return redirect()->back();
    }

    public function estimateSolution($course_id, $id, Request $request)
    {
        $solution = Solution::findOrFail($id);
        $this->validate($request, [
            'mark' => 'required|integer|min:0|max:' . $solution->task->max_mark
        ]);

        $old_rank = $solution->user->rank();
        $deadline = $solution->task->getDeadline($course_id);

        $solution->applyDeadlinePenalty($request->mark, $deadline);
        $comment = $request->comment;
        if ($solution->hasActiveDeadlinePenalty()) {
            $comment = trim("Сдано с опозданием. Штраф: -{$solution->deadline_penalty_amount} XP.\n\n" . $comment);
        }
        $solution->comment = $comment;

        if ($solution->task->price > 0 && $solution->qualifiesForTaskPriceReward() && !$solution->task->hasRewardableFullSolution($solution->user_id)) {
            CoinTransaction::register($solution->user_id, $solution->task->price, "Task #" . $solution->task->id);
        }


        $solution->teacher_id = Auth::User()->id;
        $solution->checked = Carbon::now();
        $solution->save();
        CourseActivity::recordSolutionChecked($solution);

        // Recalculate cached points for the student in this course
        CourseStudentPoints::recalculate($course_id, $solution->user_id);

        // Recalculate lesson stats for all lessons this student is enrolled in
        LessonStudentStats::recalculateForStudent($course_id, $solution->user_id);

        $solution->user->rescore();

        $when = \Carbon\Carbon::now()->addSeconds(1);
        Notification::send($solution->user, (new \App\Notifications\NewMark($solution))->delay($when));

        $solution->user->awardRankPromotionIfNeeded($old_rank);

        return redirect()->back();

    }

    public function payDeadlinePenalty($course_id, $id, $solution_id)
    {
        $solution = Solution::where('id', $solution_id)
            ->where('task_id', $id)
            ->where('course_id', $course_id)
            ->firstOrFail();
        $user = Auth::user();

        if ($solution->user_id != $user->id) {
            abort(403);
        }

        if (!$solution->hasActiveDeadlinePenalty()) {
            $this->make_info_alert('Штраф уже снят', 'У этого решения нет активного штрафа за дедлайн.');
            return redirect()->back();
        }

        $cost = $solution->deadlinePenaltyCost();

        if ($user->balance() < $cost) {
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

        if ($shouldRewardTaskPrice) {
            CoinTransaction::register($solution->user_id, $solution->task->price, "Task #" . $solution->task->id);
        }

        CourseStudentPoints::recalculate($course_id, $user->id);
        LessonStudentStats::recalculateForStudent($course_id, $user->id);

        $user->rescore();
        $user->awardRankPromotionIfNeeded($old_rank);

        $this->make_success_alert('Штраф снят', 'XP за решение пересчитан, со счета списано ' . $cost . ' GC.');

        return redirect()->back();
    }

    public function useXpBooster($course_id, $id, $solution_id)
    {
        $solution = Solution::where('id', $solution_id)
            ->where('task_id', $id)
            ->where('course_id', $course_id)
            ->firstOrFail();
        $user = Auth::user();

        if ($solution->user_id != $user->id) {
            abort(403);
        }

        if (!$solution->task->xp_booster_enabled) {
            $this->make_info_alert('Бустер недоступен', 'Для этой задачи нельзя применить XP-бустер.');
            return redirect()->back();
        }

        if ($solution->hasXpBooster()) {
            $this->make_info_alert('Бустер уже применен', 'К этому решению уже применяли XP-бустер.');
            return redirect()->back();
        }

        if ($solution->mark === null || $solution->mark >= $solution->task->max_mark) {
            $this->make_info_alert('Бустер не нужен', 'Это решение уже набрало максимум XP или еще не проверено.');
            return redirect()->back();
        }

        $cost = $solution->xpBoosterCost();

        if ($user->balance() < $cost) {
            $this->make_error_alert('Не хватает GC', 'Чтобы применить бустер, нужно ' . $cost . ' GC.');
            return redirect()->back();
        }

        $old_rank = $user->rank();
        $markBeforeBooster = $solution->mark;

        $solution->xp_booster_used_at = Carbon::now();
        $solution->applyDeadlinePenalty($solution->raw_mark === null ? $solution->mark : $solution->raw_mark, $solution->task->getDeadline($course_id));

        if ($solution->mark <= $markBeforeBooster) {
            $solution->xp_booster_used_at = null;
            $solution->xp_booster_amount = 0;
            $this->make_info_alert('Бустер не сработает', 'Бустер применяется до штрафа за дедлайн и не увеличит итоговый XP для этого решения.');
            return redirect()->back();
        }

        CoinTransaction::register($user->id, -1 * $cost, 'XP booster Solution #' . $solution->id);

        $solution->save();
        CourseActivity::recordXpBoosterUsed($solution, $cost, $solution->mark - $markBeforeBooster);

        CourseStudentPoints::recalculate($course_id, $user->id);
        LessonStudentStats::recalculateForStudent($course_id, $user->id);

        $user->rescore();
        $user->awardRankPromotionIfNeeded($old_rank);

        $this->make_success_alert('Бустер применен', 'Решение получило +' . ($solution->mark - $markBeforeBooster) . ' XP, со счета списано ' . $cost . ' GC.');

        return redirect()->back();
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

        $cost = GeekPasteClient::EXTRA_ATTEMPT_COST;
        if ($user->balance() < $cost) {
            $this->make_error_alert('Не хватает GC', 'Дополнительная попытка стоит ' . $cost . ' GC.');
            return redirect()->back();
        }

        $geekpaste = app(GeekPasteClient::class);
        if (!$geekpaste->canBuyExtraGptAttempt($user->id, $task->id, $course->id)) {
            $this->make_info_alert('Попытка пока не нужна', 'GeekPaste не подтвердил, что лимит GPT-сдач для этой задачи исчерпан.');
            return redirect()->back();
        }

        $result = $geekpaste->addExtraGptAttempt($user->id, $task->id, $course->id);
        if (!$result || !($result['extra_attempt_added'] ?? false)) {
            $this->make_error_alert('Не удалось добавить попытку', 'GeekPaste не подтвердил сброс лимита. GC не списаны.');
            return redirect()->back();
        }

        CoinTransaction::register($user->id, -1 * $cost, 'GeekPaste extra attempt Task #' . $task->id);
        CourseActivity::recordGeekPasteAttemptBought($task, $course, $user, $cost);

        $this->make_success_alert('Попытка добавлена', 'Можно отправить еще одно решение в GeekPaste. Со счета списано ' . $cost . ' GC.');

        return redirect('/insider/courses/' . $course_id . '/steps/' . $task->step_id . '#task' . $task->id);
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
        if (!$request->deadline)
        {
            \App\TaskDeadline::where('course_id', $course_id)->where('task_id', $id)->delete();
            return back();
        }
        $deadline = \App\TaskDeadline::all()->where('course_id', $course_id)->where('task_id', $id)->first();
        if ($deadline) {

            $deadline->expiration = $request->deadline;
            $deadline->penalty = $request->penalty;
            $deadline->save();
        } else {
            \App\TaskDeadline::create([
                "course_id" => $course_id,
                "task_id" => $id,
                "expiration" => $request->deadline,
                "penalty" => $request->penalty
            ]);
        }
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

    public function reviewTable($course_id, $id, Request $request)
    {
        $task = Task::findOrFail($id);
        $course = Course::findOrFail($course_id);
        # $solutions = $task->solutions;
        $students = $course->students->shuffle();
        $ids = [];

        for ($i = 0; $i < $students->count(); $i++) {
            $ids[$students[$i]->id] = $i;
            $students[$i]->works = collect([]);
        }
        for ($i = 0; $i < $students->count(); $i++) {
            $students[$i]->reviewer1 = $students[($i + 1) % $students->count()];
            $students[$i]->reviewer2 = $students[($i + 2) % $students->count()];

            try {
                $solution = $task->solutions->where('user_id', $students[$i]->id)->where('course_id', $course_id)->first();
                $students[$i]->solution = $solution->text;
                $students[$ids[$students[$i]->reviewer1->id]]->works->push($solution);
                $students[$ids[$students[$i]->reviewer2->id]]->works->push($solution);
            } catch (\Exception $e) {
                $students[$i]->solution = 'Нет';
            }


        }

        return view('reviewer.peer', compact('task', 'students', 'ids'));


    }

    public function recheckAllSolutions($course_id, $id)
    {
        $task = Task::findOrFail($id);
        $course = Course::findOrFail($course_id);

        if (!$task->is_code) {
            return redirect()->back()->with('error', 'Перепроверка доступна только для задач с кодом.');
        }

        // Get all unique students who have solutions for this task in this course
        $studentIds = Solution::where('task_id', $id)
            ->where('course_id', $course_id)
            ->pluck('user_id')
            ->unique();

        $recheckCount = 0;
        $client = new Client([
            'connect_timeout' => 2,
            'timeout' => 5,
        ]);

        foreach ($studentIds as $studentId) {
            // Zero out all solutions for this student/task/course
            Solution::where('task_id', $id)
                ->where('user_id', $studentId)
                ->where('course_id', $course_id)
                ->update([
                    'mark' => 0,
                    'raw_mark' => 0,
                    'deadline_penalty_amount' => 0,
                    'deadline_penalty_days' => 0,
                    'deadline_penalty_paid_at' => null,
                    'xp_booster_amount' => 0,
                    'xp_booster_used_at' => null,
                    'comment' => null,
                    'checked' => null
                ]);

            // Get the last solution for recheck
            $lastSolution = Solution::where('task_id', $id)
                ->where('user_id', $studentId)
                ->where('course_id', $course_id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastSolution && !empty($lastSolution->text)) {
                $codeId = $this->extractGeekPasteId($lastSolution->text);
                if ($codeId) {
                    try {

                        // Send recheck request to GeekPaste API
                        $response = $client->post(config('services.geekpaste_url') . '/recheck', [
                            'http_errors' => false,
                            'query' => ['id' => $codeId],
                        ]);

                        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                            $recheckCount++;
                        } else {
                            \Log::warning("GeekPaste recheck failed for student {$studentId}: HTTP " . $response->getStatusCode());
                        }
                    } catch (GuzzleException $e) {
                        // Log error but continue with other students
                        \Log::error("Failed to recheck solution for student {$studentId}: " . $e->getMessage());
                    }
                }
            }
        }

        return redirect()->back()->with('success', "Отправлено на перепроверку решений: {$recheckCount}");
    }

    private function extractGeekPasteId($text): ?string
    {
        if (preg_match('/(?:\\?|&amp;|&)id=([A-Za-z0-9_-]+)/', html_entity_decode($text), $matches)) {
            return $matches[1];
        }

        return null;
    }
}
