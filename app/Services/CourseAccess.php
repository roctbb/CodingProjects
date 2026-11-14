<?php
namespace App\Services;

use App\Course;
use App\Lesson;
use App\ProgramStep;
use App\Task;
use App\User;

/** Shared boundaries for pages, authoring and submission APIs. */
class CourseAccess
{
    public function manages(User $user, Course $course): bool
    {
        return $user->role === 'admin' || ($user->role === 'teacher'
            && $course->teachers()->where('users.id', $user->id)->exists());
    }

    public function manage(User $user, Course $course): void
    {
        abort_unless($this->manages($user, $course), 403);
    }

    public function lesson(Course $course, int $id): Lesson
    {
        return Lesson::where('program_id', $course->program_id)->findOrFail($id);
    }

    public function step(Course $course, int $id): ProgramStep
    {
        $step = ProgramStep::with('lesson')->where('program_id', $course->program_id)->findOrFail($id);
        abort_unless($step->lesson && (int) $step->lesson->program_id === (int) $course->program_id, 404);
        return $step;
    }

    public function task(Course $course, int $id): Task
    {
        $task = Task::with('step.lesson')->findOrFail($id);
        abort_unless($task->step && (int) $task->step->program_id === (int) $course->program_id
            && $task->step->lesson && (int) $task->step->lesson->program_id === (int) $course->program_id, 404);
        return $task;
    }

    public function study(User $user, Course $course, Lesson $lesson): void
    {
        if ($this->manages($user, $course)) return;
        abort_unless($course->state !== 'draft' && $lesson->isAvailableForUser($course, $user), 403);
    }
}
