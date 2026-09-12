<?php

namespace Tests\Unit;

use App\Solution;
use App\Task;
use Carbon\Carbon;
use Tests\TestCase;

class SubmissionMinimumMarkTest extends TestCase
{
    private function submittedSolution(): Solution
    {
        $solution = (new Solution())->forceFill([
            'text' => 'https://paste.geekclass.ru/?id=TEST',
            'submitted' => Carbon::parse('2026-09-12 12:00:00'),
        ]);
        $solution->setRelation('task', (new Task())->forceFill(['max_mark' => 75]));
        return $solution;
    }

    public function testZeroFromGeekPasteAndLocalCheckerBecomesOneOnlyOnce(): void
    {
        foreach ([0 => 1, 1 => 1, 5 => 5, 75 => 75] as $received => $expected) {
            $solution = $this->submittedSolution();
            // Same two defensive boundaries as GeekPasteAPI -> Solution.
            $normalized = $solution->normalizeSubmittedMark($received);
            $solution->applyDeadlinePenalty($normalized);
            $this->assertSame($expected, $solution->raw_mark);
            $this->assertSame($expected, $solution->mark);
            $this->assertSame($expected, $solution->normalizeSubmittedMark($solution->mark));
        }
    }

    public function testNoSubmissionOrNoResultDoesNotCreatePoint(): void
    {
        $solution = $this->submittedSolution();
        $solution->submitted = null;
        $this->assertSame(0, $solution->normalizeSubmittedMark(0));
        $solution = $this->submittedSolution();
        $solution->text = '   ';
        $solution->applyDeadlinePenalty(0);
        $this->assertSame(0, $solution->mark);
        $solution = $this->submittedSolution();
        $solution->applyDeadlinePenalty(null);
        $this->assertNull($solution->mark);
        $this->assertNull($solution->raw_mark);
    }

    public function testDeadlinePenaltyCannotEraseAttemptPoint(): void
    {
        $solution = $this->submittedSolution();
        $deadline = (object) ['expiration' => Carbon::parse('2026-09-01'), 'penalty' => 0];
        $solution->applyDeadlinePenalty(0, $deadline);
        $this->assertSame(1, $solution->raw_mark);
        $this->assertSame(1, $solution->mark);
    }

    public function testExplicitAdministrativeZeroIsNotRewrittenByModel(): void
    {
        $solution = $this->submittedSolution();
        $solution->mark = 0;
        $solution->raw_mark = 0;
        $this->assertSame(0, $solution->mark);
    }
}
