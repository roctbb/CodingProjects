<?php

namespace App\Support;

use App\Course;
use App\User;
use Illuminate\Support\Collection;

final class ProjectPath
{
    public static function forChapters(Collection $chapters, Collection $lessons, Course $course, User $viewer, Collection $stats, int $currentId): array
    {
        $manager = $viewer->role === 'admin' || $course->teachers->contains('id', $viewer->id);
        $nodes = $chapters->values()->map(function ($chapter, $index) use ($lessons, $course, $viewer, $stats, $currentId, $manager) {
            $chapterLessons = $lessons->where('chapter_id', $chapter->id);
            $available = $manager || $chapterLessons->contains(fn ($lesson) => $lesson->isAvailableForUser($course, $viewer));
            // Never infer chapter completion from one scored lesson or a closed subset.
            $earned = !$manager && $chapterLessons->isNotEmpty() && $chapterLessons->every(fn ($lesson) => $lesson->isAvailableForUser($course, $viewer)
                && ($stats->get($lesson->id)->max_points ?? 0) > 0 && ($stats->get($lesson->id)->percent ?? 0) > 90);

            return [
                'id' => $chapter->id, 'number' => $index + 1, 'name' => $chapter->name,
                'current' => $chapter->id === $currentId, 'earned' => $earned,
                'url' => $available ? url('/insider/courses/'.$course->id.'?chapter='.$chapter->id) : null,
                'state' => $earned ? 'Наклейки главы получены' : ($chapter->id === $currentId ? 'Выбранная глава' : ($available ? 'Глава доступна' : 'Пока закрыта')),
            ];
        });
        $current = $nodes->search(fn ($node) => $node['current']);
        $start = max(0, min(($current === false ? 0 : $current) - 2, $nodes->count() - 5));

        return ['nodes' => $nodes->slice($start, 5)->values()->all(), 'total' => $nodes->count(), 'current' => $current === false ? null : $nodes[$current]];
    }
}
