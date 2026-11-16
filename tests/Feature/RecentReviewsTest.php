<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class RecentReviewsTest extends DeadlineFeatureCase
{
    private function native(string $state, ?int $solution = null, int $course = 1, string $at = '2026-11-14 12:00:00', ?string $legacy = null): string
    {
        $id = (string) Str::uuid(); $run = (string) Str::uuid();
        DB::table('code_submissions')->insert(['id'=>$id,'current_run_id'=>$run,'course_id'=>$course,'task_id'=>11,'user_id'=>1,
            'request_id'=>(string)Str::uuid(),'payload_hash'=>str_repeat('a',64),'language'=>'python','files'=>'[]',
            'solution_id'=>$solution,'legacy_id'=>$legacy,'created_at'=>$at,'answer_text'=>'PRIVATE NATIVE ANSWER']);
        DB::table('code_execution_runs')->insert(['id'=>$run,'submission_id'=>$id,'snapshot'=>'{}','state'=>$state,
            'points'=>$state==='completed'?8:null,'comment'=>'PRIVATE CHECKER REPORT','created_at'=>$at]);
        return $id;
    }

    private function feed(string $query = '')
    {
        return $this->actingAs(User::find(2))->get('/insider/reviews?view=recent'.$query)->assertOk();
    }

    public function test_recent_feed_merges_native_and_legacy_without_duplicate_solutions_or_answer_text(): void
    {
        $graded = $this->solution(mark: 5);
        $native = $this->native('completed', $graded->id);
        $pending = $this->solution('2026-11-14 11:00:00', task: 12);
        DB::table('solutions')->where('id', $pending->id)->update(['text'=>'PRIVATE LEGACY ANSWER','comment'=>'PRIVATE FEEDBACK']);
        $response = $this->feed(); $items = $response->viewData('recentReviews');
        $this->assertSame(2, $items->total());
        $this->assertSame('submission', $items->first()->kind);
        $this->assertSame($native, $items->first()->item_id);
        $this->assertStringContainsString('attempt='.$native, $items->first()->url);
        $this->assertStringContainsString('attempt=solution-'.$pending->id, $items->last()->url);
        $response->assertSee('8 / 10 баллов')->assertSee('Зачтено 5 XP')->assertSee('Ожидает оценки');
        foreach (['PRIVATE NATIVE ANSWER','PRIVATE CHECKER REPORT','PRIVATE LEGACY ANSWER','PRIVATE FEEDBACK'] as $secret) $response->assertDontSee($secret);
        foreach ($items as $item) foreach (['text','comment','files','answer_text'] as $field) $this->assertObjectNotHasProperty($field, $item);
    }

    public function test_course_boundaries_archived_courses_and_role_permissions_apply_to_the_whole_feed(): void
    {
        DB::table('courses')->insert(['id'=>99,'program_id'=>1,'name'=>'PRIVATE COURSE','state'=>'started']);
        $this->native('completed', course:99);
        $this->native('testing');
        $response=$this->feed();$this->assertSame(1,$response->viewData('recentReviews')->total());$response->assertDontSee('PRIVATE COURSE');
        $this->assertSame(0,$this->feed('&course=99')->viewData('recentReviews')->total());
        DB::table('courses')->where('id',1)->update(['state'=>'ended']);
        $this->assertSame(1,$this->feed()->viewData('recentReviews')->total());
        $this->assertTrue($this->feed()->viewData('reviewCourses')->contains('id',1));
        $this->actingAs(User::find(1))->getJson('/insider/reviews?view=recent')->assertForbidden();
    }

    public function test_state_search_and_lesson_filters_cover_checks_pending_work_and_errors(): void
    {
        $error=$this->native('error');$checking=$this->native('testing');$this->native('completed');$this->solution(task:12);
        $this->assertSame([$error],$this->feed('&activity_status=error')->viewData('recentReviews')->pluck('item_id')->all());
        $this->assertSame([$checking],$this->feed('&activity_status=checking')->viewData('recentReviews')->pluck('item_id')->all());
        $this->assertSame(1,$this->feed('&activity_status=graded')->viewData('recentReviews')->total());
        $this->assertSame(1,$this->feed('&activity_status=pending')->viewData('recentReviews')->total());
        $this->assertSame(0,$this->feed('&lesson=2')->viewData('recentReviews')->total());
        DB::table('users')->where('id',1)->update(['name'=>'Мария Орлова %_!']);
        $this->assertSame(4,$this->feed('&q='.urlencode('орлова'))->viewData('recentReviews')->total());
        $this->assertSame(4,$this->feed('&q='.urlencode('%_!'))->viewData('recentReviews')->total());
        $this->assertSame(0,$this->feed('&q='.urlencode("%' OR 1=1 --"))->viewData('recentReviews')->total());
    }

    public function test_imported_legacy_attempts_are_not_counted_twice_and_pagination_keeps_filters(): void
    {
        $legacy=$this->solution(mark:7);$this->native('completed',$legacy->id,legacy:'OLD123');
        $this->assertSame(['solution'],$this->feed()->viewData('recentReviews')->pluck('kind')->all());
        for($n=0;$n<42;$n++)$this->native('completed',at:sprintf('2026-11-14 13:00:%02d',$n));
        $page=$this->feed('&course=1&activity_status=graded');
        $this->assertSame(43,$page->viewData('recentReviews')->total());$this->assertCount(40,$page->viewData('recentReviews'));
        $this->assertStringContainsString('activity_status=graded',$page->viewData('recentReviews')->nextPageUrl());
        $this->assertCount(3,$this->feed('&course=1&activity_status=graded&page=2')->viewData('recentReviews'));
        $this->actingAs(User::find(2))->getJson('/insider/reviews?view[]=recent')->assertUnprocessable();
    }

    public function test_mismatched_award_links_do_not_change_native_status_or_expose_a_score(): void
    {
        $pending = $this->solution(task:12);
        $native = $this->native('completed', $pending->id);
        $item = $this->feed('&review_status=recheck')->viewData('recentReviews')->firstWhere('item_id', $native);
        $this->assertSame('graded', $item->status);
        $this->assertNull($item->needs_review);
        $this->assertNull($item->xp);
        $this->assertSame(2, $this->feed()->viewData('recentReviews')->total());
    }
}
