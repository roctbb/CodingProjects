<?php

namespace Tests\Unit;

use App\Support\DeadlineRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DeadlineSummaryTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function summary(string $now, bool $legacy = false): string
    {
        Carbon::setTestNow(Carbon::parse($now, 'UTC'));
        $rule = DeadlineRule::fromSnapshot(['timezone'=>'Europe/Moscow','legacy'=>$legacy,'stages'=>[
            ['at'=>'2026-11-15 21:00:00','percent'=>70],
            ['at'=>'2026-11-22 21:00:00','percent'=>0],
        ]]);
        $html = Blade::render('<x-gc-deadline-chain :rule="$rule" />', compact('rule'));
        preg_match('/<button[^>]*class="gc-deadline-popover__trigger"[^>]*>(.*?)<\/button>/s', $html, $matches);
        return preg_replace('/\s+/u', ' ', strip_tags($matches[1]));
    }

    public function test_summary_keeps_only_current_penalty_and_next_deadline(): void
    {
        $before = $this->summary('2026-11-15 20:59:59');
        $this->assertStringContainsString('Без штрафа', $before);
        $this->assertStringContainsString('15.11 23:59', $before);
        $this->assertStringNotContainsString('22.11', $before);
        $current = $this->summary('2026-11-15 21:00:00');
        $this->assertStringContainsString('Сейчас −30%', $current);
        $this->assertStringContainsString('22.11 23:59', $current);
        $this->assertStringNotContainsString('15.11', $current);
        $after = $this->summary('2026-11-22 21:00:00');
        $this->assertStringContainsString('Сейчас −100%', $after);
        // The empty, hidden next-stage slot contains no stale date.
        $this->assertStringNotContainsString('22.11', $after);
    }

    public function test_legacy_boundary_matches_scoring_at_the_exact_cutoff(): void
    {
        $this->assertStringContainsString('Без штрафа', $this->summary('2026-11-15 21:00:00', true));
        $this->assertStringContainsString('Сейчас −30%', $this->summary('2026-11-15 21:00:01', true));
    }

    public function test_compact_calendar_shows_only_next_date_and_keeps_all_penalties_in_popup(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 12:00:00', 'UTC'));
        $rule = DeadlineRule::fromSnapshot(['timezone' => 'Europe/Moscow', 'stages' => [
            ['at' => '2026-11-15 21:00:00', 'percent' => 70],
            ['at' => '2026-11-22 21:00:00', 'percent' => 0],
        ]]);
        $html = Blade::render('<x-gc-deadline-chain :rule="$rule" :show-time="false" compact />', compact('rule'));
        preg_match('/<button[^>]*class="gc-deadline-popover__trigger"[^>]*>(.*?)<\/button>/s', $html, $matches);
        $this->assertStringContainsString('fa-calendar-days', $matches[1]);
        $this->assertStringContainsString('22.11', strip_tags($matches[1]));
        $this->assertStringNotContainsString('15.11', strip_tags($matches[1]));
        $this->assertStringContainsString('Сейчас −30%', $html);
        $this->assertStringContainsString('штраф 30%', $html);
        $this->assertStringContainsString('штраф 100%', $html);
        Carbon::setTestNow(Carbon::parse('2026-11-23 12:00:00', 'UTC'));
        $html = Blade::render('<x-gc-deadline-chain :rule="$rule" :show-time="false" compact />', compact('rule'));
        preg_match('/<button[^>]*class="gc-deadline-popover__trigger"[^>]*>(.*?)<\/button>/s', $html, $matches);
        $this->assertStringContainsString('22.11', strip_tags($matches[1]));
        $this->assertStringNotContainsString('Сроки прошли', $html);
    }
}
