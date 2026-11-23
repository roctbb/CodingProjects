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
        if (isset($native['best_evaluation'])) {
            $best = $best === null ? $native['best_evaluation'] : max($best, $native['best_evaluation']);
        }
        $latest = $solutions->sortByDesc('submitted')->first();
        $state = $latest ? ($latest->mark === null ? 'awaiting_review' : 'completed') : null;
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
            $state === 'completed' => ['stored', 'Проверка завершена'],
            $state === 'error' => ['error', 'Ошибка проверки'],
            default => ['pending', 'На проверке'],
        };
        [$tabTone, $icon] = match ($tone) {
            'passed' => ['complete', 'circle-check'],
            'revision' => ['neutral', 'circle-half-stroke'],
            'pending' => ['pending', 'hourglass-half'],
            'error' => ['neutral', 'triangle-exclamation'],
            default => ['neutral', 'circle'],
        };
        return ['tab_tone' => $tabTone, 'icon' => $icon, 'tone' => $tone, 'label' => $label, 'score' => $best === null ? null : "$best / $maximum"];
    }
}
