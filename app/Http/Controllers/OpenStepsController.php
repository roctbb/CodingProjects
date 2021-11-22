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

class OpenStepsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function __construct()
    {
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */

    public function details($id)
    {
        $step = ProgramStep::findOrFail($id);

        if (!$step->lesson->is_open) abort(503);

        $zero_theory = $step->theory == null || $step->theory == "";
        $one_tasker = $step->tasks->count() == 1;
        $empty = $zero_theory && $step->tasks->count() == 0;
        $tasks = $step->class_tasks;
        $quizer = false;

        return view('steps.details', compact('step', 'user', 'tasks', 'zero_theory', 'one_tasker', 'empty', 'quizer', 'tasks'));
    }




}
