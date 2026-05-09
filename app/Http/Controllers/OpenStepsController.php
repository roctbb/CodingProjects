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
        $step = ProgramStep::with('tasks')->findOrFail($id);

        if (!$step->lesson->is_open) abort(503);

        $zero_theory = $step->theory == null || $step->theory == "";
        $tasks = $step->tasks->where('is_hidden', false);
        $one_tasker = $tasks->count() == 1;
        $empty = $zero_theory && $tasks->count() == 0;
        $quizer = false;
        $course = null;
        $user = Auth::user();

        return view('steps.details', compact('step', 'tasks', 'zero_theory', 'one_tasker', 'empty', 'quizer', 'course', 'user'));
    }




}
