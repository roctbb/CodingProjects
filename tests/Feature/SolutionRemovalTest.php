<?php

namespace Tests\Feature;

use App\CodeExecutionRun;
use App\CodeSubmission;
use App\CourseStudentPoints;
use App\Services\CodeChecking\Integrity;
use App\Solution;
use App\Task;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class SolutionRemovalTest extends DeadlineFeatureCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('CREATE UNIQUE INDEX removal_lesson_stats ON lesson_student_stats(course_id,lesson_id,student_id)');
    }

    private function native(?Solution $solution = null, string $state = 'completed', ?string $legacy = null): CodeSubmission
    {
        $id = (string)Str::uuid(); $run = (string)Str::uuid();
        DB::table('code_submissions')->insert(['id'=>$id,'current_run_id'=>$run,'course_id'=>1,'task_id'=>11,'user_id'=>1,
            'request_id'=>(string)Str::uuid(),'payload_hash'=>str_repeat('a',64),'language'=>'python','files'=>'[]',
            'solution_id'=>$solution?->id,'legacy_id'=>$legacy,'created_at'=>now()]);
        DB::table('code_execution_runs')->insert(['id'=>$run,'submission_id'=>$id,'snapshot'=>'{}','state'=>$state,'created_at'=>now()]);
        if ($solution && $legacy === null) DB::table('solutions')->where('id',$solution->id)->update(['text'=>url('/insider/code-submissions/'.$id)]);
        return CodeSubmission::findOrFail($id);
    }

    private function remove(string $attempt, array $extra = [])
    {
        return $this->actingAs(User::find(2))->deleteJson('/insider/courses/1/tasks/11/attempts/'.$attempt, $extra+['confirm'=>true]);
    }

    public function test_removing_best_grade_recalculates_progress_and_keeps_the_other_attempt(): void
    {
        $other=$this->solution(mark:6);$best=$this->solution(mark:10);
        CourseStudentPoints::recalculate(1,1);$before=User::find(1)->score();
        $response=$this->remove('solution-'.$best->id)->assertOk()->assertJson(['deleted'=>true]);
        $this->assertStringNotContainsString('attempt=', $response->json('redirect_url'));
        $this->assertDatabaseMissing('solutions',['id'=>$best->id]);$this->assertDatabaseHas('solutions',['id'=>$other->id,'mark'=>6]);
        $this->assertDatabaseHas('course_student_points',['course_id'=>1,'student_id'=>1,'points'=>6]);
        $this->assertDatabaseHas('lesson_student_stats',['course_id'=>1,'student_id'=>1,'lesson_id'=>1,'points'=>6]);
        $this->assertLessThan($before,User::find(1)->score());
        $this->remove('solution-'.$best->id)->assertNotFound();
    }

    public function test_native_work_its_checks_and_related_rows_are_removed_and_late_integrity_cannot_recreate_them(): void
    {
        $solution=$this->solution(mark:8);$source=$this->native($solution);$stale=clone $source;
        DB::table('code_integrity_reports')->insert(['submission_id'=>$source->id,'state'=>'completed']);
        DB::table('code_fingerprints')->insert(['submission_id'=>$source->id,'task_id'=>11,'user_id'=>1,'fingerprint'=>'0123456789abcdef']);
        DB::table('code_attempt_credits')->insert(['submission_id'=>$source->id,'task_id'=>11,'user_id'=>1,'expires_at'=>now()->addHour(),'created_at'=>now()]);
        DB::table('reward_notices')->insert(['user_id'=>1,'kind'=>'xp','source_key'=>$source->current_run_id,'payload'=>'{}','created_at'=>now()]);
        DB::table('code_test_reports')->insert(['id'=>(string)Str::uuid(),'code_id'=>$source->id,'task_id'=>11,'course_id'=>1,'user_id'=>1,
            'started_at'=>now()->timestamp,'payload_hash'=>str_repeat('b',64),'report'=>'{}','created_at'=>now()]);
        $this->remove($source->id)->assertOk();
        foreach(['code_submissions'=>['id'=>$source->id],'solutions'=>['id'=>$solution->id],
            'code_execution_runs'=>['submission_id'=>$source->id],'code_integrity_reports'=>['submission_id'=>$source->id],
            'code_fingerprints'=>['submission_id'=>$source->id],'code_attempt_credits'=>['submission_id'=>$source->id],
            'code_test_reports'=>['code_id'=>$source->id]] as $table=>$key)$this->assertDatabaseMissing($table,$key);
        $this->assertDatabaseMissing('reward_notices',['source_key'=>$source->current_run_id]);
        app(Integrity::class)->check($stale);
        $this->assertDatabaseMissing('code_integrity_reports',['submission_id'=>$source->id]);
        $this->actingAs(User::find(2))->getJson('/insider/code-submissions/'.$source->id)->assertNotFound();
        $this->assertSame(0,$this->actingAs(User::find(2))->get('/insider/reviews?view=recent')->viewData('recentReviews')->total());
    }

    public function test_an_ungraded_native_submission_can_be_removed_without_creating_a_zero_grade(): void
    {
        $solution=$this->solution();$source=$this->native($solution,state:'awaiting_review');
        $this->remove('solution-'.$solution->id)->assertOk();
        $this->assertSame(0,Solution::count());$this->assertDatabaseMissing('code_submissions',['id'=>$source->id]);
    }

    public function test_shared_archive_is_kept_until_its_last_historical_attempt_is_deleted(): void
    {
        $first=$this->solution(mark:5);$second=$this->solution(mark:8);
        DB::table('solutions')->whereIn('id',[$first->id,$second->id])->update(['text'=>'https://paste.geekclass.ru/?id=OLD_12','geekpaste_code_id'=>'OLD_12']);
        $source=$this->native($first,legacy:'OLD_12');
        $this->remove('solution-'.$first->id)->assertOk();
        $this->assertDatabaseHas('code_submissions',['id'=>$source->id,'solution_id'=>$second->id]);
        $this->assertDatabaseHas('solutions',['id'=>$second->id,'mark'=>8]);
        $unrelated=$this->solution(mark:3);DB::table('solutions')->where('id',$unrelated->id)->update(['text'=>'https://paste.geekclass.ru/?id=OLDX12']);
        $this->remove('solution-'.$second->id)->assertOk();
        $this->assertDatabaseMissing('code_submissions',['id'=>$source->id]);$this->assertDatabaseHas('solutions',['id'=>$unrelated->id]);
    }

    public function test_permissions_scope_confirmation_and_redirect_are_validated_before_deletion(): void
    {
        $solution=$this->solution(mark:10);$path='/insider/courses/1/tasks/11/attempts/solution-'.$solution->id;
        $this->actingAs(User::find(1))->deleteJson($path,['confirm'=>true])->assertForbidden();
        DB::table('courses')->insert(['id'=>99,'program_id'=>1,'name'=>'Other course','state'=>'started']);
        $this->actingAs(User::find(2))->deleteJson(str_replace('/courses/1/','/courses/99/',$path),['confirm'=>true])->assertForbidden();
        $this->actingAs(User::find(2))->deleteJson(str_replace('/tasks/11/','/tasks/12/',$path),['confirm'=>true])->assertNotFound();
        $this->actingAs(User::find(2))->deleteJson($path)->assertUnprocessable();
        $this->remove('solution-'.$solution->id,['return_to'=>'//evil.example'])->assertUnprocessable();
        $this->assertDatabaseHas('solutions',['id'=>$solution->id]);
        DB::table('courses')->where('id',1)->update(['state'=>'ended']);
        $this->actingAs(User::find(2))->delete($path,['confirm'=>1,'return_to'=>'/insider/reviews?view=recent&course=1'])->assertRedirect('/insider/reviews?view=recent&course=1');
    }

    public function test_running_check_is_protected_and_a_transaction_failure_restores_all_rows(): void
    {
        $solution=$this->solution(mark:8);$source=$this->native($solution,'running');
        $this->remove($source->id)->assertConflict();$this->assertDatabaseHas('solutions',['id'=>$solution->id]);
        DB::table('code_execution_runs')->where('id',$source->current_run_id)->update(['state'=>'completed']);
        CourseStudentPoints::recalculate(1,1);
        DB::unprepared("CREATE TRIGGER reject_progress_update BEFORE UPDATE ON course_student_points BEGIN SELECT RAISE(ABORT, 'TEST ROLLBACK'); END");
        $this->withoutExceptionHandling();
        try{$this->remove($source->id);$this->fail('Expected a failed recalculation');}catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('TEST ROLLBACK',$e->getMessage());}
        $this->assertDatabaseHas('solutions',['id'=>$solution->id]);$this->assertDatabaseHas('code_submissions',['id'=>$source->id]);
        $this->assertDatabaseHas('code_execution_runs',['id'=>$source->current_run_id]);
    }

    public function test_coin_receipts_survive_and_prevent_a_second_task_reward_after_removal(): void
    {
        $solution=$this->solution(mark:10);
        $coin=DB::table('coin_transactions')->insertGetId(['user_id'=>1,'price'=>10,'comment'=>'Task #11','created_at'=>now(),'updated_at'=>now()]);
        $this->remove('solution-'.$solution->id)->assertOk();
        $this->assertDatabaseHas('coin_transactions',['id'=>$coin,'price'=>10]);
        $this->assertTrue(Task::find(11)->hasRewardableFullSolution(1));
    }

    public function test_late_result_and_achievement_jobs_cannot_restore_a_deleted_solution(): void
    {
        $solution=$this->solution(mark:8);$source=$this->native($solution);
        DB::table('code_execution_runs')->where('id',$source->current_run_id)->update(['snapshot'=>'{"points":10}']);
        $run=CodeExecutionRun::findOrFail($source->current_run_id);
        $this->remove($source->id)->assertOk();
        try {
            app(\App\Services\CodeChecking\Results::class)->accept($run,['execution_id'=>$run->id,'state'=>'done','points'=>8,'comment'=>'Late callback'],str_repeat('c',64));
            $this->fail('Late callback must not recreate a deleted run');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {$this->assertSame(CodeExecutionRun::class,$e->getModel());}
        $generator=new \App\Services\SolutionAchievementGenerator(\Mockery::mock(\App\Services\ChatGptService::class));
        $this->assertNull($generator->createForSolution($solution,['title'=>'Хорошая работа','description'=>'Удалённая попытка не получает награду.']));
        $this->assertSame(0,Solution::count());
    }
}
