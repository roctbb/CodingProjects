<?php

namespace Tests\Unit;

use App\CourseActivity;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CoursePulseCalculationTest extends TestCase
{
    #[DataProvider('legacyActivityLevels')]
    public function testExistingActivityLevelsKeepTheirOriginalPulse(int $submissions, int $expected): void
    {
        $this->assertSame($expected, $this->pulse($submissions));
    }

    public static function legacyActivityLevels(): array
    {
        return [
            'no activity' => [0, 0],
            'one submission' => [1, 35],
            'two submissions' => [2, 58],
            'five submissions' => [5, 88],
            'ten submissions' => [10, 99],
            'previous maximum' => [15, 100],
        ];
    }

    public function testExtraActivityRaisesPulseGraduallyBeyond100UpTo210(): void
    {
        $aboveOldMaximum = $this->pulse(20);
        $busy = $this->pulse(100);

        $this->assertGreaterThan(100, $aboveOldMaximum);
        $this->assertLessThan(110, $aboveOldMaximum);
        $this->assertGreaterThan($aboveOldMaximum, $busy);
        $this->assertLessThan(210, $busy);
        $this->assertSame(210, $this->pulse(1000));
        $this->assertSame(210, $this->pulse(10000));
    }

    public function testAdditionalActivityNeverDecreasesPulseOrMakesItJumpNear100(): void
    {
        $previous = $this->pulse(10);
        foreach (range(11, 30) as $submissions) {
            $current = $this->pulse($submissions);
            $this->assertGreaterThanOrEqual($previous, $current);
            $this->assertLessThanOrEqual(1, $current - $previous);
            $previous = $current;
        }
    }

    private function pulse(int $submissions): int
    {
        $now = Carbon::parse('2026-10-16 12:00:00');
        $activities = collect();
        for ($index = 0; $index < $submissions; $index++) {
            $activities->push((object) [
                'type' => CourseActivity::TYPE_SOLUTION_SUBMITTED,
                'created_at' => $now->copy()->subMinutes(6),
            ]);
        }

        return (new ReflectionMethod(CourseActivity::class, 'pulseValueAt'))->invoke(null, $activities, $now);
    }
}
