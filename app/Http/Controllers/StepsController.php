<?php

namespace App\Http\Controllers;

use App\Course;
use App\ProgramStep;
use App\Http\Controllers\Controller;
use App\Lesson;
use App\Question;
use App\QuestionVariant;
use App\Solution;
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
        $user  = User::findOrFail(Auth::User()->id);
        $course = Course::findOrFail($course_id);
        $step = ProgramStep::findOrFail($id);
        $tasks = [];
        \App\ActionLog::record(Auth::User()->id, 'step', $id);


        if ($user->role=='teacher' || $user->role=='admin')
        {
            $tasks = $step->tasks;
        }
        else {
            $student = $course->students->filter(function($value, $key) use ($user)  {
                return $value->id == $user->id;
            })->first();
            if ($student->pivot->is_remote)
            {
                $tasks = $step->remote_tasks;
            }
            else {
                $tasks = $step->class_tasks;
            }
        }

        $zero_theory = $step->theory == null || $step->theory == "";
        $one_tasker = $step->tasks->count() == 1 && $zero_theory;
        $empty = $zero_theory && $step->tasks->count() == 0;

        $quizer = true;
        foreach ($tasks as $task)
            if (!$task->is_quiz) $quizer = false;
        
        $quizer = $quizer && $zero_theory && !$empty;

        //Tasks Solutions
        $tasksSolutions = [];
        foreach ($tasks as $key => $task) {
            //Make a class
            $tasksSolutions[$key] = new \stdClass();
            //User solutions
            $tasksSolutions[$key]->userSolutions = $task->solutions->where('user_id', Auth::User()->id);
            //Shown solutions
            $tasksSolutions[$key]->shownSolutions = $tasksSolutions[$key]->userSolutions
            ->filter(function($sol) use ($tasksSolutions, $key) {
                return $sol->checked == null || $sol->mark == 0 || $sol->mark == $tasksSolutions[$key]->userSolutions->max('mark');
            });
            //Hidden solutions
            $tasksSolutions[$key]->hiddenSolutions =  $tasksSolutions[$key]->userSolutions
            ->filter(function($sol, $k) use ($tasksSolutions, $key){
                return !isset($tasksSolutions[$key]->shownSolutions[$k]);
            });
        }

            
        return view('steps.details', compact('tasksSolutions', 'step', 'user', 'tasks', 'zero_theory', 'one_tasker', 'empty', 'quizer', 'course'));
    }

    public function perform($course_id, $id)
    {
        $user  = User::findOrFail(Auth::User()->id);
        $step = ProgramStep::findOrFail($id);
        $course = Course::findOrFail($course_id);
        $tasks = $step->tasks;
        $zero_theory = $step->theory == null || $step->theory == "";
        $one_tasker = $step->tasks->count() == 1;
        $empty = $zero_theory && $step->tasks->count() == 0;
        return view('perform.details', compact('step', 'user', 'tasks','zero_theory', 'one_tasker', 'empty', 'course'));
    }

    public function createView($course_id, $id)
    {
        $is_lesson = false;
        $lesson = Lesson::findOrFail($id);
        return view('steps.create', compact('is_lesson', 'lesson'));
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

        return redirect('/insider/courses/'.$course_id.'/steps/' . $step->id);
    }



    public function editView($course_id, $id)
    {
        $step = ProgramStep::findOrFail($id);
        return view('steps.edit', compact('step'));
    }


    public function edit($course_id, $id, Request $request)
    {
        $step = ProgramStep::findOrFail($id);
        $this->validate($request, [
            'name' => 'required|string',
            'start_date' => 'date'
        ]);
        ProgramStep::editStep($step, $request);
        return redirect('/insider/courses/'.$course_id.'/steps/' . $step->id);
    }

    public function makeLower($course_id, $id, Request $request)
    {
        $step = ProgramStep::findOrFail($id);
        $step->sort_index -= 1;
        $step->save();
        return redirect('/insider/courses/'.$course_id.'/steps/' . $step->id);
    }
    public function makeUpper($course_id, $id, Request $request)
    {
        $step = ProgramStep::findOrFail($id);
        $step->sort_index += 1;
        $step->save();
        return redirect('/insider/courses/'.$course_id.'/steps/' . $step->id);
    }
    public function delete($course_id, $id)
    {
        $step = ProgramStep::findOrFail($id);
        $next = $step->nextStep();
        $pr = $step->previousStep();
        $lesson = $step->lesson;

        ProgramStep::where('id', $id)->delete();
        if ($pr != null) return redirect('/insider/courses/'.$course_id.'/steps/'.$pr->id);
        if ($next != null) return redirect('/insider/courses/'.$course_id.'/steps/'.$next->id);
        Lesson::where('id', $lesson->id)->delete();
        return redirect('/insider/courses/'.$course_id);
    }


}
