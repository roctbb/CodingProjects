<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BlockedTask extends Model
{
    protected $table = 'blocked_tasks';

    protected $fillable = [
        'task_id', 'user_id', 'course_id', 'reason', 'blocked_at'
    ];

    protected $dates = ['blocked_at'];

    public function task()
    {
        return $this->belongsTo('App\Task', 'task_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function course()
    {
        return $this->belongsTo('App\Course', 'course_id', 'id');
    }
}
