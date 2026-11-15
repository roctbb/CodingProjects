<?php

namespace Tests\Feature;

use App\Solution;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\DeadlineFeatureCase;

class SingleReviewSkipTest extends DeadlineFeatureCase
{
    public function test_json_skip_and_student_recheck_preserve_the_grade_and_reject_an_old_queue_token(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        DB::table('tasks')->where('id',11)->update(['is_code'=>true]);
        $solution=$this->solution(mark:5);
        DB::table('solutions')->where('id',$solution->id)->update(['recheck_requested'=>true,'recheck_comment'=>'Первый запрос на проверку решения.']);
        $solution=$solution->fresh();
        $token=$solution->reviewToken();
        $this->actingAs(User::findOrFail(2))->postJson($this->endpoint($solution),['review_token'=>$token])->assertOk()->assertJsonPath('skipped',true);
        $url='/insider/courses/1/tasks/11/solution/'.$solution->id.'/recheck';
        $this->actingAs(User::findOrFail(1))->postJson($url,['recheck_comment'=>'Проверьте, пожалуйста, мой разбор решения.'])->assertOk()->assertJsonPath('requested',true);
        $this->postJson($url,['recheck_comment'=>'Повторный запрос не меняет предыдущий.'])->assertOk()->assertJsonPath('requested',true);
        $this->assertSame(5,$solution->fresh()->mark);
        $this->actingAs(User::findOrFail(2))->postJson($this->endpoint($solution),['review_token'=>$token])->assertConflict();
        $this->assertTrue((bool)$solution->fresh()->recheck_requested);
    }

    private function endpoint(Solution $solution, ?int $course = null): string
    {
        return '/insider/courses/'.($course ?? $solution->course_id).'/tasks/'.$solution->task_id.'/solution/'.$solution->id.'/skip-review';
    }

    public function test_single_skip_changes_only_the_selected_queue_flags_and_preserves_grades_and_other_work(): void
    {
        $target = $this->solution(mark: 5);
        $other = $this->solution('2026-11-14 09:00:00', 3, 11, 3);
        DB::table('solutions')->whereIn('id', [$target->id, $other->id])->update(['recheck_requested' => true, 'recheck_comment' => 'Пожалуйста, проверьте объяснение ещё раз.']);
        $target = $target->fresh();
        $before = (array) DB::table('solutions')->where('id', $target->id)->first();
        $otherBefore = (array) DB::table('solutions')->where('id', $other->id)->first();
        $referer = url('/insider/reviews?course=1&q=пример&review_status=recheck&page=2');
        $this->actingAs(User::findOrFail(2))->from($referer)->post($this->endpoint($target), ['review_token' => $target->reviewToken()])->assertRedirect($referer);
        $after = (array) DB::table('solutions')->where('id', $target->id)->first();
        foreach (['review_skipped', 'recheck_requested', 'recheck_comment', 'updated_at'] as $key) { unset($before[$key], $after[$key]); }
        $this->assertSame($before, $after);
        $this->assertSame($otherBefore, (array) DB::table('solutions')->where('id', $other->id)->first());
        $this->assertDatabaseHas('solutions', ['id' => $target->id, 'mark' => 5, 'review_skipped' => true, 'recheck_requested' => false]);
        $this->assertDatabaseCount('solutions', 2);
    }

    public function test_a_teacher_from_another_course_or_a_mismatched_route_cannot_skip_work(): void
    {
        $target = $this->solution(); $token = $target->fresh()->reviewToken();
        DB::table('users')->where('id', 5)->update(['role' => 'teacher']);
        $this->actingAs(User::findOrFail(5))->post($this->endpoint($target), ['review_token' => $token])->assertForbidden();
        $this->actingAs(User::findOrFail(1))->post($this->endpoint($target), ['review_token' => $token])->assertForbidden();
        $this->actingAs(User::findOrFail(2))->post($this->endpoint($target, 2), ['review_token' => $token])->assertNotFound();
        $this->assertFalse($target->fresh()->review_skipped);
        $this->assertNull($target->fresh()->mark);
    }

    public function test_a_replayed_skip_cannot_dismiss_a_new_student_recheck_request(): void
    {
        DB::table('tasks')->where('id', 11)->update(['is_code' => true]);
        $target = $this->solution(mark: 5)->fresh();
        $token = $target->reviewToken();
        $this->actingAs(User::findOrFail(2))->post($this->endpoint($target), ['review_token' => $token])->assertRedirect();
        // Repeating the already-completed action is harmless.
        $this->post($this->endpoint($target), ['review_token' => $token])->assertRedirect();
        $this->actingAs(User::findOrFail(1));
        $reason = 'Прошу проверить новое объяснение решения задачи.';
        $this->post('/insider/courses/1/tasks/11/solution/'.$target->id.'/recheck', ['recheck_comment' => $reason])->assertRedirect();
        $this->actingAs(User::findOrFail(2))->post($this->endpoint($target), ['review_token' => $token])->assertConflict();
        $this->post($this->endpoint($target))->assertConflict();
        $this->assertDatabaseHas('solutions', ['id' => $target->id, 'mark' => 5, 'review_skipped' => false,
            'recheck_requested' => true, 'recheck_comment' => $reason]);
    }

    public function test_single_skip_keeps_the_existing_csrf_boundary(): void
    {
        $target = $this->solution()->fresh();
        $environment = app()->environment();
        $this->withMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        try {
            $this->app->detectEnvironment(fn () => 'local');
            $this->actingAs(User::findOrFail(2))->post($this->endpoint($target), ['review_token' => $target->reviewToken()])->assertStatus(419);
        } finally {
            $this->app->detectEnvironment(fn () => $environment);
        }
        $this->assertFalse($target->fresh()->review_skipped);
    }
}
