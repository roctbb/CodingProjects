<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    protected $table = 'answers';

    public function user()
    {
        return $this->hasOne('App\User', 'user_id', 'id');
    }
    public function variant()
    {
        return $this->hasOne('App\QuestionVariant', 'variant_id', 'id');
    }

    public function question()
    {
        return $this->hasOne('App\Question', 'question_id', 'id');
    }
}
