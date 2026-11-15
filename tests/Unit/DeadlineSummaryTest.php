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
        preg_match('/<summary[^>]*>(.*?)<\/summary>/s', $html, $matches);
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
}
