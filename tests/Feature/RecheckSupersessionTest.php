<?php

namespace Tests\Feature;

use App\CodeExecutionRun;
use App\CodeSubmission;
use App\Services\CodeChecking\SubmissionReview;
use App\Services\SolutionRecheck;
use App\Solution;
use Illuminate\Support\Facades\DB;
use Tests\Support\DeadlineFeatureCase;

class RecheckSupersessionTest extends DeadlineFeatureCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tasks')->where('id', 11)->update(['is_code' => true]);
    }

    private function requested(string $at = '2026-11-14 10:00:00', int $task = 11, int $user = 1): Solution
    {
        $answer = $this->solution($at, 5, $task, $user);
        $answer->forceFill(['recheck_requested' => true, 'recheck_comment' => 'Please inspect the original solution.'])->save();
        return $answer;
    }

    public function test_new_full_evaluation_clears_only_older_requests_in_the_same_student_task_and_course(): void
    {
        $old = $this->requested();
        $sameTime = $this->requested('2026-11-14 11:00:00');
        $later = $this->requested('2026-11-14 12:00:00');
        $otherStudent = $this->requested(user: 3);
        $otherTask = $this->requested(task: 12);
        DB::table('courses')->insert(['id' => 900, 'program_id' => 1, 'name' => 'Another course', 'state' => 'started']);
        $otherCourse = $this->requested();
        DB::table('solutions')->where('id', $otherCourse->id)->update(['course_id' => 900]);

        $this->solution('2026-11-14 11:00:00', 10);

        foreach ([$old, $sameTime] as $answer) {
            $answer->refresh();
            $this->assertFalse($answer->recheck_requested);
            $this->assertSame(5, $answer->evaluatedMark());
            $this->assertSame(5, (int) $answer->mark);
            $this->assertSame('Please inspect the original solution.', $answer->recheck_comment);
        }
        foreach ([$later, $otherStudent, $otherTask, $otherCourse] as $answer) $this->assertTrue($answer->fresh()->recheck_requested);
    }

    public function test_full_checker_score_clears_requests_despite_deadline_xp_penalty_and_blocks_new_requests(): void
    {
        $old = $this->requested();
        $this->policy(stages: [['deadline_date' => '2026-11-13', 'retained_percent' => 50]]);
        $accepted = $this->solution('2026-11-14 11:00:00', 10);
        $this->assertSame(5, (int) $accepted->mark);
        $this->assertSame(10, $accepted->evaluatedMark());
        $this->assertFalse($old->fresh()->recheck_requested);
        $this->assertFalse(Solution::whereKey($old->id)->pendingReview()->exists());
        $legacy = app(SolutionRecheck::class)->request($old->fresh(), 'Please review this again.');
        $this->assertFalse($legacy['requested']);
        $this->assertFalse($legacy['available']);

        $submission = new CodeSubmission();
        $submission->forceFill(['course_id' => 1, 'task_id' => 11, 'user_id' => 1, 'solution_id' => $old->id]);
        $run = new CodeExecutionRun();
        $run->forceFill(['state' => 'completed', 'finished_at' => now(), 'points' => 5, 'snapshot' => ['points' => 10]]);
        $submission->setRelation('currentRun', $run);
        $native = app(SubmissionReview::class)->status($submission);
        $this->assertFalse($native['requested']);
        $this->assertFalse($native['available']);
        $this->assertSame('Задача уже зачтена на полный балл.', $native['message']);
    }

    public function test_partial_result_and_boosted_xp_do_not_cancel_requests_or_count_as_full_acceptance(): void
    {
        $old = $this->requested();
        $partial = $this->solution('2026-11-14 11:00:00', 5);
        $partial->xp_booster_used_at = now();
        $partial->applyDeadlinePenalty(5);
        $partial->save();
        $this->assertSame(10, (int) $partial->mark);
        $this->assertSame(5, $partial->evaluatedMark());
        $this->assertTrue($old->fresh()->recheck_requested);
        $this->assertTrue(Solution::whereKey($old->id)->pendingReview()->exists());
        $this->assertTrue(app(SolutionRecheck::class)->status($partial)['available']);
        $this->assertFalse(Solution::where('course_id', 1)->where('task_id', 11)->where('user_id', 1)->fullEvaluation(10)->exists());
    }

    public function test_cancellation_rolls_back_with_the_grade_and_never_fills_ungraded_answers(): void
    {
        $old = $this->requested();
        $pending = $this->solution('2026-11-14 09:00:00');
        DB::beginTransaction();
        $accepted = $this->solution('2026-11-14 11:00:00', 10);
        $this->assertFalse($old->fresh()->recheck_requested);
        DB::rollBack();
        $this->assertTrue($old->fresh()->recheck_requested);
        $this->assertFalse(Solution::whereKey($accepted->id)->exists());
        $this->assertNull($pending->fresh()->mark);
        $this->assertNull($pending->fresh()->checked);
    }
}
