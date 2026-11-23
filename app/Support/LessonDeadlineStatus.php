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
        $deadlines = [];
        $actionable = $progress?->filter(fn ($row) => in_array($row['status'], ['todo', 'revision'], true))->pluck('task.id');
        foreach ($lesson->steps as $step) {
            foreach ($step->tasks as $task) {
                if (!$task->isVisible($student, $course)) continue;
                if ($actionable !== null ? !$actionable->contains($task->id) : ($task->isDone($student->id) || $task->isOnCheck($student->id))) continue;
                if ($deadline = $task->getDeadline($course->id, $student->id)) $deadlines[] = $deadline;
            }
        }

        return self::forCalendar($deadlines);
    }

    public static function forCalendar(iterable $deadlines): array
    {
        $now = now();
        $timestamp = $now->getTimestampMs();
        $first = null;
        foreach ($deadlines as $deadline) {
            $rule = $deadline instanceof DeadlineRule ? $deadline : DeadlineRule::fromLegacy($deadline);
            $stage = $rule->series[0] ?? null;
            if (!$stage) continue;

            $cutoff = Carbon::parse($stage['at'], 'UTC')->getTimestampMs() + (int) $rule->legacyBoundary;
            if ($first === null || $cutoff < $first['cutoff']) {
                $first = ['cutoff' => $cutoff, 'timezone' => $rule->ruleTimezone, 'day' => $rule->deadlineAt($stage)->format('Y-m-d')];
            }
        }

        $status = match (true) {
            $first === null => null,
            $timestamp >= $first['cutoff'] => 'overdue',
            $first['day'] === $now->copy()->setTimezone($first['timezone'])->format('Y-m-d') => 'today',
            default => null,
        };
        return ['status' => $status, 'first' => $first, 'now' => $timestamp];
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
