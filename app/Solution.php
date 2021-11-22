<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Solution extends Model
{
    protected $table = 'solutions';

    protected $fillable = [
        'text', 'step_id', 'submitted', 'user_id'
    ];

    protected $appends = ['mark'];

    protected $dates = [
        'submitted', 'checked'
    ];
/*
    public function pmark()
    {
        $taskd = \App\Task::find($this->task_id);
        $tasktimeout = false;
        if ($taskd->deadline != null)
            $tasktimeout = $taskd->deadline->lt($this->created_at);
        return round($this->mark * ($tasktimeout ? $taskd->penalty : 1.0));;
    }*/

    public function getNmarkAttribute()
    {
        return $this->mark;
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function task()
    {
        return $this->belongsTo('App\Task', 'task_id', 'id')->with('consequences');
    }

    public function course()
    {
        return $this->belongsTo('App\Course', 'course_id', 'id');
    }

    public function teacher()
    {
        return $this->belongsTo('App\User', 'teacher_id', 'id');
    }
}
