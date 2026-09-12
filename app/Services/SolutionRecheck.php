<?php

namespace App\Services;

use App\Solution;
use App\Notifications\NewSolution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SolutionRecheck
{
    public function status(Solution $solution): array
    {
        $requested = (bool) $solution->recheck_requested;
        $reason = null;
        if (!$solution->task || !$solution->task->is_code || !$solution->submitted || $solution->mark === null) {
            $reason = 'Дождитесь результата проверки решения.';
        } elseif ($solution->task->isBlocked($solution->user_id, $solution->course_id)) {
            $reason = 'Задача заблокирована преподавателем.';
        } elseif (Solution::where('task_id', $solution->task_id)
            ->where('course_id', $solution->course_id)->where('user_id', $solution->user_id)
            ->whereNotNull('submitted')->where('mark', '>=', $solution->task->max_mark)->exists()) {
            $reason = 'Задача уже зачтена на полный балл.';
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
            // The web form and GeekPaste API share a lock: retries must not notify twice.
            $locked = Solution::whereKey($solution->id)->lockForUpdate()->firstOrFail();
            $status = $this->status($locked);
            if (!$status['available']) {
                return $status;
            }
            $locked->recheck_requested = true;
            $locked->recheck_comment = trim($comment);
            $locked->review_skipped = false;
            $locked->save();
            Notification::send($locked->course->teachers,
                (new NewSolution($locked))->delay(now()->addSecond())->afterCommit());
            return $this->status($locked);
        });
    }
}
