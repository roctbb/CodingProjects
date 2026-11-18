<?php

namespace App\Support;

use App\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class LessonTaskProgress
{
    public static function forStudent(Task $task, int $student, ?Collection $pendingIds = null, ?array $native = null): array
    {
        $attempts = $task->solutions->where('user_id', $student)->filter(fn ($row) => $row->submitted !== null || $row->mark !== null)
            ->sortByDesc(fn ($row) => sprintf('%s:%020d', $row->submitted ?? $row->checked ?? $row->created_at, $row->id));
        $reviewed = $attempts->filter(fn ($row) => $row->mark !== null);
        $evaluation = $reviewed->max(fn ($row) => $row->evaluatedMark());
        $maximum = (int) $task->max_mark;
        $full = $maximum > 0 && $evaluation !== null && $evaluation >= $maximum;
        $accepted = $maximum > 0 && $evaluation !== null && $evaluation >= ($maximum > 1 ? round($maximum * 3 / 4) : 1);
        $pending = $pendingIds === null ? (!$full && $task->isOnCheck($student)) : $attempts->contains(fn ($row) => $pendingIds->has($row->id));
        $latest = $attempts->first();
        $latestAt = $latest?->submitted ?? $latest?->checked ?? $latest?->created_at;
        $newerNative = $native && (!$latestAt || $native['latest_at']->gt(Carbon::parse($latestAt)));
        $pending = $pending || ($newerNative && in_array($native['state'], ['awaiting_review', 'imported_pending'], true));
        $checking = $newerNative && !in_array($native['state'], ['completed', 'error', 'imported_pending', 'awaiting_review'], true);
        $checkError = $newerNative && $native['state'] === 'error';
        $status = match (true) {
            $checking => 'checking',
            $checkError => 'check_error',
            $pending => 'pending',
            $full => 'done',
            $accepted => 'improve',
            $reviewed->isNotEmpty() => 'revision',
            default => 'todo',
        };

        return ['task' => $task, 'status' => $status, 'pending' => $pending, 'checking' => $checking, 'checkError' => $checkError, 'completed' => $accepted, 'full' => $full,
            'attempted' => $attempts->isNotEmpty() || $native !== null, 'optional' => (bool) $task->is_star, 'evaluation' => $evaluation,
            'points' => max(0, (float) ($reviewed->max('mark') ?? 0)), 'maximum' => $maximum,
            'feedbackSolutionId' => $reviewed->first()?->id, 'feedback' => (string) ($reviewed->first()?->comment ?? '')];
    }

    public static function taskCount(int $count, bool $accusative = false): string
    {
        $noun = $count % 100 >= 11 && $count % 100 <= 14 ? 'задач' : match ($count % 10) {
            1 => $accusative ? 'задачу' : 'задача', 2, 3, 4 => 'задачи', default => 'задач',
        };
        return $count.' '.$noun;
    }
}
