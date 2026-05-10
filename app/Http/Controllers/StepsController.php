<?php

namespace App\Http\Controllers;

use App\Course;
use App\CourseActivity;
use App\Achievement;
use App\ProgramStep;
use App\Http\Controllers\Controller;
use App\Lesson;
use App\Question;
use App\QuestionVariant;
use App\Solution;
use App\Services\GeekPasteClient;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Auth;

class StepsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('step')->except(['createView', 'create']);
        $this->middleware('teacher')->only(['editView', 'edit', 'makeLower', 'makeUpper', 'perform', 'delete']);

    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */

    public function details($course_id, $id)
    {
        $user = User::with('submissions')->findOrFail(Auth::User()->id);
        $course = Course::with('teachers', 'students')->findOrFail($course_id);
        $step = ProgramStep::with([
            'lesson.steps',
            'tasks.solutions' => function ($query) use ($course_id) {
                $query->where('course_id', $course_id);
            },
            'tasks.deadlines' => function ($query) use ($course_id) {
                $query->where('course_id', $course_id);
            },
            'tasks.blockedTasks' => function ($query) use ($course_id) {
                $query->where('course_id', $course_id);
            },
        ])->findOrFail($id);
        $tasks = [];
        \App\ActionLog::record(Auth::User()->id, 'step', $id);


        $tasks = $step->tasks->filter(function($task) use ($user, $course) {
            return $task->isVisible($user, $course);
        });

        $latestTaskAiSummaries = collect();
        $earnedTaskAchievements = collect();
        if ($tasks->isNotEmpty()) {
            $latestTaskAiSummaries = CourseActivity::with('user:id,name')
                ->where('course_id', $course->id)
                ->where('type', CourseActivity::TYPE_TASK_AI_SUMMARY)
                ->whereIn('task_id', $tasks->pluck('id')->values())
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->unique('task_id')
                ->keyBy('task_id');

            $earnedTaskAchievements = Achievement::query()
                ->where('course_id', $course->id)
                ->where('user_id', $user->id)
                ->where('status', Achievement::STATUS_PUBLISHED)
                ->whereIn('task_id', $tasks->pluck('id')->values())
                ->pluck('id', 'task_id');
        }

        $zero_theory = $step->theory == null || $step->theory == "";
        $one_tasker = $tasks->count() == 1 && $zero_theory;
        $empty = $zero_theory && $tasks->count() == 0;

        $quizer = true;
        foreach ($tasks as $task)
            if (!$task->is_quiz) $quizer = false;

        $quizer = $quizer && $zero_theory && !$empty;

        $geekpasteAttemptResetStatuses = [];
        $isStudent = $user->role == 'student' && !$course->teachers->contains('id', $user->id);
        if ($isStudent) {
            $geekpaste = app(GeekPasteClient::class);
            foreach ($tasks as $task) {
                if (!$task->is_code || $task->isBlocked($user->id, $course->id)) {
                    continue;
                }

                $status = $geekpaste->gptRateLimitStatus($user->id, $task->id, $course->id);
                if ($geekpaste->allowsExtraAttempt($status)) {
                    $geekpasteAttemptResetStatuses[$task->id] = $status;
                }
            }
        }

        return view('steps.details', compact('step', 'user', 'tasks', 'zero_theory', 'one_tasker', 'empty', 'quizer', 'course', 'geekpasteAttemptResetStatuses', 'latestTaskAiSummaries', 'earnedTaskAchievements'));
    }

    public function perform($course_id, $id)
    {
        $user = User::with('submissions')->findOrFail(Auth::User()->id);
        $step = ProgramStep::with([
            'lesson.steps',
            'tasks.solutions' => function ($query) use ($course_id) {
                $query->where('course_id', $course_id);
            },
            'tasks.blockedTasks' => function ($query) use ($course_id) {
                $query->where('course_id', $course_id);
            },
        ])->findOrFail($id);
        $course = Course::with('teachers', 'students')->findOrFail($course_id);
        $tasks = $step->tasks->filter(function($task) use ($user, $course) {
            return $task->isVisible($user, $course);
        });
        $zero_theory = $step->theory == null || $step->theory == "";
        $one_tasker = $tasks->count() == 1;
        $empty = $zero_theory && $tasks->count() == 0;
        return view('perform.details', compact('step', 'user', 'tasks', 'zero_theory', 'one_tasker', 'empty', 'course'));
    }

    public function createView($course_id, $id)
    {
        $is_lesson = false;
        $lesson = Lesson::findOrFail($id);
        $course = Course::findOrFail($course_id);
        return view('steps.create', compact('is_lesson', 'lesson', 'course'));
    }

    public function create($course_id, $id, Request $request)
    {
        $lesson = Lesson::findOrFail($id);
        $this->validate($request, [
            'name' => 'required|string',
        ]);
        $step = ProgramStep::createStep($lesson, $request);
        $step->video_url = $request->video_url;
        $step->save();

        return redirect('/insider/courses/' . $course_id . '/steps/' . $step->id);
    }


    public function editView($course_id, $id)
    {
        $step = ProgramStep::findOrFail($id);
        $course = Course::findOrFail($course_id);
        return view('steps.edit', compact('step', 'course'));
    }


    public function edit($course_id, $id, Request $request)
    {
        $step = ProgramStep::findOrFail($id);
        $this->validate($request, [
            'name' => 'required|string',
            'start_date' => 'date'
        ]);
        ProgramStep::editStep($step, $request);
        return redirect('/insider/courses/' . $course_id . '/steps/' . $step->id);
    }

    public function makeLower($course_id, $id, Request $request)
    {
        $step = ProgramStep::findOrFail($id);
        $step->sort_index -= 1;
        $step->save();
        return redirect('/insider/courses/' . $course_id . '/steps/' . $step->id);
    }

    public function makeUpper($course_id, $id, Request $request)
    {
        $step = ProgramStep::findOrFail($id);
        $step->sort_index += 1;
        $step->save();
        return redirect('/insider/courses/' . $course_id . '/steps/' . $step->id);
    }

    public function delete($course_id, $id)
    {
        $step = ProgramStep::findOrFail($id);
        $next = $step->nextStep();
        $pr = $step->previousStep();
        $lesson = $step->lesson;

        ProgramStep::where('id', $id)->delete();
        if ($pr != null) return redirect('/insider/courses/' . $course_id . '/steps/' . $pr->id);
        if ($next != null) return redirect('/insider/courses/' . $course_id . '/steps/' . $next->id);
        Lesson::where('id', $lesson->id)->delete();
        return redirect('/insider/courses/' . $course_id);
    }


}
