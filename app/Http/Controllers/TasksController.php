<?php

namespace App\Http\Controllers;

use App\CoinTransaction;
use App\Course;
use App\ProgramStep;
use App\Http\Controllers\Controller;
use App\Question;
use App\QuestionVariant;
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
        $this->middleware('teacher')->only(['create', 'delete', 'editForm', 'edit', 'reviewSolutions', 'estimateSolution', 'phantomSolution']);
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
            'only_remote' => $request->only_remote == 'on' ? true : false,
            'only_class' => $request->only_class == 'on' ? true : false]);
        $task->solution = $request->solution;
        $task->price = $price;


        if ($request->has('answer') and $request->answer != "") {
            $task->is_quiz = true;
            $task->answer = $request->answer;
        } else if ($request->has('is_code') and $request->is_code == true) {
            $task->is_code = true;
        }
        $task->save();

        if ($request->consequences)
            foreach ($request->consequences as $consequence_id) {
                $task->consequences()->attach($consequence_id);
            }

        return redirect('/insider/courses/' . $course_id . '/steps/' . $step->id . '#task' . $task->id);
    }

    public function delete($course_id, $id)
    {
        $task = Task::findOrFail($id);
        $step_id = $task->step_id;
        $task->delete();
        return redirect('/insider/courses/' . $course_id . '/steps/' . $step_id);
    }

    public function editForm($course_id, $id)
    {
        $task = Task::findOrFail($id);
        $course = Course::findOrFail($course_id);
        return view('steps.edit_task', compact('task'));
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
        if (!$request->has('price')) $request->price = 0;
        $task->price = $request->price;
        $task->solution = $request->solution;
        if ($request->is_star == 'on') {
            $task->is_star = true;
        } else {
            $task->is_star = false;
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
        if ($request->has('code_answer') and $request->code_answer != "") {
            $task->is_quiz = false;
            $task->is_code = true;
            $task->checker = $request->checker;
            $task->code_answer = $request->code_answer;
            $task->code_input = $request->code_input;
            $task->template = $request->template;
        } else {
            $task->is_code = false;
        }

        $task->save();

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

        return redirect('/insider/courses/' . $course_id . '/steps/' . $task->step->id . '#task' . $id);
    }


    public function postSolution($course_id, $id, Request $request)
    {
        $task = Task::findOrFail($id);
        $user = User::findOrFail(Auth::User()->id);
        $this->validate($request, [
            'text' => 'required|string',
        ]);

        $step_id = $task->step_id;
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
                if ($task->price > 0 and !$task->isFullDone(Auth::User()->id)) {
                    CoinTransaction::register(Auth::User()->id, $task->price, "Task #" . $task->id);
                }

                $deadline = $task->getDeadline($course_id);

                if (!$deadline or Carbon::now()->lt($deadline->expiration->addDay())) {
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
            $user->rescore();
            $new_rank = $user->rank();
            if ($new_rank != $old_rank) {
                $when = \Carbon\Carbon::now()->addSeconds(1);
                Notification::send($user, (new \App\Notifications\NewRank())->delay($when));
            }
        }
        if ($task->is_code) {
            $solution->text = $request->text;

            $client = new Client();
            $res = $client->post('https://checker.geekclass.ru', ['form_params' => ['pswd' => 'GEEK_PSWD_{{}}', 'input' => $task->code_input, 'code' => $solution->text, 'checker' => $task->checker]]);
            if ($res->getStatusCode() != 200) {
                $solution->mark = 0;
                $solution->comment = "Ошибка проверки, попробуйте еще раз чуть позже..";
            }
            $data = \GuzzleHttp\json_decode($res->getBody());
            if ($data->state == 'general error') {
                $solution->mark = 0;
                $solution->comment = "Ошибка проверки, попробуйте еще раз чуть позже.";
            }
            if ($data->state == 'error') {
                $solution->mark = 0;
                $solution->comment = "Ошибка в программе:<br><br>" . nl2br($data->error);
            }
            if ($data->state == 'success') {
                if (str_contains($data->result, $task->code_answer)) {
                    $deadline = $this->getDeadline($course_id);

                    if (!$deadline or $solution->created_at->lt($deadline->expiration->addDay())) {
                        $solution->mark = $task->max_mark;
                        $solution->comment = "Правильно.";
                    } else {
                        $solution->mark = ceil($task->max_mark * $deadline->penalty);
                        $solution->comment = "Правильно. Сдано с опозданием.";
                    }

                } else {
                    $solution->mark = 0;
                    $solution->comment = "Неверное решение.";
                }
            }
            $solution->teacher_id = $task->Step->Course->Teachers->first()->id;
            $solution->checked = Carbon::now();

        }

        $solution->save();

        if (!$task->is_quiz && !$task->is_code) {
            $when = \Carbon\Carbon::now()->addSeconds(1);
            Notification::send($course->teachers, (new \App\Notifications\NewSolution($solution))->delay($when));
        }


        return [
            "mark" => $solution->mark,
            "comment" => $solution->comment
        ];
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

    public function estimateSolution($course_id, $id, Request $request)
    {
        $solution = Solution::findOrFail($id);
        $this->validate($request, [
            'mark' => 'required|integer|min:0|max:' . $solution->task->max_mark
        ]);

        $old_rank = $solution->user->rank();
        $deadline = $solution->task->getDeadline($course_id);

        if (!$deadline or $solution->created_at->lt($deadline->expiration->addDay())) {
            $solution->mark = $request->mark;
            $solution->comment = $request->comment;
        } else {
            $solution->mark = ceil($request->mark * $deadline->penalty);
            $solution->comment = "Сдано с опозданием.\n\n" . $request->comment;
        }

        if ($solution->task->price > 0 and $solution->mark == $solution->task->max_mark and !$solution->task->isFullDone($solution->user_id)) {
            CoinTransaction::register($solution->user_id, $solution->task->price, "Task #" . $solution->task->id);
        }


        $solution->teacher_id = Auth::User()->id;
        $solution->checked = Carbon::now();
        $solution->save();

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
        // dd($deadline);
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
}
