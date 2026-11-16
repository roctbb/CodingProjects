<?php

namespace App\Support;

use App\Course;
use App\Lesson;
use App\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class LessonDeadlineStatus
{
    public static function forLesson(Lesson $lesson, Course $course, User $student, ?Collection $progress = null): array
    {
        $now = now();
        $rules = [];
        $actionable = $progress?->filter(fn ($row) => in_array($row['status'], ['todo', 'revision'], true))->pluck('task.id');
        foreach ($lesson->steps as $step) {
            foreach ($step->tasks as $task) {
                if (!$task->isVisible($student, $course)) continue;
                if ($actionable !== null ? !$actionable->contains($task->id) : ($task->isDone($student->id) || $task->isOnCheck($student->id))) continue;
                $deadline = $task->getDeadline($course->id);
                if (!$deadline) continue;
                $rule = $deadline instanceof DeadlineRule ? $deadline : DeadlineRule::fromLegacy($deadline);
                if (!$rule->series) continue;
                $rules[] = [
                    'legacy' => $rule->legacyBoundary,
                    'timezone' => $rule->ruleTimezone,
                    'stages' => array_map(fn ($stage) => [
                        'cutoff' => Carbon::parse($stage['at'], 'UTC')->getTimestampMs(),
                        'percent' => (float) $stage['percent'],
                        'day' => $rule->deadlineAt($stage)->format('Y-m-d'),
                    ], $rule->series),
                ];
            }
        }

        return ['status' => self::status($rules, $now), 'rules' => $rules, 'now' => $now->getTimestampMs()];
    }

    public static function status(array $rules, CarbonInterface $now): ?string
    {
        $today = false;
        $timestamp = $now->getTimestampMs();
        foreach ($rules as $rule) {
            $retained = 100;
            $passed = false;
            $dueToday = false;
            $localDay = $now->copy()->setTimezone($rule['timezone'])->format('Y-m-d');
            foreach ($rule['stages'] as $stage) {
                if ($rule['legacy'] ? $timestamp > $stage['cutoff'] : $timestamp >= $stage['cutoff']) {
                    $retained = $stage['percent'];
                    $passed = true;
                }
                $dueToday = $dueToday || $stage['day'] === $localDay;
            }
            if ($retained <= 0) continue;
            if ($passed) return 'overdue';
            $today = $today || $dueToday;
        }

        return $today ? 'today' : null;
    }
}
