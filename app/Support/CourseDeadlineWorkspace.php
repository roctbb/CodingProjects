<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class CourseDeadlineWorkspace
{
    public static function navigation(\App\Course $course, \App\User $student, ?Collection $items = null): array
    {
        $key = 'course-deadline-items:'.$course->id.':'.$student->id;
        $items ??= request()->attributes->get($key);
        if ($items === null) {
            $available = app(\App\Services\CourseDeadlineService::class)->available()
                || \Illuminate\Support\Facades\Schema::hasTable('task_deadlines');
            $entries = $available ? app(\App\Queries\CourseDeadlineList::class)->forCourses(collect([$course]), $student) : collect();
            $native = app(\App\Services\CodeChecking\History::class)->ungradedProgress(
                (int) $course->id, $entries->pluck('task_id')->unique()->values()->all(), (int) $student->id);
            $items = self::items($entries, (int) $student->id, $native);
        }
        request()->attributes->set($key, $items);
        $unfinishedItems = $items->filter(fn ($item) => !$item['done'] && $item['status'] !== 'blocked');
        $rules = $unfinishedItems->map(function ($item) {
            $rule = $item['rule'];
            return ['legacy' => $rule->legacyBoundary, 'timezone' => $rule->ruleTimezone,
                'actionable' => !in_array($item['status'], ['pending', 'checking'], true),
                'stages' => array_map(fn ($stage) => ['cutoff' => \Carbon\Carbon::parse($stage['at'], 'UTC')->getTimestampMs(),
                    'percent' => (float) $stage['percent'], 'day' => $rule->deadlineAt($stage)->format('Y-m-d')], $rule->series)];
        })->values();
        $now = now();
        $active = $rules->filter(function ($rule) use ($now) {
            $percent = 100;
            foreach ($rule['stages'] as $stage) {
                if ($rule['legacy'] ? $now->getTimestampMs() > $stage['cutoff'] : $now->getTimestampMs() >= $stage['cutoff']) $percent = $stage['percent'];
            }
            return $percent > 0;
        });
        return ['has_upcoming' => $unfinishedItems->contains(fn ($item) => $item['next'] !== null),
            'count' => $active->count(), 'status' => LessonDeadlineStatus::status($active->where('actionable', true)->all(), $now),
            'rules' => $rules->all(), 'now' => $now->getTimestampMs()];
    }

    public static function items(Collection $entries, ?int $student = null, ?Collection $nativeProgress = null): Collection
    {
        $now = now();

        return $entries->groupBy(fn ($entry) => ($entry->audience_id ?? 0).':'.$entry->task_id)->map(function ($stages) use ($now, $student, $nativeProgress) {
            $entry = $stages->first();
            $rule = $entry instanceof DeadlineRule ? $entry : DeadlineRule::fromLegacy($entry);
            $schedule = collect($rule->series);
            $next = $schedule->first(function ($stage) use ($rule, $now) {
                $cutoff = \Carbon\Carbon::parse($stage['at'], 'UTC');
                return $rule->legacyBoundary ? $cutoff->gte($now) : $cutoff->gt($now);
            });
            $last = $schedule->last();
            $progress = $student === null ? null : LessonTaskProgress::forStudent(
                $entry->task, $student, native: $nativeProgress?->get($entry->task_id)?->get($student));
            $blocked = $student !== null && $entry->task->relationLoaded('blockedTasks')
                && $entry->task->blockedTasks->contains(fn ($block) => (int) $block->course_id === (int) $entry->course_id && (int) $block->user_id === $student);

            return [
                'task' => $entry->task,
                'audience_id' => (int) ($entry->audience_id ?? 0), 'audience_name' => $entry->audience_name,
                'rule' => $rule,
                'done' => (bool) $entry->is_done,
                'status' => $blocked ? 'blocked' : ($progress['status'] ?? null),
                'points' => $progress['points'] ?? null,
                'penalty' => (float) round(100 - $rule->at($now)->penalty * 100, 2),
                'next' => $next ? \Carbon\Carbon::parse($next['at'], 'UTC')->getTimestampMs() : null,
                'last' => $last ? $rule->deadlineAt($last) : null,
            ];
        })->sortBy(fn ($item) => [$item['done'] ? 1 : 0, $item['next'] === null ? 1 : 0,
            $item['next'] ?? -($item['last']?->timestamp ?? 0), $item['task']->id])->values();
    }
}
