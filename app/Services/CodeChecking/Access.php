<?php

namespace App\Services\CodeChecking;

use App\Course;
use App\Task;
use App\User;
use Illuminate\Support\Facades\DB;

class Access
{
    public function teacher(User $user, int $courseId): bool
    {
        return $user->role === 'admin' || DB::table('course_teachers')->where('course_id', $courseId)->where('user_id', $user->id)->exists();
    }

    public function task(User $user, int $courseId, int $taskId, bool $teacherOnly = false): Task
    {
        $course = Course::findOrFail($courseId);
        $task = Task::with('step')->findOrFail($taskId);
        abort_unless($task->step && (int) $task->step->program_id === (int) $course->program_id, 404);
        if (!$this->teacher($user, $courseId)) {
            abort_if($teacherOnly, 403);
            abort_unless(DB::table('course_students')->where('course_id', $courseId)->where('user_id', $user->id)->exists()
                && $task->isVisible($user, $course), 403);
        }
        return $task;
    }

    public function authorOrTeacher(User $user, object $record): void
    {
        $this->task($user, (int) $record->course_id, (int) $record->task_id);
        abort_unless((int) $record->user_id === (int) $user->id || $this->teacher($user, (int) $record->course_id), 403);
    }
}
