<?php

namespace App\Http\Controllers;

use App\CompletedPrograms;
use App\Programs;
use App\ProgramsStep;
use App\Http\Controllers\Controller;
use App\Program;
use App\Provider;
use App\User;
use App\Lesson;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Auth;

class ProgramsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = User::findOrFail(Auth::User()->id);
        $programs = Program::orderBy('id')->get();
        return view('programs.index', compact('programs', 'user'));
    }

    public function details($id)
    {
        $user = User::findOrFail(Auth::User()->id);
        $program = Program::findOrFail($id);;
        return view('programs.details', compact('program', 'user'));
    }

    public function createView()
    {
        return view('programs.create');
    }

    public function editView($id)
    {
        $user = User::findOrFail(Auth::User()->id);
        $program = Program::findOrFail($id);
        return view('programs.edit', compact('program', 'user'));
    }

    public function edit($id, Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required|string',
        ]);

        $program = Program::findOrFail($id);
        $program->name = $request->name;
        $program->description = clean($request->description);
        $program->save();
        return redirect('/insider/programs/' . $program->id);
    }

    public function create(Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);
        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required|string',
            'image' => 'image|max:1000'
        ]);
        $program = new Program();
        $program->name = $request->name;
        $program->description = clean($request->description);
        $program->save();

        $program->authors()->attach($user->id);
        return redirect('/insider/programs');
    }
}
