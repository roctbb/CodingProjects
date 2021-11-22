<?php
/**
 * Created by PhpStorm.
 * User: AlexNerru
 * Date: 03.09.2017
 * Time: 23:22
 */

namespace App\Http\Controllers;

use App\Course;
use App\Project;
use App\Http\Controllers\Controller;
use App\Solution;
use App\Task;
use App\User;
use Illuminate\Http\Request;
use Auth;
use Carbon\Carbon;


class ProjectsController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('project')->only(['editView', 'editProject', 'delete']);
    }

    public function index()
    {
        $user = User::findOrFail(Auth::User()->id);
        $projects = Project::all();
        return view('projects.index', compact('projects', 'user'));
    }

    public function details($id)
    {
        $user = User::findOrFail(Auth::User()->id);
        $project = Project::findOrFail($id);
        $author = $project->author();
        $guest_projects = $user->projects;
        $is_in_project = $project->team->contains($user);
        $is_author = $author->get() == Auth::User();
        $tags = explode(" ", $project->tags);
        return view('projects.details', compact('project', 'user', 'is_in_project', 'is_author', 'tags'));
    }

    public function createView()
    {
        $user = User::findOrFail(Auth::User()->id);
        return view('projects.create', compact('user'));
    }

    public function editView($id)
    {
        $user = User::findOrFail(Auth::User()->id);
        $project = Project::findOrFail($id);
        return view('projects.edit', compact('project', 'user'));
    }

    public function edit($id, Request $request)
    {


        $this->validate($request, [
            'name' => 'required|string',
            'short_description' => 'required|string',
            'description' => 'nullable|string',
            'type' => 'nullable|string',
            'url' => 'nullable|string',
            'tags' => 'nullable|string',
            'image' => 'image|max:3000',
        ]);


        $project = Project::findOrFail($id);
        $user = User::findOrFail(Auth::User()->id);
        // if (!$project->team->contains($user)) abort(503);

        if ($request->idea !== null)
        {
            if ($project->basedOn->count() !== 0)
            {
                $project->basedOn->first()->idea_id = $request->idea;
                $project->basedOn->first()->save();
            }
            else
            {
                \App\ProjectIdea::create([
                    "project_id" => $project->id,
                    "idea_id" => $request->idea
                ]);
            }
        }
        else 
        {
            $project->basedOn->first()->delete();
        }
        

        foreach ($project->team as $member) {
            if ($member == $project->author) continue;
            $project->team()->detach($member->id);
        }
        if ($request->team != null)
            foreach ($request->team as $member_id) {
                $project->team()->attach($member_id);
            }

        if ($request['task'] != "") {
            $parts = explode('_', $request->task);
            $task_id = $parts[1];
            $course_id = $parts[0];

            $task = Task::findOrFail($task_id);
            $course = Course::findOrFail($course_id);

            if (!$course->students->contains($user)) abort(503);
            $project->task_id = $task_id;
            $project->course_id = $course_id;
            $project->save();
            # TODO: уведомление преподавателю
        }
        else
        {
            $project->task_id = NULL;
            $project->course_id = NULL;
            $project->save();
        }


        return redirect('/insider/projects/' . $project->id);
    }

    public function create(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'short_description' => 'required|string'

        ]);

        $user = User::findOrFail(Auth::User()->id);
        $project = Project::createProject($request);
        $project->team()->attach($user->id);
        
        if ($request->idea !== null)
        {
            \App\ProjectIdea::create([
                "project_id" => $project->id,
                "idea_id" => $request->idea
            ]);
        }

        if ($request['task'] != "") {
            $parts = explode('_', $request->task);
            $task_id = $parts[1];
            $course_id = $parts[0];

            $task = Task::findOrFail($task_id);
            $course = Course::findOrFail($course_id);

            if (!$course->students->contains($user)) abort(503);
            $project->task_id = $task_id;
            $project->course_id = $course_id;
            $project->save();
            # TODO: уведомление преподавателю
        }

        if ($request->team != null)
            foreach ($request->team as $member_id) {
                $project->team()->attach($member_id);
            }


        return redirect('/insider/projects/' . $project->id);
    }

    public function deleteProject($id)
    {
        $user = User::findOrFail(Auth::User()->id);
        $project = Project::findOrFail($id);
        if (!$project->team->contains($user)) abort(503);
        $project->delete();
        return redirect('/insider/profile/');
    }

    public function review($id, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);
        $project = Project::findOrFail($id);
        if ($user->role == 'student') abort(503);
        if ($project->task == null) abort(503);

        $this->validate($request, [
            'mark' => 'required|integer|min:0|max:' . $project->task->max_mark
        ]);

        $when = \Carbon\Carbon::now()->addSeconds(1);

        foreach ($project->team as $member) {
            $solution = new Solution();
            $solution->task_id = $project->task_id;
            $solution->user_id = $member->id;
            $solution->course_id = $project->course_id;
            $solution->submitted = Carbon::now();
            $solution->text = url('/insider/projects/' . $project->id);
            $solution->mark = $request->mark;
            $solution->comment = $request->comment;
            $solution->teacher_id = Auth::User()->id;
            $solution->checked = Carbon::now();
            $solution->save();

            \Notification::send($solution->user, (new \App\Notifications\NewMark($solution))->delay($when));
        }

        return redirect('/insider/projects/' . $project->id);

    }

    public function ask_review($id, Request $request)
    {
        $project = Project::findOrFail($id);
        if ($project->task == null) abort(503);

        $when = \Carbon\Carbon::now()->addSeconds(1);

        foreach ($project->task_course->teachers as $teacher) {
            \Notification::send($teacher, (new \App\Notifications\NewProjectSolution($project))->delay($when));
        }
        \Session::flash('message', 'Уведомления отправлены!');
        return redirect('/insider/projects/' . $project->id);

    }

    public function rewardView($id)
    {
        $project = \App\Project::findOrFail($id);
        return view('projects.reward', ['project' => $project]);
    }
    public function reward($id, Request $request)
    {
        $project = \App\Project::findOrFail($id);
        if ($project->author_ !== null)
        {
            if (\Auth::user()->id === $project->author_->id && \Auth::user()->role !== "admin") abort(403);

            $messages = [
                'reward.min' => 'Минимальная сумма награды - 1 GeekCoin',
                'reward.max' => 'На вашем балансе не хватает GeekCoin',
                'comment.min' => 'Комментарий обязятелен'
            ];
            $request->validate([
                'reward' => 'numeric|min:1' . (\Auth::user()->is_teacher ? '' : '|max:' . \Auth::user()->balance()),
                'comment' => 'min:1'
            ], $messages);

            
            \App\ProjectAward::register($project->author_->id, $id, $request->reward, $request->comment);
        }
        return redirect('/insider/projects/' . $project->id);
    
    }
}

