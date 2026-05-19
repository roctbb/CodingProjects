<?php

namespace App\Jobs;

use App\Achievement;
use App\CourseActivity;
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
        $this->force = $force;
    }

    public function handle(AchievementTrophyGenerator $generator): void
    {
        $achievement = Achievement::with('course', 'task', 'user')->find($this->achievementId);
        if (!$achievement) {
            return;
        }

        if (!$this->force && $achievement->trophyImageUrl()) {
            return;
        }

        try {
            $generator->generateForAchievement($achievement);
            $achievement->refresh();
            $this->syncAchievementActivityPayload($achievement);
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

    private function syncAchievementActivityPayload(Achievement $achievement): void
    {
        CourseActivity::where('type', CourseActivity::TYPE_AI_ACHIEVEMENT_EARNED)
            ->where('solution_id', $achievement->solution_id)
            ->where('task_id', $achievement->task_id)
            ->where('user_id', $achievement->user_id)
            ->get()
            ->each(function ($activity) use ($achievement) {
                $payload = $activity->payload ?: [];
                $payload['achievement_title'] = $achievement->title;
                $payload['achievement_description'] = $achievement->description;
                $payload['icon_key'] = $achievement->icon_key;
                $payload['visual_key'] = $achievement->payload['visual_key'] ?? null;
                $payload['svg_icon'] = $achievement->payload['svg_icon'] ?? null;
                $payload['trophy_image'] = $achievement->payload['trophy_image'] ?? null;
                $activity->payload = $payload;
                $activity->save();
            });
    }
}
