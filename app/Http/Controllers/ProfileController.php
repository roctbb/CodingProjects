<?php

namespace App\Http\Controllers;

use App\CoinTransaction;
use App\CompletedCourse;
use App\Course;
use App\ProgramStep;
use App\Http\Controllers\Controller;
use App\User;
use App\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Auth;
use App\Project;


class ProfileController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('self')->except(['index', 'details', 'project', 'editProject']);
        $this->middleware('teacher')->only(['addMoney']);
        $this->middleware('admin')->only(['deleteCourse', 'course', 'deleteCurrentCourse', 'addMoney']);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = User::with('submissions', 'posts', 'games', 'completedCourses')->orderBy('name')->get();
        return view('profile.index', compact('users'));
    }

    public function details($id = null)
    {
        $guest = User::findOrFail(Auth::User()->id);
        $user = null;
        if ($id == null) {
            $user = $guest;
        } else {
            $user = User::findOrFail($id);
        }

    // Use cached sticker retrieval and descriptions
    $stickers = $user->getStickers();
    $sticker_description = $user->getStickerDescriptions();


        $projects = $user->projects();
        $events = Event::all();
        return view('profile.details', compact('user', 'guest', 'projects', 'events', 'stickers', 'sticker_description'));
    }

    public function editView($id)
    {
        $guest = User::findOrFail(Auth::User()->id);
        $user = User::findOrFail($id);
        $projects = $user->projects();
        return view('profile.edit', compact('user', 'guest', 'projects'));
    }

    public function deleteCourse($id)
    {
        $course = CompletedCourse::findOrFail($id);
        $course->delete();
        return redirect()->back();
    }

    public function deleteCurrentCourse($user_id, $course_id)
    {
        $user = User::findOrFail($user_id);
        $user->courses()->detach($course_id);
        return redirect()->back();
    }

    public function course($id, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);
        $this->validate($request, [
            'name' => 'required|string',
            'provider' => 'required|string',
            'mark' => 'required|string',
        ]);
        $course = new CompletedCourse();
        $course->name = $request->name;
        $course->mark = $request->mark;
        $course->user_id = $id;
        $course->provider = $request->provider;
        if (str_contains(mb_strtolower($course->provider), 'goto')) {
            $course->provider = 'GoTo';
            $course->class = 'danger';
        } else if (str_contains(mb_strtolower($course->provider), 'geekon')) {
            $course->provider = 'GeekON-School';
            $course->class = 'success';
        } else if (str_contains(mb_strtolower($course->provider), 'геккон')) {
            $course->provider = 'Геккон-клуб';
            $course->class = 'info';
        } else if (str_contains(mb_strtolower($course->provider), 'polymus')) {
            $course->provider = 'Политехнический музей';
            $course->class = 'primary';
        } else if (str_contains(mb_strtolower($course->provider), 'алгоритмика')) {
            $course->provider = 'Алгоритмика';
            $course->class = 'warning';
        }
        $course->save();
        return redirect()->back();
    }

    public function addMoney($id, Request $request)
    {
        $this->validate($request, [
            'description' => 'required|string',
            'amount' => 'integer|min:-100|max:100|required'
        ]);

        CoinTransaction::register($id, $request->amount, clean($request->description));

        $this->make_success_alert('Успех!', 'Деньги начислены.');

        return redirect()->back();
    }

    public function edit($id, Request $request)
    {
        $guest = User::findOrFail(Auth::User()->id);
        $user = User::findOrFail($id);

        $validationRules = [
            'name' => 'required|string',
            'school' => 'required|string',
            'grade' => 'integer|min:1|max:11|required',
            'hobbies' => 'required|string',
            'interests' => 'required|string',
            'image' => 'image|max:4000'
        ];

        // Добавляем валидацию для роли, если пользователь - админ
        if (Auth::User()->role == 'admin') {
            $validationRules['role'] = 'required|in:student,teacher,theme_moderator,admin';
        }

        $this->validate($request, $validationRules);

        $user->name = $request->name;
        $user->vk = $request->vk;
        $user->git = $request->git;
        $user->facebook = $request->facebook;
        $user->telegram = $request->telegram;
        $user->hobbies = $request->hobbies;
        $user->interests = $request->interests;
        $user->school = $request->school;
        if (Auth::User()->role == 'teacher' || Auth::User()->role == 'admin') {
            if ($request->birthday) {
                $user->birthday = Carbon::createFromFormat('Y-m-d', $request->birthday);
            }
        }
        $user->setGrade($request->grade);

        if ($request->password != "") {
            $this->validate($request, ['password' => 'required|string|min:6|confirmed']);
            $user->password = bcrypt($request->password);
        }

        if ($request->hasFile('image')) {
            $extn = '.' . $request->file('image')->guessClientExtension();
            $path = $request->file('image')->storeAs('user_avatars', $user->id . $extn);
            $user->image = $path;
        }

        if ($guest->role == 'teacher')
            $user->comments = $request->comments;

        // Назначение роли (только для админов)
        if (Auth::User()->role == 'admin') {
            $user->role = $request->role;
        }

        $user->save();

        return redirect('/insider/profile/' . $id);
    }
    #TODO do i need this?
    /*public function project ($id, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);
        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required|string',
            'type' => 'required|string',
            'url' => 'required|string',
        ]);
        $project = new Project();
        $project->name = $request->name;
        $project->description = clean($request->description);
        $project->type = $request->type;
        $project->url = $request->url;

        return redirect()->back();

    }*/
}
