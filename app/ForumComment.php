<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ForumComment extends Model
{

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function post()
    {
        return $this->belongsTo('App\ForumPost', 'post_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo('App\ForumComment', 'parent_id', 'id');
    }
}
