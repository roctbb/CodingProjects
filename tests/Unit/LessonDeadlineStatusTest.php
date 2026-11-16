<?php

namespace Tests\Unit;

use App\Support\LessonDeadlineStatus;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class LessonDeadlineStatusTest extends TestCase
{
    private function rule(float $retained = 70, bool $legacy = false): array
    {
        return ['legacy' => $legacy, 'timezone' => 'Europe/Moscow', 'stages' => [[
            'cutoff' => Carbon::parse('2026-11-16 21:00:00', 'UTC')->getTimestampMs(),
            'percent' => $retained, 'day' => '2026-11-16',
        ]]];
    }

    public function test_today_becomes_overdue_only_while_points_can_still_be_earned(): void
    {
        $this->assertNull(LessonDeadlineStatus::status([$this->rule()], Carbon::parse('2026-11-15 12:00', 'UTC')));
        $this->assertSame('today', LessonDeadlineStatus::status([$this->rule()], Carbon::parse('2026-11-16 12:00', 'UTC')));
        $after = Carbon::parse('2026-11-17 12:00', 'UTC');
        $this->assertSame('overdue', LessonDeadlineStatus::status([$this->rule()], $after));
        $this->assertNull(LessonDeadlineStatus::status([$this->rule(0)], $after));
        $this->assertSame('overdue', LessonDeadlineStatus::status([$this->rule(0.004)], $after));
        $this->assertNull(LessonDeadlineStatus::status([], $after));
    }

    public function test_native_and_legacy_boundaries_match_the_scoring_rules(): void
    {
        $boundary = Carbon::parse('2026-11-16 21:00:00', 'UTC');
        $this->assertSame('overdue', LessonDeadlineStatus::status([$this->rule()], $boundary));
        $this->assertNull(LessonDeadlineStatus::status([$this->rule(70, true)], $boundary));
        $this->assertSame('overdue', LessonDeadlineStatus::status([$this->rule(70, true)], $boundary->addMillisecond()));
    }

    public function test_calendar_day_uses_the_rule_timezone_and_overdue_takes_priority(): void
    {
        $now = Carbon::parse('2026-11-16 22:00', 'UTC');
        $future = $this->rule();
        $future['stages'][0]['day'] = '2026-11-17';
        $future['stages'][0]['cutoff'] += 86400000;
        $this->assertSame('today', LessonDeadlineStatus::status([$future], $now));
        $future['timezone'] = 'America/New_York';
        $this->assertNull(LessonDeadlineStatus::status([$future], $now));
        $future['timezone'] = 'Europe/Moscow';
        $this->assertSame('today', LessonDeadlineStatus::status([$this->rule(0), $future], $now));
        $this->assertSame('overdue', LessonDeadlineStatus::status([$future, $this->rule()], $now));
    }
}
