<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'tasks';

    protected $fillable = [
        'text', 'step_id', 'deadline', 'name', 'max_mark', 'is_star', 'only_class', 'only_remote', 'sort_index', 'is_quiz', 'is_code', 'is_hidden', 'xp_booster_enabled', 'generates_ai_achievement', 'ai_achievement_instruction'
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'generates_ai_achievement' => 'boolean',
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

    public function deadlines()
    {
        return $this->hasMany('App\TaskDeadline', 'task_id', 'id');
    }

    public function blockedTasks()
    {
        return $this->hasMany('App\BlockedTask', 'task_id', 'id');
    }

    protected function solutionsForUser($user_id)
    {
        if ($this->relationLoaded('solutions')) {
            return $this->solutions->where('user_id', $user_id);
        }

        return $this->solutions()->where('user_id', $user_id);
    }

    public function isDone($user_id)
    {
        $minMark = $this->max_mark > 1 ? round($this->max_mark * 3 / 4) : 1;

        return $this->solutionsForUser($user_id)
                ->where('mark', '>=', $minMark)
                ->count() != 0;
    }

    public function isOnCheck($user_id)
    {
        if ($this->relationLoaded('solutions')) {
            return $this->solutionsForUser($user_id)
                    ->filter(function ($solution) {
                        return $solution->submitted !== null
                            && ($solution->mark === null || $solution->recheck_requested)
                            && !$solution->review_skipped;
                    })
                    ->count() != 0;
        }

        return $this->solutionsForUser($user_id)
                ->whereNotNull('submitted')
                ->where(function ($query) {
                    $query->whereNull('mark')
                        ->orWhere('recheck_requested', true);
                })
                ->where(function ($query) {
                    $query->where('review_skipped', false)
                        ->orWhereNull('review_skipped');
                })
                ->exists();
    }

    public function isSubmitted($user_id)
    {
        return $this->solutionsForUser($user_id)->count() != 0;
    }

    public function isFailed($user_id)
    {
        return $this->isSubmitted($user_id) and !$this->isDone($user_id) and !$this->isOnCheck($user_id);
    }

    public function isFullDone($user_id)
    {
        return $this->solutionsForUser($user_id)->where('mark', '=', $this->max_mark)->count() !=0;
    }

    public function hasRewardableFullSolution($user_id)
    {
        return $this->solutionsForUser($user_id)
                ->where('mark', '=', $this->max_mark)
                ->where('xp_booster_used_at', null)
                ->count() != 0;
    }

    public function getDeadline($course_id)
    {
        if ($this->relationLoaded('deadlines')) {
            return $this->deadlines->where('course_id', $course_id)->first();
        }

        return $this->hasMany('App\TaskDeadline', 'task_id', 'id')->where('course_id', $course_id)->get()->first();

    }

    public function isBlocked($user_id, $course_id)
    {
        if ($this->relationLoaded('blockedTasks')) {
            return $this->blockedTasks
                    ->where('user_id', $user_id)
                    ->where('course_id', $course_id)
                    ->count() != 0;
        }

        return \App\BlockedTask::where('task_id', $this->id)
            ->where('user_id', $user_id)
            ->where('course_id', $course_id)
            ->exists();
    }

    public function isVisible($user, $course)
    {
        if (!$this->is_hidden) return true;

        $user_id = $user instanceof User ? $user->id : $user;
        if ($course->teachers->contains('id', $user_id)) return true;

        $user = $user instanceof User ? $user : User::find($user_id);
        if ($user && $user->role == 'admin') return true;
        if ($user && $user->relationLoaded('submissions')) {
            return $user->submissions
                    ->where('task_id', $this->id)
                    ->where('course_id', $course->id)
                    ->count() != 0;
        }
        if ($user && $user->relationLoaded('solutions')) {
            return $user->solutions
                    ->where('task_id', $this->id)
                    ->where('course_id', $course->id)
                    ->count() != 0;
        }

        return $this->solutionsForUser($user_id)->where('course_id', $course->id)->count() != 0;
    }

    public function latestSolutionForUser($user_id)
    {
        $solutions = $this->solutionsForUser($user_id);

        if ($this->relationLoaded('solutions')) {
            return $solutions->sortByDesc('submitted')->first();
        }

        return $solutions->orderByDesc('submitted')->first();
    }

}
