<?php

namespace App\Services;

use App\Solution;
use App\Task;
use App\Notifications\NewSolution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

class SolutionRecheck
{
    public static function taskRestriction(Task $task, int $course, int $student): ?string
    {
        if ($task->isBlocked($student, $course)) return 'Задача заблокирована преподавателем.';
        if (Schema::hasTable('solutions') && Solution::where('task_id', $task->id)
            ->where('course_id', $course)->where('user_id', $student)
            ->fullEvaluation((int)$task->max_mark)->exists()) {
            return 'Задача уже зачтена на полный балл.';
        }
        return null;
    }

    public function status(Solution $solution): array
    {
        $requested = (bool) $solution->recheck_requested;
        $reason = null;
        if (!$solution->task || !$solution->task->is_code || !$solution->submitted || $solution->mark === null) {
            $reason = 'Дождитесь результата проверки решения.';
        } else {
            $reason = self::taskRestriction($solution->task, $solution->course_id, $solution->user_id);
        }

        return [
            'state' => 'ok',
            'available' => !$requested && $reason === null,
            'requested' => $requested,
            'comment' => $requested ? (string) $solution->recheck_comment : null,
            'message' => $requested ? 'Запрос на перепроверку отправлен преподавателю.' : $reason,
        ];
    }

    public function request(Solution $solution, string $comment): array
    {
        return DB::transaction(function () use ($solution, $comment) {
            \App\User::whereKey($solution->user_id)->lockForUpdate()->firstOrFail();
            // Serialize requests from every local entry point; retries must not notify twice.
            $locked = Solution::whereKey($solution->id)->lockForUpdate()->firstOrFail();
            $status = $this->status($locked);
            if (!$status['available']) {
                return $status;
            }
            $locked->recheck_requested = true;
            $locked->recheck_comment = trim($comment);
            $locked->review_skipped = false;
            $locked->save();
            app(TaskChat::class)->recordRecheck($locked);
            Notification::send($locked->course->teachers,
                (new NewSolution($locked))->delay(now()->addSecond())->afterCommit());
            return $this->status($locked);
        });
    }
}
