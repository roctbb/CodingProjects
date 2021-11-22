<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Event extends Model
{
    protected $table = 'events';
    protected $dates = ['date'];

    public function userOrgs()
    {
        return $this->belongsToMany('App\User', 'event_orgs');
    }

    public function isOwner($userId)
    {

        return $this->userOrgs()->first()->id == $userId || \App\User::find($userId)->is_teacher;
    }

    public static function getOld()
    {  
        $start = Carbon::createFromDate(2000, 1, 1);
        $now = Carbon::now();
        return Event::all()->whereBetween('date', [$start, $now]);
    }
    public static function getNew()
    {
        // $now = Carbon::now();
        // $events = Event::all();
        // $end = Carbon::parse($event->date);
        return Event::all()->where('date', '>=', Carbon::now());  
        
    }
    public function hasLiked($userId)
    {
        return $this->userLikes()->where('id', $userId)->count() > 0;
    }
    public function participants()
    {
        return $this->belongsToMany('App\User', 'event_partis');
    }

    public function userLikes()
    {
        return $this->belongsToMany('App\User', 'event_likes');
    }

    public function tags()
    {
        return $this->belongsToMany('App\Tags', 'event_tags', "event_id", "tag_id");
    }

    public function getUpvotes()
    {
        return $this->hasMany('\App\EventLikes')->where('amount', '>', 0)->count();
    }
    public function getDownvotes()
    {
        return $this->hasMany('\App\EventLikes')->where('amount', '<', 0)->count();
    }
    public function hasVoted($user)
    {
        return count($this->hasMany('\App\EventLikes')->where('user_id', $user)->get()) > 0;
    }

    public function hasUpvoted($user)
    {
        return $this->hasMany('\App\EventLikes')->where('user_id', $user)->get()->sum('amount') > 0;
    }

    public function hasDownvoted($user)
    {
        return $this->hasMany('\App\EventLikes')->where('user_id', $user)->get()->sum('amount') < 0;
    }
    public function votes()
    {
        return $this->hasMany('App\EventLikes');
    }

    public function comments()
    {
        return $this->hasMany('App\EventComments');
    }

    public function vote($amount, $user_id = null)
    {
        $user_id = \Auth::id();
        if ($this->hasVoted($user_id))
        {

            $gv = $this->votes()
                  ->where('user_id', $user_id)->get()->first();
            
            if ($gv->amount != $amount)
            {
                $gv->amount = $amount;
            }
            else
            {
                $gv->amount = 0;
            }
            $gv->save();
        }
        else
        {

            \App\EventLikes::create([
                'amount' => $amount,
                'user_id' => $user_id,
                'event_id' => $this->id
            ]);
        }
    }

}
