<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Lesson extends Model
{
    protected $table = 'lessons';

    protected $fillable = [
        'name', 'description', 'image', 'start_date', 'early_access_enabled'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'early_access_enabled' => 'boolean',
    ];

    protected $results_cache = array();

    public function program()
    {
        return $this->belongsTo('App\Program', 'program_id', 'id');
    }

    public function chapter()
    {
        return $this->belongsTo('App\ProgramChapter', 'chapter_id', 'id');
    }

    public function steps()
    {
        return $this->hasMany('App\ProgramStep', 'lesson_id', 'id')->with('tasks')->orderBy('sort_index')->orderBy('id');
    }

    public function percent(User $student, $course = null)
    {
        $points = $this->points($student, $course);
        $max_points = $this->max_points($student, $course);
        if ($max_points == 0) return 100;
        return $points * 100 / $max_points;

    }

    public function points(User $student, $course = null)
    {
        $sum = 0;
        foreach ($this->steps as $step)
            $sum += $step->points($student, $course);
        return $sum;
    }

    public function max_points(User $student, $course = null)
    {
        $sum = 0;
        foreach ($this->steps as $step)
            $sum += $step->max_points($student, $course);
        return $sum;
    }

    public function tasks()
    {
        $tasks = new \Illuminate\Database\Eloquent\Collection;
        foreach ($this->steps as $step) {
            $tasks = $tasks->merge($step->tasks);
        }
        return $tasks;
    }

    public function info()
    {
        return $this->hasMany('App\LessonInfo', "lesson_id");
    }

    public function earlyAccesses()
    {
        return $this->hasMany('App\LessonEarlyAccess', 'lesson_id', 'id');
    }

    public function earlyAccessCost()
    {
        return 10;
    }

    public function hasEarlyAccess($course, $user)
    {
        if (!$course || !$user) {
            return false;
        }

        if ($this->relationLoaded('earlyAccesses')) {
            return $this->earlyAccesses
                    ->where('course_id', $course->id)
                    ->where('user_id', $user->id)
                    ->count() != 0;
        }

        return LessonEarlyAccess::where('course_id', $course->id)
            ->where('lesson_id', $this->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function canBuyEarlyAccess($course, $user)
    {
        if (!$course || !$user || !$this->early_access_enabled || $this->isStarted($course)) {
            return false;
        }

        if (!$course->students->contains('id', $user->id)) {
            return false;
        }

        return !$this->hasEarlyAccess($course, $user);
    }

    public function getStartDate($course)
    {
        $info = $this->info->where('course_id', $course->id)->first();
        if ($info == null) return null;
        else return $info->start_date;
    }

    public function setStartDate($course, $date)
    {
        $info = $this->info->where('course_id', $course->id)->first();
        if ($info == null) {
            $info = new LessonInfo();
            $info->lesson_id = $this->id;
            $info->course_id = $course->id;
            $info->start_date = $date;
        } else {
            $info->start_date = $date;
        }
        $info->save();
    }

    public function isStarted($course)
    {
        $info = $this->info->where('course_id', $course->id)->first();
        if ($info == null) return false;
        if ($info->start_date == null) return false;
        return $info->start_date->lt(Carbon::now()->setTime(23, 59));
    }

    public function isAvailable($course)
    {
        $user = \Auth::user();
        if ($user == null) {
            return false;
        }
        return $this->isAvailableForUser($course, $user);
    }

    public function isAvailableForUser($course, $user)
    {
        if ($user->role == 'admin' || $course->teachers->contains($user)) return true;
        if (!$course->students->contains('id', $user->id)) return false;
        return $this->isStarted($course) || $this->hasEarlyAccess($course, $user);
    }

    public function isDone($course)
    {
        $user = \Auth::user();
        if ($user == null) {
            return false;
        }
        return $this->isDoneByUser($course, $user);
    }

    public function isDoneByUser($course, $user)
    {
        if ($user->role == 'admin' || $course->teachers->contains($user)) return true;
        if (!$this->isAvailableForUser($course, $user)) return false;
        $this->loadMissing('steps.tasks.solutions');
        foreach ($this->tasks()->where('is_star', false) as $task) {
            if (!$task->isVisible($user, $course)) continue;
            if ($user->relationLoaded('submissions')) {
                $minMark = $task->max_mark > 1 ? round($task->max_mark * 3 / 4) : 1;
                $isDone = $user->submissions
                    ->where('course_id', $course->id)
                    ->where('task_id', $task->id)
                    ->where('mark', '>=', $minMark)
                    ->count() != 0;

                if (!$isDone) return false;
                continue;
            }

            if (!$task->isDone($user->id)) return false;
        }
        return true;
    }

    public function export()
    {
        $lesson = Lesson::where('id', $this->id)->with('steps')->first();
        unset($lesson->id);
        unset($lesson->updated_at);
        foreach ($lesson->steps as $key => $step) {
            unset($lesson->steps[$key]->id);
            unset($lesson->steps[$key]->updated_at);
            unset($lesson->steps[$key]->lesson_id);
            unset($lesson->steps[$key]->program_id);

            foreach ($lesson->steps[$key]->tasks as $tkey => $task) {
                unset($lesson->steps[$key]->tasks[$tkey]->id);
                unset($lesson->steps[$key]->tasks[$tkey]->step_id);
                unset($lesson->steps[$key]->tasks[$tkey]->updated_at);
            }
        }
        return $lesson->toJson();
    }

    public function import($lesson_json)
    {
        $new_lesson = json_decode($lesson_json);
        if (json_last_error() !== JSON_ERROR_NONE || !$new_lesson || !isset($new_lesson->steps)) {
            throw new \InvalidArgumentException('Некорректный JSON урока.');
        }

        DB::transaction(function () use ($new_lesson) {
            foreach ($new_lesson->steps as $step) {
                $tasks = $step->tasks ?? [];
                unset($step->tasks);
                $new_step = new ProgramStep();
                foreach ($step as $property => $value) {
                    if (!is_array($value) && !is_object($value)) {
                        $new_step->$property = $value;
                    }
                }

                $new_step->lesson_id = $this->id;
                $new_step->program_id = $this->program_id;
                $new_step->save();

                foreach ($tasks as $task) {
                    $new_task = new Task();
                    foreach ($task as $property => $value) {
                        if (!is_array($value) && !is_object($value)) {
                            $new_task->$property = $value;
                        }
                    }

                    $new_task->step_id = $new_step->id;
                    $new_task->save();
                }
            }
            $this->save();
        });
    }

}
