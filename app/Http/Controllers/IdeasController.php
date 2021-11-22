<?php
/**
 * Created by PhpStorm.
 * User: AlexNerru
 * Date: 03.09.2017
 * Time: 23:22
 */

namespace App\Http\Controllers;

use App\CoinTransaction;
use App\CoreNode;
use App\Idea;
use App\Http\Controllers\Controller;
use App\Notifications\IdeaApproved;
use App\Notifications\IdeaDeclined;
use App\Notifications\NewIdea;
use App\User;
use Illuminate\Http\Request;
use Auth;
use Carbon\Carbon;


class IdeasController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('idea')->except(['index', 'details', 'createView', 'create', 'approve', 'decline']);
        $this->middleware('teacher')->only(['approve', 'decline']);
    }

    public function index()
    {
        $user = User::findOrFail(Auth::User()->id);
        $approved_ideas = Idea::where('is_approved', true)->get();
        $approved_ideas = $approved_ideas->groupBy(function ($item, $key) {
            return mb_substr($item->name, 0, 1);     //treats the name string as an array
        })->sortBy(function ($item, $key) {      //sorts A-Z at the top level
            return $key;
        });

        if ($user->role != 'student') {
            $draft_ideas = Idea::where('is_approved', false)->get();
        } else {
            $draft_ideas = Idea::where('is_approved', false)->where('author_id', $user->id)->get();
        }

        $draft_ideas = $draft_ideas->groupBy(function ($item, $key) {
            return mb_substr($item->name, 0, 1);     //treats the name string as an array
        })->sortBy(function ($item, $key) {      //sorts A-Z at the top level
            return $key;
        });
        return view('ideas.index', compact('approved_ideas', 'draft_ideas', 'user'));
    }

    public function details($id)
    {
        $user = User::findOrFail(Auth::User()->id);
        $idea = Idea::findOrFail($id);
        $author = $idea->author();
        return view('ideas.details', compact('idea', 'user', 'author'));
    }

    public function createView()
    {
        return view('ideas.create');
    }

    public function editView($id)
    {
        $idea = Idea::findOrFail($id);
        $user = User::findOrFail(Auth::User()->id);
        return view('ideas.edit', compact('idea', 'user'));
    }

    public function edit($id, Request $request)
    {

        $this->validate($request, [
            'name' => 'required|string',
            'short_description' => 'nullable|string',
            'description' => 'required|string',
            'sdl_node_id' => 'nullable|exists:core_nodes,id'
        ]);
        $user = User::findOrFail(Auth::User()->id);

        $idea = Idea::findOrFail($id);
        $idea->name = $request->name;
        $idea->description = clean($request->description);
        $idea->short_description = clean($request->short_description);
        if ($user->role != 'student' and $request->has('sdl_node_id'))
        {
            $idea->sdl_node_id = $request->sdl_node_id;
        }
        $idea->save();


        return redirect('/insider/ideas/' . $idea->id);
    }

    public function create(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'short_description' => 'nullable|string',
            'description' => 'required|string',
            'g-recaptcha-response' => app('App\Services\Recaptcha')->getValidationString()

        ]);
        $user = User::findOrFail(Auth::User()->id);
        $idea = new Idea();
        $idea->name = $request->name;
        $idea->description = clean($request->description);
        $idea->short_description = clean($request->short_description);
        $idea->author_id = $user->id;
        if ($user->role == 'teacher') {
            $idea->is_approved = true;
        }
        $idea->save();

        if ($user->role == 'student') {
            $teachers = User::where('role', 'admin')->get();
            $when = Carbon::now()->addSeconds(1);
            foreach ($teachers as $teacher) {
                $teacher->notify((new NewIdea($idea))->delay($when));
            }
        }


        return redirect('/insider/ideas/' . $idea->id);
    }

    public function delete($id)
    {
        $idea = Idea::findOrFail($id);
        $idea->delete();
        return redirect('/insider/ideas/');
    }

    public function approve($id)
    {
        $idea = Idea::findOrFail($id);
        $idea->is_approved = true;
        $idea->reviewer_id = Auth::User()->id;
        $idea->save();

        $when = Carbon::now()->addSeconds(1);
        $idea->author->notify((new IdeaApproved($idea))->delay($when));

        CoinTransaction::register($idea->author->id, 5, "Idea #" . $idea->id);
        return redirect('/insider/ideas/');
    }

    public function decline($id)
    {
        $idea = Idea::findOrFail($id);

        $when = Carbon::now()->addSeconds(1);
        $idea->author->notify((new IdeaDeclined($idea))->delay($when));

        return redirect('/insider/ideas/');
    }
}
