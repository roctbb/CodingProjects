<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class CourseLessonStatistics
{
    public const PAGE_SIZE=25;

    public static function forStudents(Collection $stats,Collection $students):Collection
    {
        return $stats->whereIn('student_id',$students->pluck('id')->all());
    }

    public static function firstPage(Collection $students,string $path):LengthAwarePaginator
    {
        return new LengthAwarePaginator($students->take(self::PAGE_SIZE)->values(),$students->count(),self::PAGE_SIZE,1,['path'=>$path]);
    }

    public static function summary(Collection $stats, int $students): array
    {
        return [
            'average' => $students ? (int) round($stats->sum(fn ($row) => min(100, max(0, (float) $row->percent))) / $students) : 0,
            'rewarded' => $stats->filter(fn ($row) => $row->max_points > 0 && $row->percent > 90)->count(),
            'total' => $students,
        ];
    }
}
