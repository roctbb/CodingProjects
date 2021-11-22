<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ForumThread extends Model
{
    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function tags(){
        return $this->belongsToMany( 'App\ForumTag', 'forum_threads_tags', 'thread_id', 'tag_id' );
    }

    public function subscribers(){
        return $this->belongsToMany( 'App\User', 'forum_threads_subscribers', 'thread_id', 'user_id' );
    }

    public function posts()
    {
        return $this->hasMany('App\ForumPost', 'thread_id', 'id')->orderBy('id');
    }

    public function orderedPosts()
    {
        return $this->posts->sortByDesc(function ($item) {
            if ($item->is_question) return 1000000;
            return $item->getVotes();
        });
    }

    public function attachTag($tag)
    {
        $tag = mb_strtolower($tag);
        $record = ForumTag::where('name', $tag)->first();
        if ($record == null)
        {
            $record = new ForumTag;
            $record->name = $tag;
            $record->save();
        }
        $this->tags()->attach($record->id);
    }
}
