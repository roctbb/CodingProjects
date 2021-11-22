<?php

namespace App\Http\Controllers;

use App\CompletedCourse;
use App\Course;
use App\EducationalResult;
use App\MarketDeal;
use App\MarketGood;
use App\Notifications\NewOrder;
use App\Program;
use App\ProgramStep;
use App\Http\Controllers\Controller;
use App\Provider;
use App\ResultScale;
use App\Task;
use App\User;
use App\Lesson;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Auth;

class ScalesController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('admin');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $scales = ResultScale::all();
        return view('scales.index', compact('scales'));
    }

    public function details($id)
    {
        $scale = ResultScale::findOrFail($id);
        return view('scales.details', compact('scale'));
    }


    public function createView()
    {
        return view('scales.create');
    }

    public function editView($id)
    {
        $scale = ResultScale::findOrFail($id);
        return view('scales.edit', compact('scale'));
    }

    public function edit($id, Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $scale = ResultScale::findOrFail($id);
        $scale->name = $request->name;
        $scale->description = clean($request->description);
        $scale->save();
        return redirect('/insider/scales/');
    }

    public function create(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $scale = new ResultScale();
        $scale->name = $request->name;
        $scale->description = clean($request->description);
        $scale->save();
        return redirect('/insider/scales/');
    }

    public function createResultView($id, Request $request)
    {
        $scale = ResultScale::findOrFail($id);
        return view('scales.create_result', compact('scale'));
    }

    public function createResult($id, Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'nullable|string',
            'level' => 'required|integer',
        ]);

        $scale = ResultScale::findOrFail($id);

        $result = new EducationalResult();
        $result->name = $request->name;
        $result->description = clean($request->description);
        $result->level = $request->level;
        $result->scale_id = $scale->id;
        $result->save();
        return redirect('/insider/scales/' . $scale->id);
    }

    public function editResultView($id, $result_id, Request $request)
    {
        $scale = ResultScale::findOrFail($id);
        $result = EducationalResult::findOrFail($result_id);

        return view('scales.edit_result', compact('scale', 'result'));
    }

    public function editResult($id, $result_id, Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'nullable|string',
            'level' => 'required|integer',
        ]);

        $scale = ResultScale::findOrFail($id);
        $result = EducationalResult::findOrFail($result_id);
        $result->name = $request->name;
        $result->description = clean($request->description);
        $result->level = $request->level;
        $result->save();
        return redirect('/insider/scales/' . $scale->id);
    }

    public function deleteResult($id, $result_id, Request $request)
    {
        $scale = ResultScale::findOrFail($id);
        $result = EducationalResult::findOrFail($result_id);
        $result->delete();
        return redirect('/insider/scales/' . $scale->id);
    }

    public function createTaskForm($id, $result_id)
    {
        $scale = ResultScale::findOrFail($id);
        $result = EducationalResult::findOrFail($result_id);
        return view('scales.create_task', compact('result', 'scale'));
    }

    public function createTask($id, $result_id, Request $request)
    {
        $scale = ResultScale::findOrFail($id);
        $result = EducationalResult::findOrFail($result_id);
        $this->validate($request, [
            'text' => 'required|string',
            'name' => 'required|string',
            'solution' => 'required_if:is_demo,yes'
        ]);

        $task = new Task();
        $task->text = clean($request->text);
        $task->name = $request->name;

        if ($request->is_demo == "yes") {
            $task->is_demo = true;
            $task->solution = $request->solution;
        } else {
            $task->is_demo = false;
        }

        if ($request->is_training == "yes") {
            $task->is_training = true;
        } else {
            $task->is_training = false;
        }
        $task->result_id = $result_id;
        $task->save();
        return redirect('/insider/scales/' . $scale->id);
    }

    public function deleteTask($id, $result_id, $task_id)
    {
        $task = Task::findOrFail($task_id);
        $task->delete();
        return redirect('/insider/scales/' . $id);
    }

    public function editTaskForm($id, $result_id, $task_id)
    {
        $task = Task::findOrFail($task_id);
        $scale = ResultScale::findOrFail($id);
        $result = EducationalResult::findOrFail($result_id);
        return view('scales.edit_task', compact('task', 'result', 'scale'));
    }

    public function editTask($id, $result_id, $task_id, Request $request)
    {
        $scale = ResultScale::findOrFail($id);
        $result = EducationalResult::findOrFail($result_id);
        $task = Task::findOrFail($task_id);
        $this->validate($request, [
            'text' => 'required|string',
            'name' => 'required|string',
            'solution' => 'required_if:is_demo,yes'
        ]);
        $task->text = clean($request->text);
        $task->name = $request->name;

        if ($request->is_demo == "yes") {
            $task->is_demo = true;
            $task->solution = $request->solution;
        } else {
            $task->is_demo = false;
        }

        if ($request->is_training == "yes") {
            $task->is_training = true;
        } else {
            $task->is_training = false;
        }

        $task->save();

        return redirect('/insider/scales/' . $scale->id);
    }


}
