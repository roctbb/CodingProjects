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
        return app(\App\Services\CourseAccess::class)->manages($user, Course::findOrFail($courseId));
    }

    public function task(User $user, int $courseId, int $taskId, bool $teacherOnly = false): Task
    {
        $course = Course::findOrFail($courseId);
        $access = app(\App\Services\CourseAccess::class);
        $task = $access->task($course, $taskId);
        if (!$this->teacher($user, $courseId)) {
            abort_if($teacherOnly, 403);
            $access->study($user, $course, $task->step->lesson);
            abort_unless(DB::table('course_students')->where('course_id', $courseId)->where('user_id', $user->id)->exists()
                && $task->isVisible($user, $course), 403);
        }
        return $task;
    }

    public function authorOrTeacher(User $user, object $record): void
    {
        if ((int)$record->user_id===(int)$user->id && DB::table('course_students')->where('course_id',$record->course_id)->where('user_id',$user->id)->exists()) {
            app(\App\Services\CourseAccess::class)->task(Course::findOrFail($record->course_id),(int)$record->task_id);
            return; // Moving into an unopened lesson preserves access to existing work.
        }
        $this->task($user, (int) $record->course_id, (int) $record->task_id);
        abort_unless((int) $record->user_id === (int) $user->id || $this->teacher($user, (int) $record->course_id), 403);
    }
}
