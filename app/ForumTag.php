<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ForumTag extends Model
{
    public function threads(){
        return $this->belongsToMany( 'App\ForumThread', 'forum_threads_tags', 'tag_id', 'thread_id' );
    }
}
