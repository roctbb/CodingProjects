<?php

namespace App\Support;

use App\Course;
use App\Lesson;
use App\User;
use Illuminate\Support\Collection;

final class CourseLessonView
{
    public static function forStudent(Lesson $lesson, Course $course, User $viewer, $stats, ?Collection $rows = null): array
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

        $rows ??= $tasks->filter(fn ($task) => $task->max_mark > 0)->map(fn ($task) => LessonTaskProgress::forStudent($task, $viewer->id));
        $required = $rows->where('optional', false);
        $revision = $required->where('status', 'revision');
        $todo = $required->where('status', 'todo');
        $pendingCount = $required->where('pending', true)->count();
        $improvable = $required->where('status', 'improve');
        $checking = $required->where('checking', true);
        $checkErrors = $required->where('status', 'check_error');
        $completed = $required->isNotEmpty() && $required->every(fn ($row) => $row['completed']);
        $remaining = $revision->count() + $todo->count() + $checkErrors->count();
        $priority = $revision->first() ?? $checkErrors->first() ?? $todo->first() ?? $checking->first() ?? $required->first(fn ($row) => $row['pending']) ?? $improvable->first();
        if ($priority) $nextStep = $lesson->steps->firstWhere('id', $priority['task']->step_id) ?? $nextStep;
        if ($course->state !== 'ended' && $revision->isNotEmpty()) [$action, $hint] = ['Исправить работу', 'Исправить'];
        elseif ($course->state !== 'ended' && $checkErrors->isNotEmpty()) [$action, $hint] = ['Посмотреть проверку', 'Проверка'];
        elseif ($course->state !== 'ended' && $todo->isNotEmpty()) [$action, $hint] = ['Продолжить урок', 'Продолжить'];
        elseif ($course->state !== 'ended' && $checking->isNotEmpty()) [$action, $hint] = ['Посмотреть проверку', 'Проверяется'];
        elseif ($course->state !== 'ended' && !$remaining && $pendingCount) [$action, $hint] = ['Посмотреть работу', 'На проверке'];
        elseif ($course->state !== 'ended' && !$remaining && $improvable->isNotEmpty()) [$action, $hint] = ['Улучшить результат', 'Улучшить'];
        elseif ($course->state !== 'ended' && $completed) [$action, $hint] = ['Повторить урок', 'Повторить'];
        $workLabel = match (true) {
            $required->isEmpty() => 'Материалы для чтения',
            $revision->isNotEmpty() => 'Исправить '.LessonTaskProgress::taskCount($revision->count(), true),
            $checkErrors->isNotEmpty() => 'Проверка не завершилась',
            $remaining > 0 => ($remaining % 10 === 1 && $remaining % 100 !== 11 ? 'Осталась ' : 'Осталось ').LessonTaskProgress::taskCount($remaining),
            $completed => $required->every(fn ($row) => $row['full']) ? 'Все задачи решены' : 'Все задачи зачтены',
            default => null,
        };
        $targetUrl = $priority ? app(\App\Services\TaskLocation::class)->task($course->id, $priority['task'], $viewer->id)
            : ($nextStep ? url('/insider/courses/'.$course->id.'/steps/'.$nextStep->id) : null);

        return [
            'rows' => $rows, 'completed' => $completed, 'canImprove' => $improvable->isNotEmpty(), 'remaining' => $remaining, 'revisionCount' => $revision->count(),
            'pendingCount' => $pendingCount, 'checkingCount' => $checking->count(), 'workLabel' => $workLabel, 'targetUrl' => $targetUrl,
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
