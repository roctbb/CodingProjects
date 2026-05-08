<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskDeadline extends Model
{
    public $fillable = ['course_id', 'task_id', 'expiration', 'penalty'];
    protected $casts = [
        'expiration' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo('App\Task', 'task_id', 'id');
    }

    public function course()
    {
        return $this->belongsTo('App\Course', 'course_id', 'id');
    }
}
