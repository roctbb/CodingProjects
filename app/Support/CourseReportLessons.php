<?php

namespace App\Support;

use App\Course;

final class CourseReportLessons
{
    public static function groups(Course $course): array
    {
        $allLessons = $course->program->lessons->filter(fn ($lesson) => $lesson->steps->contains(
            fn ($step) => $step->tasks->isNotEmpty()
        ));
        $chapters = $course->program->chapters;
        $groups = collect();
        $orderedLessons = collect();

        foreach ($chapters as $chapter) {
            $chapterLessons = $allLessons
                ->where('chapter_id', $chapter->id)
                ->sortBy('sort_index')
                ->filter(function ($lesson) use ($course) {
                    return $lesson->isStarted($course);
                })
                ->values();

            if ($chapterLessons->isEmpty()) {
                continue;
            }

            $groups->push([
                'chapter' => $chapter,
                'lessons' => $chapterLessons,
            ]);
            $orderedLessons = $orderedLessons->merge($chapterLessons);
        }

        $knownChapterIds = $chapters->pluck('id')->all();
        $uncategorizedLessons = $allLessons
            ->filter(function ($lesson) use ($course, $knownChapterIds) {
                return (!$lesson->chapter_id || !in_array($lesson->chapter_id, $knownChapterIds))
                    && $lesson->isStarted($course);
            })
            ->sortBy('sort_index')
            ->values();

        if ($uncategorizedLessons->isNotEmpty()) {
            $groups->push([
                'chapter' => null,
                'lessons' => $uncategorizedLessons,
            ]);
            $orderedLessons = $orderedLessons->merge($uncategorizedLessons);
        }

        return [$orderedLessons->values(), $groups];
    }
}
