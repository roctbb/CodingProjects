<?php

namespace App\Jobs;

use App\Services\SolutionAchievementGenerator;
use App\Solution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateSolutionAchievement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 120;

    protected $solutionId;

    public function __construct($solutionId)
    {
        $this->solutionId = $solutionId;
    }

    public function handle(SolutionAchievementGenerator $generator)
    {
        $solution = Solution::with('task.step.lesson', 'course', 'user')->find($this->solutionId);

        if (!$solution) {
            return;
        }

        $generator->generateForSolution($solution);
    }
}
