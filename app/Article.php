<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    public function author()
    {
        return $this->belongsTo('App\User', 'author_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany(\App\ArticleComments::class);
    }

    public function tags(){
        return $this->belongsToMany( 'App\ArticleTag', 'articles_tags', 'article_id', 'tag_id' );
    }

    public function attachTag($tag)
    {
        $tag = mb_strtolower($tag);
        $record = ArticleTag::where('name', $tag)->first();
        if ($record == null)
        {
            $record = new ArticleTag;
            $record->name = $tag;
            $record->save();
        }
        $this->tags()->attach($record->id);
    }


    public function getUpvotes()
    {
        return $this->hasMany('\App\ArticleVotes')->where('amount', '>', 0)->count();
    }
    public function getDownvotes()
    {
        return $this->hasMany('\App\ArticleVotes')->where('amount', '<', 0)->count();
    }
    public function hasVoted($user)
    {
        return count($this->hasMany('\App\ArticleVotes')->where('user_id', $user)->get()) > 0;
    }

    public function hasUpvoted($user)
    {
        return $this->hasMany('\App\ArticleVotes')->where('user_id', $user)->get()->sum('amount') > 0;
    }

    public function hasDownvoted($user)
    {
        return $this->hasMany('\App\ArticleVotes')->where('user_id', $user)->get()->sum('amount') < 0;
    }
    public function votes()
    {
        return $this->hasMany('App\ArticleVotes');
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

            \App\ArticleVotes::create([
                'amount' => $amount,
                'user_id' => $user_id,
                'article_id' => $this->id
            ]);
        }
    }
}
