<?php

namespace Tests\Unit;

use App\Solution;
use App\Task;
use Carbon\Carbon;
use Tests\TestCase;

class SolutionAchievementEligibilityTest extends TestCase
{
    public function testAiAchievementRequiresFullRawScore()
    {
        $task = new Task([
            'max_mark' => 10,
            'generates_ai_achievement' => true,
        ]);

        $solution = (new Solution())->forceFill([
            'mark' => 10,
            'raw_mark' => 10,
            'submitted' => Carbon::now(),
        ]);
        $solution->setRelation('task', $task);
        $this->assertTrue($solution->isEligibleForAiAchievement());

        $partialSolution = (new Solution())->forceFill([
            'mark' => 9,
            'raw_mark' => 9,
            'submitted' => Carbon::now(),
        ]);
        $partialSolution->setRelation('task', $task);
        $this->assertFalse($partialSolution->isEligibleForAiAchievement());

        $boostedSolution = (new Solution())->forceFill([
            'mark' => 10,
            'raw_mark' => 9,
            'xp_booster_amount' => 1,
            'submitted' => Carbon::now(),
        ]);
        $boostedSolution->setRelation('task', $task);
        $this->assertFalse($boostedSolution->isEligibleForAiAchievement());
    }
}
