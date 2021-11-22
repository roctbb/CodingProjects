<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProgramChapter extends Model
{
    public function program()
    {
        return $this->belongsTo('App\Program', 'program_id', 'id');
    }

    public function lessons()
    {
        return $this->hasMany('App\Lesson', 'chapter_id', 'id')->orderBy('sort_index')->orderBy('id');
    }

    public function isAvailable($course)
    {
        $user = User::findOrFail(\Auth::User()->id);
        return $this->isAvailableForUser($course, $user);
    }

    public function isAvailableForUser($course, $user)
    {
        foreach ($this->lessons as $lesson) {
            if ($lesson->isAvailableForUser($course, $user)) return true;
        }
        return false;
    }

    public function isDone($course)
    {
        $user = User::findOrFail(\Auth::User()->id);
        return $this->isDoneByUser($course, $user);
    }

    public function isDoneByUser($course, $user)
    {
        foreach ($this->lessons as $lesson) {
            if (!$lesson->isDoneByUser($course, $user)) return false;
        }
        return true;
    }

    public function isStarted($course)
    {
        if ($this->lessons->count() == 0) return false;
        foreach ($this->lessons as $lesson) {
            if ($lesson->isStarted($course)) return true;
        }
        return false;
    }

    public function getStudentsPercent($course)
    {
        $done = 0;
        foreach ($course->students as $student) {
            if ($this->isDoneByUser($course, $student))
                $done++;
        }
        return $done * 100 / max($course->students->count(), 1);
    }

    public function getStudentPercent($student)
    {

        $temp_steps = collect([]);
        foreach ($this->lessons as $lesson) {
            $temp_steps = $temp_steps->merge($lesson->steps);
        }

        $max_points = 0;
        $points = 0;
        foreach ($temp_steps as $step) {

            $tasks = $step->class_tasks;


            foreach ($tasks as $task) {
                if (!$task->is_star) $max_points += $task->max_mark;
                $points += $student->submissions->where('task_id', $task->id)->max('mark');
            }

        }
        if ($max_points != 0) {
            return min(100, $points * 100 / $max_points);
        }
        return 0;
    }
}
