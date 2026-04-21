<?php

namespace App\Http\Controllers;

use App\BlockedTask;
use App\CoinTransaction;
use App\Course;
use App\Http\Requests\Tasks\EstimateTaskSolutionRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\SubmitTaskSolutionRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\ProgramStep;
use App\Http\Controllers\Controller;
use App\Solution;
use App\TaskDeadline;
use App\Task;
use App\User;
use App\Services\StudentProgressService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Support\Facades\DB;
use Notification;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

class TasksController extends Controller
{
    private StudentProgressService $progressService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(StudentProgressService $progressService)
    {
        $this->progressService = $progressService;
        $this->middleware('auth');
        $this->middleware('task');
        $this->middleware('teacher')->only([
            'create',
            'delete',
            'editForm',
            'edit',
            'reviewSolutions',
            'estimateSolution',
            'phantomSolution',
            'blockStudent',
            'unblockStudent',
            'makeLower',
            'makeUpper',
            'toNextTask',
            'toPreviousTask',
            'makeDeadline',
            'recheckAllSolutions',
            'reviewTable',
        ]);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */

    public function create($course_id, $id, StoreTaskRequest $request)
    {
        $step = ProgramStep::findOrFail($id);
        $order = 100;
        if ($step->tasks->count() != 0) {
            $order = $step->tasks->last()->sort_index + 1;
        }

        $price = $request->price ?? 0;

        $task = Task::create(['text' => $request->text, 'step_id' => $step->id, 'name' => $request->name, 'max_mark' => $request->max_mark, 'sort_index' => $order,
            'is_star' => $request->is_star == 'on' ? true : false,
            'is_hidden' => $request->is_hidden == 'on' ? true : false,
            'only_remote' => $request->only_remote == 'on' ? true : false,
            'only_class' => $request->only_class == 'on' ? true : false]);
        $task->solution = $request->solution;
        $task->price = $price;

        if ($request->has('answer') && $request->answer != "") {
            $task->is_quiz = true;
            $task->answer = $request->answer;
        } elseif ($request->has('is_code') && $request->is_code == 'on') {
            $task->is_code = true;
        }
        $task->save();

        if ($request->consequences)
            foreach ($request->consequences as $consequence_id) {
                $task->consequences()->attach($consequence_id);
            }

        // Recalculate points for all students after adding new task
        $course = Course::findOrFail($course_id);
        $this->progressService->dispatchCourseStudentsRecalculation($course_id, $course->students);

        return $this->redirectToTask($course_id, $step->id, $task->id);
    }

    public function delete($course_id, $id)
    {
        $task = Task::findOrFail($id);
        $step_id = $task->step_id;
        $task->delete();

        // Recalculate points for all students after deleting task
        $course = Course::findOrFail($course_id);
        $this->progressService->dispatchCourseStudentsRecalculation($course_id, $course->students);

        return redirect('/insider/courses/' . $course_id . '/steps/' . $step_id);
    }

    public function editForm($course_id, $id)
    {
        $task = Task::findOrFail($id);
        $course = Course::findOrFail($course_id);
        return view('steps.edit_task', compact('task'));
    }

    public function edit($course_id, $id, UpdateTaskRequest $request)
    {
        $task = Task::findOrFail($id);

        foreach ($task->consequences as $consequence) {
            $task->consequences()->detach($consequence->id);
        }
        if ($request->consequences != null)
            foreach ($request->consequences as $consequence_id) {
                $task->consequences()->attach($consequence_id);
            }

        $task->text = $request->text;
        $task->max_mark = $request->max_mark;
        $task->name = $request->name;
        $task->price = $request->price ?? 0;
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
        if ($request->has('answer') && $request->answer != "") {
            $task->is_quiz = true;
            $task->answer = $request->answer;
        } else {
            $task->is_quiz = false;
        }
        if ($request->has('is_code') && $request->is_code == "on") {
            $task->is_quiz = false;
            $task->is_code = true;
        } else {
            $task->is_code = false;
        }

        $task->save();

        // Recalculate points for all students after editing task
        $course = Course::findOrFail($course_id);
        $this->progressService->dispatchCourseStudentsRecalculation($course_id, $course->students);

        $step_id = $task->step_id;
        return $this->redirectToTask($course_id, $step_id, $id);
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
        $this->progressService->dispatchCourseStudentsRecalculation($course_id, $course->students);

        return $this->redirectToTask($course_id, $task->step->id, $id);
    }


    public function postSolution($course_id, $id, SubmitTaskSolutionRequest $request)
    {
        $task = Task::findOrFail($id);
        $user = Auth::user();

        // Blocked users cannot submit
        if ($task->isBlocked($user->id, $course_id)) {
            return [
                "mark" => 0,
                "comment" => "Задача заблокирована для вас. Обратитесь к преподавателю."
            ];
        }

        $course = Course::findOrFail($course_id);

        $solution = new Solution();
        $solution->task_id = $id;
        $solution->user_id = $user->id;
        $solution->course_id = $course_id;
        $solution->submitted = Carbon::now();
        $solution->text = clean($request->text);

        if ($task->is_quiz) {
            $old_rank = $user->rank();
            if ($task->answer == $request->text) {
                if ($task->price > 0 && !$task->isFullDone($user->id)) {
                    CoinTransaction::register($user->id, $task->price, "Task #" . $task->id);
                }

                $deadline = $task->getDeadline($course_id);

                if (!$deadline || Carbon::now()->lt($deadline->expiration->addDay())) {
                    $solution->mark = $task->max_mark;
                    $solution->comment = "Правильно.";
                } else {
                    $solution->mark = ceil($task->max_mark * $deadline->penalty);
                    $solution->comment = "Правильно. Сдано с опозданием.";
                }

            } else {
                $solution->mark = 0;
                $solution->comment = "Неверный ответ.";
            }

            if (count($course->Teachers) > 0) {
                $solution->teacher_id = $course->Teachers->first()->id;
            } else {
                $solution->teacher_id = 1;
            }
            $solution->checked = Carbon::now();
            $solution->save();

            // Recalculate cached points after auto-grading quiz
            $this->progressService->recalculateStudentProgress($course_id, $user->id);

            $user->rescore();
            $new_rank = $user->rank();
            if ($new_rank != $old_rank) {
                $when = \Carbon\Carbon::now()->addSeconds(1);
                Notification::send($user, (new \App\Notifications\NewRank())->delay($when));
            }
        }

        $solution->save();

        if (!$task->is_quiz && !$task->is_code) {
            $when = \Carbon\Carbon::now()->addSeconds(1);
            Notification::send($course->teachers, (new \App\Notifications\NewSolution($solution))->delay($when));
        }

        // If this is the first solution for a hidden task, recalculate cached points
        // because the task just became visible for this student
        if ($task->is_hidden && !$task->is_quiz) {
            $this->progressService->recalculateStudentProgress($course_id, $user->id);
        }


        return [
            "mark" => $solution->mark,
            "comment" => $solution->comment
        ];
    }

    public function askForRecheck($course_id, $id, $solution_id)
    {
        $solution = Solution::findOrFail($solution_id);

        if (!$solution->recheck_requested && $solution->task->is_code) {
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
        DB::transaction(function () use ($id, $student_id, $course_id) {
            if (!BlockedTask::where('task_id', $id)
                ->where('user_id', $student_id)
                ->where('course_id', $course_id)->exists()) {
                BlockedTask::create([
                    'task_id' => $id,
                    'user_id' => $student_id,
                    'course_id' => $course_id,
                    'blocked_at' => Carbon::now(),
                    'reason' => 'plagiarism',
                ]);
            }

            $solutions = Solution::where('task_id', $id)
                ->where('user_id', $student_id)
                ->where('course_id', $course_id)
                ->get();
            foreach ($solutions as $solution) {
                $solution->mark = 0;
                $solution->comment = 'Решение заблокировано (плагиат).';
                $solution->teacher_id = Auth::id();
                if ($solution->checked == null) {
                    $solution->checked = Carbon::now();
                }
                $solution->save();
            }
        });

        // Invalidate cached score
        $student->rescore();

        // Recalculate cached points for the student in this course
        $this->progressService->recalculateStudentProgress($course_id, $student_id);

        return redirect()->back();
    }

    public function unblockStudent($course_id, $id, $student_id)
    {
        DB::transaction(function () use ($id, $student_id, $course_id) {
            BlockedTask::where('task_id', $id)
                ->where('user_id', $student_id)
                ->where('course_id', $course_id)
                ->delete();
        });

        // Recalculate cached points for the student in this course
        $this->progressService->recalculateStudentProgress($course_id, $student_id);

        // Do not modify marks here; just allow new submissions
        return redirect()->back();
    }

    public function estimateSolution($course_id, $id, EstimateTaskSolutionRequest $request)
    {
        $solution = Solution::findOrFail($id);
        $old_rank = $solution->user->rank();

        DB::transaction(function () use ($solution, $request, $course_id) {
            $deadline = $solution->task->getDeadline($course_id);

            if (!$deadline || $solution->created_at->lt($deadline->expiration->addDay())) {
                $solution->mark = $request->mark;
                $solution->comment = $request->comment;
            } else {
                $solution->mark = ceil($request->mark * $deadline->penalty);
                $solution->comment = "Сдано с опозданием.\n\n" . $request->comment;
            }

            if ($solution->task->price > 0 && $solution->mark == $solution->task->max_mark && !$solution->task->isFullDone($solution->user_id)) {
                CoinTransaction::register($solution->user_id, $solution->task->price, "Task #" . $solution->task->id);
            }

            $solution->teacher_id = Auth::id();
            $solution->checked = Carbon::now();
            $solution->save();
        });

        $this->progressService->recalculateStudentProgress($course_id, $solution->user_id);

        $solution->user->rescore();
        $new_rank = $solution->user->rank();

        $when = \Carbon\Carbon::now()->addSeconds(1);
        Notification::send($solution->user, (new \App\Notifications\NewMark($solution))->delay($when));

        if ($new_rank != $old_rank) {
            $when = \Carbon\Carbon::now()->addSeconds(1);
            Notification::send($solution->user, (new \App\Notifications\NewRank())->delay($when));
        }

        return redirect()->back();

    }

    public function makeLower($course_id, $id, Request $request)
    {
        $task = Task::findOrFail($id);
        $task->sort_index -= 1;
        $task->save();
        return $this->redirectToTask($course_id, $task->step->id, $id);
    }

    public function makeDeadline($course_id, $id, Request $request)
    {
        if (!$request->deadline) {
            TaskDeadline::where('course_id', $course_id)->where('task_id', $id)->delete();
            return back();
        }
        $deadline = TaskDeadline::where('course_id', $course_id)->where('task_id', $id)->first();
        if ($deadline) {

            $deadline->expiration = $request->deadline;
            $deadline->penalty = $request->penalty;
            $deadline->save();
        } else {
            TaskDeadline::create([
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
        return $this->redirectToTask($course_id, $task->step->id, $id);
    }

    public function toNextTask($course_id, $id, Request $request)
    {
        $task = Task::findOrFail($id);
        $next = $task->step->nextStep();
        if ($next != null) {
            $task->step_id = $next->id;
            $task->save();
            return $this->redirectToTask($course_id, $next->id, $id);
        }

        return $this->redirectToTask($course_id, $task->step->id, $id);
    }

    public function toPreviousTask($course_id, $id, Request $request)
    {
        $task = Task::findOrFail($id);
        $previous = $task->step->previousStep();
        if ($previous != null) {
            $task->step_id = $previous->id;
            $task->save();
            return $this->redirectToTask($course_id, $previous->id, $id);
        }

        return $this->redirectToTask($course_id, $task->step->id, $id);
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
        $client = new Client();

        foreach ($studentIds as $studentId) {
            // Zero out all solutions for this student/task/course
            Solution::where('task_id', $id)
                ->where('user_id', $studentId)
                ->where('course_id', $course_id)
                ->update([
                    'mark' => 0,
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
                // Extract code ID from the solution text (assuming it's a GeekPaste URL)
                preg_match('/\?id=([^&\s]+)/', $lastSolution->text, $matches);
                if (isset($matches[1])) {
                    $codeId = $matches[1];
                    try {

                        // Send recheck request to GeekPaste API
                        $client->post(config('services.geekpaste_url') . '/recheck', [
                            'query' => ['id' => $codeId]
                        ]);

                        $recheckCount++;
                    } catch (GuzzleException $e) {
                        // Log error but continue with other students
                        \Log::error("Failed to recheck solution for student {$studentId}: " . $e->getMessage());
                    }
                }
            }
        }

        return redirect()->back()->with('success', "Отправлено на перепроверку решений: {$recheckCount}");
    }

    private function redirectToTask($courseId, $stepId, $taskId)
    {
        return redirect('/insider/courses/' . $courseId . '/steps/' . $stepId . '#task' . $taskId);
    }
}
