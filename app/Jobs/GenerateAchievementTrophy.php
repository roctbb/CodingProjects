<?php

namespace App\Jobs;

use App\Achievement;
use App\Services\AchievementTrophyGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAchievementTrophy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 240;

    protected $achievementId;
    protected $force;

    public function __construct($achievementId, bool $force = false)
    {
        $this->achievementId = $achievementId;
        $this->onConnection(config('queue.default') === 'sync' ? 'database' : config('queue.default'));
        $this->force = $force;
    }

    public function handle(AchievementTrophyGenerator $generator): void
    {
        $achievement = Achievement::with('course', 'task')->find($this->achievementId);
        if (!$achievement) {
            return;
        }

        if (!$this->force && $achievement->trophyImageUrl()) {
            return;
        }

        try {
            $generator->generateForAchievement($achievement);
        } catch (\Throwable $e) {
            Log::warning('Queued achievement trophy generation failed', [
                'achievement_id' => $achievement->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }
}
