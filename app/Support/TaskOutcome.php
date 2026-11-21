<?php

namespace App\Support;

use App\Task;

final class TaskOutcome
{
    public static function forStudent(Task $task, int $student, ?array $native): array
    {
        // The workbook eager-loads solutions for this course.
        $solutions = $task->solutions->where('user_id', $student)->whereNotNull('submitted');
        $best = $solutions->whereNotNull('mark')->max(fn ($solution) => $solution->evaluatedMark());
        $latest = $solutions->sortByDesc('submitted')->first();
        $state = $latest ? 'awaiting_review' : null;
        if ($native && (!$latest || $native['latest_at']->gte($latest->submitted))) {
            $state = $native['state'];
        }
        return self::summary($best, (int) $task->max_mark, $state);
    }

    public static function summary(?int $best, int $maximum, ?string $state): array
    {
        [$tone, $label] = match (true) {
            $best !== null && $maximum > 0 && $best >= $maximum => ['passed', 'Решение принято'],
            $best !== null && $maximum > 1 && $best >= round($maximum * .75) => ['passed', 'Задача зачтена'],
            $best !== null => ['revision', 'Требует доработки'],
            $state === null => ['empty', 'Решение не сдано'],
            $state === 'error' => ['error', 'Ошибка проверки'],
            default => ['pending', 'На проверке'],
        };
        return ['tone' => $tone, 'label' => $label, 'score' => $best === null ? null : "$best / $maximum"];
    }
}
