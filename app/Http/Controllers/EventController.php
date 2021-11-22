<?php

namespace App\Http\Controllers;

use App\CoinTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Event;
use App\User;
use App\Tags;
use App\EventComments;

class EventController extends Controller
{
    public function current_event($id)
    {
        $event = Event::findOrFail($id);
        $user = User::all();
        $comments = EventComments::all();
        return view('/events/details', ['event' => $event, 'users' => $user, 'comments' => $comments]);
    }

    public function add_event_view()
    {
        return view('/events/add_event_view');
    }

    public function event_view(Request $request)
    {
        $events = Event::getNew();
        $old_events = Event::getOld();

        return view('/events/index', ['old_events' => $old_events, 'events' => $events]);
    }

    public function prize_view($id, Request $request)
    {
        if (\Auth::user()->role != 'admin' and \Auth::user()->role != 'teacher') abort(403);
        $event = Event::findOrFail($id);
        if ($event->coins_delivered) abort(403);
        return view('/events/coins', ['event' => $event]);
    }

    public function prize($id, Request $request)
    {
        if (\Auth::user()->role != 'admin' and \Auth::user()->role != 'teacher') abort(403);
        $event = Event::findOrFail($id);
        if ($event->coins_delivered) abort(403);

        foreach ($event->userOrgs as $user) {
            if ($request->has('prize' . $user->id) and is_numeric($request->get('prize' . $user->id))) {
                $coins = intval($request->get('prize' . $user->id));
                CoinTransaction::register($user->id, $coins, "Event #" . $event->id);
            }
        }

        foreach ($event->participants as $user) {
            if (!$event->userOrgs->contains($user)) {
                if ($request->has('prize' . $user->id) and is_numeric($request->get('prize' . $user->id))) {
                    $coins = intval($request->get('prize' . $user->id));
                    CoinTransaction::register($user->id, $coins, "Event #" . $event->id);
                }
            }
        }

        $event->coins_delivered = true;
        $event->save();

        return redirect('/insider/events/');
    }

    public function add_event(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'text' => 'required|string',
            'short_text' => 'required|string',
            'date' => 'required|date|date_format:Y-m-d',
            'time' => 'required|date_format:H:i',
        ]);
        $event = new Event();
        $event->name = $request->name;
        $event->text = clean($request->text);
        $event->date = $request->date;
        $time = explode(':', $request->time);
        $event->date = $event->date->setTime($time[0], $time[1]);
        $event->location = $request->location;
        $event->type = $request->type;
        $event->short_text = $request->short_text;
        $event->max_people = $request->max_people;
        $event->skills = $request->skills;
        $event->site = $request->site;
        $event->save();
        $event->userOrgs()->attach(Auth::User()->id);

        return redirect('/insider/events/' . $event->id);
    }

    public function del_event($id)
    {
        $event = Event::findOrFail($id);
        if (Auth::User()->role == 'admin' or Auth::User()->role == 'teacher' or $event->userOrgs->contains(Auth::User()->id)) {
            $event->delete();
        }
        return redirect('/insider/events');
    }

    public function go_event($id)

    {
        $event = Event::findOrFail($id);
        if ($event->participants()->where('id', Auth::User()->id)->count() == 0) {
            $event->participants()->attach(Auth::User()->id);
        }
        return redirect('/insider/events/' . $id);
    }

    public function left_event($id)
    {
        $event = Event::findOrFail($id);
        $event->participants()->detach(Auth::User()->id);
        return redirect('/insider/events/' . $id);
    }

    public function del_comment($id, $id2)
    {
        $comment = EventComments::findOrFail($id2);
        if ($comment->user_id == Auth::User()->id) {
            $comment->delete();
        }
        return redirect('/insider/events/' . $id);
    }

    public function like_event($id, Request $request)
    {
        $event = Event::findOrFail($id);

        // if ($event->user->id == \Auth::id()) abort(403);

        $event->vote(1);

        return redirect('/insider/events/' . $id);
    }

    public function dislike_event($id, Request $request)
    {
        $event = Event::findOrFail($id);

        // if ($event->user->id == \Auth::id()) abort(403);

        $event->vote(-1);

        return redirect('/insider/events/' . $id);
    }

    public function add_comment(Request $request, $id)
    {
        $comment = new EventComments;
        $comment->user_id = Auth::User()->id;
        $comment->event_id = $id;
        $comment->text = clean($request->text);
        $comment->save();
        return redirect('/insider/events/' . $comment->event_id);
    }

    public function edit_event_view($id)
    {
        $event = Event::findOrFail($id);
        if (Auth::User()->role == 'admin' or Auth::User()->role == 'teacher' or $event->userOrgs->contains(Auth::User()->id)) {
            return view('events/edit_event_view', ['event' => $event]);
        } else {
            return redirect('/insider/events/' . $id);
        }
    }

    public function edit_event(Request $request)
    {
        $event = Event::findOrFail($request->id);
        if (!(Auth::User()->role == 'admin' or Auth::User()->role == 'teacher' or $event->userOrgs->contains(Auth::User()->id))) {
            abort(503);
        }
        $event->name = $request->name;
        $event->text = clean($request->text);
        $event->date = $request->date;
        $time = explode(':', $request->time);
        $event->date = $event->date->setTime($time[0], $time[1]);
        $event->location = $request->location;
        $event->type = $request->type;
        $event->short_text = $request->short_text;
        $event->max_people = $request->max_people;
        $event->skills = $request->skills;
        $event->site = $request->site;
        $event->save();
        foreach ($event->userOrgs as $org) {
            $event->userOrgs()->detach($org->id);
        }

        foreach ($request->orgs as $org) {
            $event->userOrgs()->attach($org);
        }

        foreach ($event->participants as $participant) {
            $event->participants()->detach($participant->id);
        }

        foreach ($request->participants as $participant) {
            $event->participants()->attach($participant);
        }
        return redirect('/insider/events/' . $event->id);
    }
}
