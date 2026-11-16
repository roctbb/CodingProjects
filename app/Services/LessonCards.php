<?php

namespace App\Services;

use App\Course;
use App\Solution;
use App\Support\LessonTaskProgress;
use App\User;
use Illuminate\Database\Eloquent\Collection as Models;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class LessonCards
{
    public function forLessons(Course $course, Collection $lessons, User $viewer, Collection $students): Collection
    {
        $manager = $viewer->role === 'admin' || $course->teachers->contains('id', $viewer->id);
        $tasks = new Models($lessons->flatMap->steps->flatMap->tasks->all());
        $pending = Solution::where('course_id', $course->id)->whereIn('task_id', $tasks->modelKeys())
            ->when(!$manager, fn ($query) => $query->where('user_id', $viewer->id))
            ->has('user')->pendingReview()->get(['id', 'task_id', 'user_id']);
        $pendingIds = $pending->keyBy('id');
        $native = app(\App\Services\CodeChecking\History::class)->ungradedProgress($course->id, $tasks->modelKeys(), $manager ? null : $viewer->id);
        if ($manager) {
            $columns = ['id', 'course_id', 'task_id', 'user_id', 'submitted', 'checked', 'created_at', 'mark', 'review_skipped', 'recheck_requested'];
            foreach (['raw_mark', 'evaluated_mark'] as $column) if (Schema::hasColumn('solutions', $column)) $columns[] = $column;
            $tasks->load(['solutions' => fn ($query) => $query->where('course_id', $course->id)
                ->whereIn('user_id', $students->pluck('id'))->select($columns)]);
        }
        $studentRows = collect();
        if (!$manager) {
            $studentRows = $lessons->filter(fn ($lesson) => $lesson->isAvailableForUser($course, $viewer))->flatMap->steps->flatMap->tasks
                ->filter(fn ($task) => $task->isVisible($viewer, $course) && $task->max_mark > 0)
                ->map(fn ($task) => LessonTaskProgress::forStudent($task, $viewer->id, $pendingIds, $native->get($task->id)?->get($viewer->id)));
            $studentRows = app(StudentLessonFeedback::class)->visible($studentRows, $course, $viewer)->keyBy('task.id');
        }

        return $lessons->mapWithKeys(function ($lesson) use ($course, $students, $manager, $pending, $pendingIds, $studentRows, $native) {
            if (!$manager) {
                $rows = $studentRows->only($lesson->steps->flatMap->tasks->pluck('id')->all())->values();
                return [$lesson->id => ['rows' => $rows, 'points' => $rows->sum('points'),
                    'maximum' => $rows->where('optional', false)->sum('maximum')]];
            }
            $completed = 0;
            $started = 0;
            $assigned = 0;
            $checking = 0;
            foreach ($students as $student) {
                $required = $lesson->steps->flatMap->tasks->filter(fn ($task) => !$task->is_star && $task->max_mark > 0 && $task->isVisible($student, $course))
                    ->map(fn ($task) => LessonTaskProgress::forStudent($task, $student->id, $pendingIds, $native->get($task->id)?->get($student->id)));
                if ($required->isNotEmpty()) $assigned++;
                if ($required->isNotEmpty() && $required->every(fn ($row) => $row['completed'])) $completed++;
                if ($required->contains(fn ($row) => $row['attempted'])) $started++;
                $checking += $required->where('checking', true)->count();
            }
            $taskIds = $lesson->steps->flatMap->tasks->pluck('id');
            $waiting = $course->state === 'started' ? $pending->whereIn('task_id', $taskIds)->count() : 0;
            return [$lesson->id => ['completed' => $completed, 'total' => $assigned, 'assessed' => $assigned > 0,
                'notStarted' => $assigned - $started, 'unfinished' => $assigned - $completed,
                'percent' => $assigned ? (int) round($completed * 100 / $assigned) : 0,
                'pending' => $waiting, 'checking' => $checking, 'hasActivity' => $started > 0 || $waiting > 0,
                'reviewUrl' => url('/insider/reviews').'?'.http_build_query(['course' => $course->id, 'lesson' => $lesson->id])]];
        });
    }
}
