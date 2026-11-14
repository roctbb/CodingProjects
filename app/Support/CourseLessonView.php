<?php

namespace App\Support;

use App\Course;
use App\Lesson;
use App\User;

final class CourseLessonView
{
    public static function forStudent(Lesson $lesson, Course $course, User $viewer, $stats): array
    {
        $tasks = $lesson->steps->flatMap->tasks->filter(fn ($task) => !$task->is_star && $task->isVisible($viewer, $course));
        $incomplete = $tasks->filter(fn ($task) => !$task->isFullDone($viewer->id));
        $actionable = $incomplete->reject(fn ($task) => $task->isOnCheck($viewer->id));
        $earned = ($stats->max_points ?? 0) > 0 && ($stats->percent ?? 0) > 90;
        $pendingOnly = $incomplete->isNotEmpty() && $actionable->isEmpty();
        $targetTask = $actionable->first() ?? $incomplete->first();
        $nextStep = $lesson->steps->first(fn ($step) => $step->tasks->contains('id', $targetTask?->id)) ?? $lesson->steps->first();

        [$action, $hint] = match (true) {
            $course->state === 'ended' => ['Повторить материалы', 'Повторить'],
            $tasks->isEmpty() => ['Читать материалы', 'Читать'],
            $pendingOnly => ['Посмотреть работу', 'На проверке'],
            $earned && $incomplete->isNotEmpty() => ['Улучшить результат', 'Улучшить'],
            $incomplete->isEmpty() => ['Повторить урок', 'Повторить'],
            default => ['Продолжить урок', 'Продолжить'],
        };

        return [
            'rewardEarned' => $earned,
            'hasTasks' => $tasks->isNotEmpty(),
            'allTasksDone' => $tasks->isNotEmpty() && $incomplete->isEmpty(),
            'needsImprovement' => $earned && $actionable->isNotEmpty(),
            'status' => match (true) {
                $tasks->isEmpty() => 'Материалы для чтения',
                $pendingOnly => 'На проверке',
                $earned && $incomplete->isNotEmpty() => 'Наклейка получена · можно улучшить',
                $incomplete->isEmpty() => 'Все задачи решены',
                default => null,
            },
            'action' => $action,
            'hint' => $hint,
            'nextStep' => $nextStep,
        ];
    }
}
