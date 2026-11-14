<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class CourseLessonStatistics
{
    public static function summary(Collection $stats, int $students): array
    {
        return [
            'average' => $students ? (int) round($stats->sum(fn ($row) => min(100, max(0, (float) $row->percent))) / $students) : 0,
            'rewarded' => $stats->filter(fn ($row) => $row->max_points > 0 && $row->percent > 90)->count(),
            'total' => $students,
        ];
    }
}
