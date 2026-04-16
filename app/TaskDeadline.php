<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskDeadline extends Model
{
    //
    public $fillable = ['course_id', 'task_id', 'expiration', 'penalty'];
    protected $casts = ['expiration' => 'datetime'];
}
