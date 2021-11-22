<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'tasks';

    protected $fillable = [
        'text', 'step_id', 'deadline', 'name', 'max_mark', 'is_star', 'only_class', 'only_remote', 'sort_index', 'is_quiz', 'is_code'
    ];

    protected $dates = [
        'deadline'
    ];

    public function step()
    {
        return $this->belongsTo('App\ProgramStep', 'step_id', 'id');
    }

    public function solutions()
    {
        return $this->hasMany('App\Solution', 'task_id', 'id');
    }

    public function questions()
    {
        return $this->hasMany('App\Question', 'task_id', 'id');
    }

    public function consequences()
    {
        return $this->belongsToMany('App\CoreNode', 'core_consequences', "task_id", "node_id");
    }

    public function tests()
    {
        return $this->hasMany('App\CodeTask', 'task_id', 'id');
    }

    public function isDone($user_id)
    {
        if ($this->max_mark > 1)
        {
            return $this->solutions()->where('user_id', $user_id)->where('mark', '>', 1)->count() !=0;
        }
        else {
            return $this->solutions()->where('user_id', $user_id)->where('mark', '>=', 1)->count() !=0;
        }
    }
    public function isOnCheck($user_id)
    {
        return $this->solutions()->where('user_id', $user_id)->where('mark',  null)->count() !=0;
    }
    public function isSubmitted($user_id)
    {
        return $this->solutions()->where('user_id', $user_id)->count() !=0;
    }
    public function isFailed($user_id)
    {
        return $this->isSubmitted($user_id) and !$this->isDone($user_id) and !$this->isOnCheck($user_id);
    }

    public function isFullDone($user_id)
    {
        return $this->solutions()->where('user_id', $user_id)->where('mark', '=', $this->max_mark)->count() !=0;
    }

    public function getDeadline($course_id)
    {
        return $this->hasMany('App\TaskDeadline', 'task_id', 'id')->where('course_id', $course_id)->get()->first();

    }



}
