<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class QuestionVariant extends Model
{
    protected $table = 'questions_variants';

    protected $fillable = [
        'text'
    ];
}
