<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LessonInfo extends Model
{
    protected $table = 'lesson_info';
    public $timestamps = false;
    protected $dates = ['start_date'];

}
