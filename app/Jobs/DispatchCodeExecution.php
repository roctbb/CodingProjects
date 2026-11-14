<?php

namespace App\Jobs;

use App\CodeExecutionRun;
use App\Services\CodeChecking\RunnerClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchCodeExecution implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 20;
    public $timeout = 240;

    public function __construct(public string $executionId) {}

    public function backoff(): array
    {
        return [5, 15, 30, 60, 120, 300];
    }

    public function handle(RunnerClient $runner): void
    {
        $run = CodeExecutionRun::find($this->executionId);
        if (!$run || $run->finished_at || $run->dispatched_at) return;
        $claimed = CodeExecutionRun::whereKey($run->id)->whereNull('finished_at')->whereNull('dispatched_at')
            ->where(fn ($query) => $query->whereNull('dispatch_lease_until')->orWhere('dispatch_lease_until', '<', now()))
            ->update(['dispatch_attempted_at' => now(), 'dispatch_lease_until' => now()->addMinutes(5)]);
        if (!$claimed) return;
        try {
            $run->refresh();
            $submission = $run->submission;
            if ($submission->preparation_state !== 'ready') {
                $run->update(['state' => 'preparing', 'progress' => ['phase' => 'preparing', 'completed' => 0, 'total' => 0]]);
                try {
                    app(\App\Services\CodeChecking\Repositories::class)->capture($submission, $run->snapshot);
                    app(\App\Services\CodeChecking\SubmissionPayload::class)->validate(['mode' => 'project', 'language' => $submission->language,
                        'text' => $submission->answer_text ?? '', 'files' => $submission->files], array_replace($run->snapshot, ['submission_modes' => ['project']]));
                    $submission->update(['files' => app(\App\Services\CodeChecking\Submissions::class)->executionFiles($submission, $run->snapshot)]);
                    CheckCodeIntegrity::dispatch($submission->id);
                } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
                    if (!in_array($exception->getStatusCode(), [400, 404, 422], true)) throw $exception;
                    $submission->update(['preparation_state' => 'failed']);
                    $run->update(['state' => 'error', 'comment' => $exception->getMessage(), 'finished_at' => now()]);
                    return;
                }
            }
            if ($run->snapshot['check_type'] === 'manual') {
                $run->update(['state' => 'awaiting_review', 'dispatched_at' => now(),
                    'progress' => ['phase' => 'awaiting_review', 'completed' => 0, 'total' => 0]]);
                return;
            }
            $runner->dispatch($run->id, $run->submission_id);
            $run->update(['dispatched_at' => now()]);
        } finally {
            CodeExecutionRun::whereKey($run->id)->update(['dispatch_lease_until' => null]);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $run = CodeExecutionRun::find($this->executionId);
        if ($run && !$run->finished_at && !$run->dispatched_at
            && (!$run->dispatch_lease_until || $run->dispatch_lease_until->isPast())) $run->update(['state' => 'error',
            'comment' => 'Не удалось подготовить или отправить решение. Преподаватель может повторить проверку.', 'finished_at' => now()]);
    }
}
