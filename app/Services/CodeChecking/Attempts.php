<?php

namespace App\Services\CodeChecking;

use App\CodeSubmission;
use App\CodeTaskChecker;
use App\CoinTransaction;
use App\Services\Ai\AiSettings;
use App\Services\CoinWallet;
use App\Task;
use App\User;
use Illuminate\Support\Facades\DB;

class Attempts
{
    public const EXTRA_ATTEMPT_COST = 5;

    public function __construct(private AiSettings $aiSettings) {}

    private function exhausted(User $user, Task $task, CodeTaskChecker $checker): bool
    {
        $limit = $this->aiSettings->all()['submission_hourly_limit'] ?? $checker->config['gpt_rate_limit'] ?? 3;
        $submissions = CodeSubmission::query();
        $cutoff = $submissions->getModel()->fromDateTime(now()->subHour());

        return $submissions->where('user_id', $user->id)->where('task_id', $task->id)
            ->where('created_at', '>', $cutoff)->count() >= $limit;
    }

    private function credits(User $user, Task $task)
    {
        return DB::table('code_attempt_credits')->where('user_id', $user->id)->where('task_id', $task->id)
            ->whereNull('submission_id')->where('expires_at', '>', now());
    }

    // Called under the same per-user lock as submission deduplication.
    public function reserve(User $user, Task $task, CodeTaskChecker $checker): ?int
    {
        if (!in_array($checker->config['check_type'], Configuration::AI_TYPES, true) || !$this->exhausted($user, $task, $checker)) return null;
        $credit = $this->credits($user, $task)->orderBy('expires_at')->first();
        abort_unless($credit, 429, 'GPT submission limit reached.');
        return (int) $credit->id;
    }

    public function buy(User $user, Task $task, int $courseId): array
    {
        abort_unless($user->role === 'student' && !app(Access::class)->teacher($user, $courseId), 403);
        abort_if($task->isBlocked($user->id, $courseId), 403);
        return app(CoinWallet::class)->transaction($user->id, function ($locked) use ($task) {
            $checker = CodeTaskChecker::findOrFail($task->id);
            abort_unless(in_array($checker->config['check_type'], Configuration::AI_TYPES, true) && $this->exhausted($locked, $task, $checker), 409);
            if ($this->credits($locked, $task)->exists()) return ['available' => true, 'charged' => 0, 'balance' => (int) $locked->balance()];
            $price = $locked->geekPasteExtraAttemptCost();
            abort_if($locked->balance() < $price, 409, 'Insufficient coins.');
            if ($price > 0) CoinTransaction::register($locked->id, -$price, 'GeekExecutor extra attempt Task #'.$task->id);
            else $locked->consumeFreeGeekPasteResetToday();
            DB::table('code_attempt_credits')->insert(['user_id' => $locked->id, 'task_id' => $task->id,
                'expires_at' => now()->addHour(), 'created_at' => now()]);
            return ['available' => true, 'charged' => $price, 'balance' => (int) $locked->balance()];
        });
    }
}
