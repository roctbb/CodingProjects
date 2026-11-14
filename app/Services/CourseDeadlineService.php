<?php

namespace App\Services;

use App\Course;
use App\Task;
use App\TaskDeadline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CourseDeadlineService
{
    public function set(Course $course, Task $task, ?string $date, mixed $penalty = null): void
    {
        abort_unless($task->step && $task->step->lesson && (int) $task->step->lesson->program_id === (int) $course->program_id, 404);
        Validator::make(['date' => $date, 'penalty' => $penalty], ['date' => 'nullable|date_format:Y-m-d', 'penalty' => 'nullable|numeric|min:0|max:1'])->validate();
        DB::transaction(function () use ($course, $task, $date, $penalty) {
            if (!$date) {
                TaskDeadline::where('course_id', $course->id)->where('task_id', $task->id)->delete();
                return;
            }
            $current = TaskDeadline::where('course_id', $course->id)->where('task_id', $task->id)->first();
            $values = ['expiration' => $date, 'penalty' => $penalty ?? $current?->penalty ?? 0];
            ($current ?? new TaskDeadline(['course_id' => $course->id, 'task_id' => $task->id]))->fill($values)->save();
        });
    }
}
