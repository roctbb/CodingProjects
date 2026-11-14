<?php

namespace App\Support;

use App\Course;
use App\ProgramChapter;
use App\User;
use Illuminate\Support\Collection;

final class CourseStudyOutcome
{
    public static function forChapter(Course $course, ProgramChapter $chapter, User $viewer, Collection $lessons, Collection $states): ?array
    {
        $first = $lessons->first();
        $repeat = $first?->steps->first();
        $repeatUrl = $repeat ? url('/insider/courses/'.$course->id.'/steps/'.$repeat->id) : null;
        if ($course->state === 'ended') {
            return ['title' => 'Курс завершён', 'text' => 'Результаты сохранены. Доступные материалы можно повторять в удобном темпе.', 'url' => $repeatUrl, 'action' => 'Повторить материалы'];
        }
        // A finished open subset does not mean that the whole chapter is complete.
        if ($course->program->lessons->where('chapter_id', $chapter->id)->contains(
            fn ($lesson) => $lesson->steps->isNotEmpty() && !$lesson->isAvailableForUser($course, $viewer)
        )) return null;
        $assessed = $states->filter(fn ($state) => $state['hasTasks']);
        if ($assessed->isEmpty() || !$assessed->every(fn ($state) => $state['rewardEarned'])) return null;

        $complete = $assessed->every(fn ($state) => $state['allTasksDone']);
        $navigation = CourseChapterNavigation::forCourse($course, $chapter, $viewer);
        $next = $navigation['next'];
        $hasLater = $navigation['selected'] < count($navigation['items']) - 1;
        $text = $complete ? 'Все обязательные задачи решены.' : 'Наклейки главы получены. Ответы ещё можно улучшить.';
        if (!$next && $hasLater) $text .= ' Следующая глава пока не открыта.';
        elseif (!$next) $text .= ' Это последняя глава программы; материалы остаются доступны для повторения.';
        $improve = $assessed->first(fn ($state) => $state['needsImprovement']);
        $fallbackUrl = $improve ? url('/insider/courses/'.$course->id.'/steps/'.$improve['nextStep']->id) : $repeatUrl;
        return [
            'title' => $complete ? 'Глава пройдена' : 'Наклейки главы получены',
            'text' => $text,
            'url' => $next['url'] ?? $fallbackUrl,
            'action' => $next ? 'Следующая глава' : ($improve ? 'Улучшить результат' : 'Повторить материалы'),
        ];
    }
}
