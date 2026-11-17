<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class CourseDeadlineWorkspace
{
    public static function items(Collection $entries): Collection
    {
        $now = now();

        return $entries->groupBy(fn ($entry) => ($entry->audience_id ?? 0).':'.$entry->task_id)->map(function ($stages) use ($now) {
            $entry = $stages->first();
            $rule = $entry instanceof DeadlineRule ? $entry : DeadlineRule::fromLegacy($entry);
            $schedule = collect($rule->series);
            $next = $schedule->first(function ($stage) use ($rule, $now) {
                $cutoff = \Carbon\Carbon::parse($stage['at'], 'UTC');
                return $rule->legacyBoundary ? $cutoff->gte($now) : $cutoff->gt($now);
            });
            $last = $schedule->last();

            return [
                'task' => $entry->task,
                'audience_id' => (int) ($entry->audience_id ?? 0), 'audience_name' => $entry->audience_name,
                'rule' => $rule,
                'done' => (bool) $entry->is_done,
                'penalty' => (float) round(100 - $rule->at($now)->penalty * 100, 2),
                'next' => $next ? \Carbon\Carbon::parse($next['at'], 'UTC')->getTimestampMs() : null,
                'last' => $last ? $rule->deadlineAt($last) : null,
                'chapter' => (string) ($entry->task->step->lesson->chapter_id ?? 0),
            ];
        })->sortBy(fn ($item) => [$item['done'] ? 1 : 0, $item['next'] === null ? 1 : 0,
            $item['next'] ?? -($item['last']?->timestamp ?? 0), $item['task']->id])->values();
    }
}
