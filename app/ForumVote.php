<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ForumVote extends Model
{
    protected $fillable = ['post_id', 'user_id', 'mark'];

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function post()
    {
        return $this->belongsTo('App\ForumPost', 'post_id', 'id');
    }
}
