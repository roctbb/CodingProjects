<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ForumPost extends Model
{

    protected $table = 'forum_posts';

    public function vote($amount, $user_id = null)
    {
        $user_id = \Auth::id();
        if (!$this->checkVote(User::find($user_id)))
        {

            $gv =  $this->votes()
                ->where('user_id', $user_id)->get()[0];
            if ($gv->mark != $amount)
            {
                $gv->mark = $amount;
            }
            else
            {
                $gv->mark = 0;
            }
            $gv->save();
        }
        else
        {

            \App\ForumVote::create([
                'mark' => $amount,
                'user_id' => $user_id,
                'post_id' => $this->id
            ]);
        }
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function thread()
    {
        return $this->belongsTo('App\ForumThread', 'thread_id', 'id');
    }

    public function votes()
    {
        return $this->hasMany('App\ForumVote', 'post_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany('App\ForumComment', 'post_id', 'id');
    }

    public function getVotes()
    {
        return $this->votes()->sum('mark');
    }
    public function hasUpvoted($user)
    {
        return $this->votes()->where('user_id', $user)->get()->sum('mark') > 0;
    }

    public function hasDownvoted($user)
    {
        return $this->votes()->where('user_id', $user)->get()->sum('mark') < 0;
    }
    public function getUpvotes()
    {
        return $this->votes()->where('mark', '>', 0)->count();
    }
    public function getDownvotes()
    {
        return $this->votes()->where('mark', '<', 0)->count();
    }
        public function checkVote($user)
    {
        return $this->votes()->where('user_id', $user->id)->count()==0;
    }
    public function goodVote($user)
    {
        if ($this->checkVote($user)) return false;
        return $this->votes()->where('user_id', $user->id)->first()->mark==1;
    }



}
