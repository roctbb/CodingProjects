<?php

namespace App\Queries;

use App\TaskDeadline;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as ModelCollection;
use Illuminate\Support\Collection;

class CourseDeadlineList
{
    public function forCourses(Collection $courses, User $viewer, bool $includeCompleted = false): Collection
    {
        if ($courses->isEmpty()) {
            return collect();
        }

        $courses = new ModelCollection($courses->all());
        $courses->loadMissing('teachers', 'students');
        $courseIds = $courses->modelKeys();
        $byId = $courses->keyBy('id');
        $now = Carbon::now();

        return TaskDeadline::with([
            'task.step.lesson.info' => fn ($query) => $query->whereIn('course_id', $courseIds),
            'task.step.lesson.earlyAccesses' => fn ($query) => $query->whereIn('course_id', $courseIds)->where('user_id', $viewer->id),
            'task.solutions' => fn ($query) => $query->whereIn('course_id', $courseIds)->where('user_id', $viewer->id),
        ])
            ->whereIn('course_id', $courseIds)
            ->whereNotNull('expiration')
            ->orderBy('expiration')->orderBy('course_id')->orderBy('task_id')
            ->get()
            ->filter(function ($deadline) use ($byId, $viewer) {
                $course = $byId->get($deadline->course_id);
                $lesson = $deadline->task?->step?->lesson;
                if (!$lesson || (int) $lesson->program_id !== (int) $course->program_id) {
                    return false;
                }
                return $viewer->role === 'admin' || $course->teachers->contains('id', $viewer->id)
                    || ($lesson->isAvailableForUser($course, $viewer) && $deadline->task->isVisible($viewer, $course));
            })
            ->map(function ($deadline) use ($byId, $viewer, $now) {
                $course = $byId->get($deadline->course_id);
                $isManager = $viewer->role === 'admin' || $course->teachers->contains('id', $viewer->id);
                $minimumMark = $deadline->task->max_mark > 1 ? round($deadline->task->max_mark * 3 / 4) : 1;
                $deadline->is_done = !$isManager && $deadline->task->solutions
                    ->where('course_id', $course->id)->where('mark', '>=', $minimumMark)->isNotEmpty();
                $deadline->is_overdue = !$deadline->is_done && $deadline->expiration->copy()->addDay()->lte($now);
                $deadline->is_today = !$deadline->is_done && $deadline->expiration->isSameDay($now);
                $deadline->is_soon = !$deadline->is_done && !$deadline->is_overdue && $deadline->expiration->lt($now->copy()->addDays(3));
                $deadline->setRelation('course', $course);

                return $deadline;
            })
            ->filter(fn ($deadline) => $includeCompleted || !$deadline->is_done)
            ->values();
    }
}
