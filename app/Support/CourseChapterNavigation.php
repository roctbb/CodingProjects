<?php

namespace App\Support;

use App\Course;
use App\ProgramChapter;
use App\User;

final class CourseChapterNavigation
{
    public static function forCourse(Course $course, ProgramChapter $current, User $viewer): array
    {
        $isManager = $viewer->role === 'admin' || $course->teachers->contains('id', $viewer->id);
        $items = $course->program->chapters->values()->map(function ($chapter) use ($course, $viewer, $isManager) {
            $available = $isManager || $chapter->isAvailableForUser($course, $viewer);

            return [
                'id' => $chapter->id,
                'name' => $chapter->name,
                'url' => $available ? url('/insider/courses/'.$course->id.'?chapter='.$chapter->id) : null,
            ];
        });
        $selected = $items->search(fn ($item) => $item['id'] == $current->id);
        if ($selected === false) {
            throw new \LogicException('Selected chapter must belong to the course program.');
        }

        return [
            'items' => $items->all(),
            'selected' => $selected,
            'previous' => $items->take($selected)->last(fn ($item) => $item['url'] !== null),
            'next' => $items->slice($selected + 1)->first(fn ($item) => $item['url'] !== null),
            'availableCount' => $items->filter(fn ($item) => $item['url'] !== null)->count(),
            'hasAlternatives' => $items->contains(fn ($item, $index) => $index !== $selected && $item['url'] !== null),
        ];
    }
}
