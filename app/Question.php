<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $table = 'questions';

    protected $fillable = [
        'text', 'step_id'
    ];

    public function step()
    {
        return $this->belongsTo('App\ProgramStep', 'step_id', 'id');
    }

    public function variants()
    {
        return $this->hasMany('App\QuestionVariant', 'question_id', 'id');
    }


}
