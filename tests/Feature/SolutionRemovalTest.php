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
        (require database_path('migrations/2026_11_16_230000_link_solution_coin_rewards.php'))->up();
        DB::table('achievements')->delete();
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
        $credit=\App\CoinTransaction::register(1,10,'Reward',solutionId:$solution->id);
        $achievement=\App\Achievement::create(['user_id'=>1,'course_id'=>1,'task_id'=>11,'solution_id'=>$solution->id,'title'=>'Награда','description'=>'За решение']);
        CourseStudentPoints::recalculate(1,1);
        DB::unprepared("CREATE TRIGGER reject_progress_update BEFORE UPDATE ON course_student_points BEGIN SELECT RAISE(ABORT, 'TEST ROLLBACK'); END");
        $this->withoutExceptionHandling();
        try{$this->remove($source->id);$this->fail('Expected a failed recalculation');}catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('TEST ROLLBACK',$e->getMessage());}
        $this->assertDatabaseHas('solutions',['id'=>$solution->id]);$this->assertDatabaseHas('code_submissions',['id'=>$source->id]);
        $this->assertDatabaseHas('code_execution_runs',['id'=>$source->current_run_id]);
        $this->assertDatabaseHas('achievements',['id'=>$achievement->id]);
        $this->assertDatabaseMissing('coin_transactions',['reversal_of_id'=>$credit->id]);
    }

    public function test_old_task_reward_is_revoked_and_a_new_legitimate_solution_can_earn_it_again(): void
    {
        $solution=$this->solution(mark:10);
        $coin=DB::table('coin_transactions')->insertGetId(['user_id'=>1,'price'=>10,'comment'=>'Task #11','created_at'=>now(),'updated_at'=>now()]);
        $this->remove('solution-'.$solution->id)->assertOk();
        $this->assertDatabaseHas('coin_transactions',['id'=>$coin,'price'=>10]);
        $this->assertDatabaseHas('coin_transactions',['reversal_of_id'=>$coin,'price'=>-10]);
        $this->assertFalse(Task::find(11)->hasRewardableFullSolution(1));
        DB::table('tasks')->where('id',11)->update(['price'=>10]);
        $next=$this->solution();
        $this->actingAs(User::find(2))->postJson('/insider/courses/1/solution/'.$next->id,['mark'=>10,'comment'=>'Повторное решение'])->assertOk();
        $this->assertDatabaseHas('coin_transactions',['user_id'=>1,'comment'=>'Task #11','solution_id'=>$next->id]);
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

    public function test_removal_revokes_all_linked_rewards_and_deletes_only_its_achievement_and_trophy(): void
    {
        \Illuminate\Support\Facades\Storage::fake();
        $solution=$this->solution(mark:10);$other=$this->solution(mark:8,task:12);
        $credits=collect([10,20,30])->map(fn($amount)=>\App\CoinTransaction::register(1,$amount,'Reward '.$amount,solutionId:$solution->id));
        $unrelated=\App\CoinTransaction::register(1,99,'Other solution',solutionId:$other->id);
        $achievement=\App\Achievement::create(['user_id'=>1,'course_id'=>1,'task_id'=>11,'solution_id'=>$solution->id,'title'=>'Награда','description'=>'За решение','payload'=>['coin_reward'=>30]]);
        $path='achievement-trophies/achievement-'.$achievement->id.'-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::put($path,'image');$achievement->update(['payload'=>['coin_reward'=>30,'trophy_image'=>$path]]);
        $otherAchievement=\App\Achievement::create(['user_id'=>1,'course_id'=>1,'task_id'=>12,'solution_id'=>$other->id,'title'=>'Другая награда','description'=>'За другую задачу']);
        app(\App\Services\RewardNotices::class)->achievement($achievement);
        \App\CourseActivity::recordAchievementEarned($achievement);
        DB::table('classroom_bonus_awards')->insert(['user_id'=>1,'course_id'=>1,'task_id'=>11,'solution_id'=>$solution->id,'session_id'=>1,
            'amount'=>20,'coin_transaction_id'=>$credits[1]->id,'created_at'=>now(),'updated_at'=>now()]);
        $before=User::find(1)->balance();
        $this->remove('solution-'.$solution->id)->assertOk();
        $this->assertSame((int)$before-60,(int)User::find(1)->balance());
        $this->assertDatabaseMissing('achievements',['id'=>$achievement->id]);$this->assertDatabaseHas('achievements',['id'=>$otherAchievement->id]);
        \Illuminate\Support\Facades\Storage::assertMissing($path);
        $this->assertDatabaseMissing('classroom_bonus_awards',['solution_id'=>$solution->id]);
        $this->assertDatabaseMissing('course_activities',['solution_id'=>$solution->id]);
        foreach($credits as $credit){
            $this->assertDatabaseHas('coin_transactions',['id'=>$credit->id,'price'=>$credit->price]);
            $this->assertDatabaseHas('coin_transactions',['reversal_of_id'=>$credit->id,'price'=>-$credit->price]);
            $this->assertDatabaseMissing('reward_notices',['kind'=>'coins','source_key'=>(string)$credit->id]);
        }
        $this->assertDatabaseMissing('reward_notices',['kind'=>'achievement','source_key'=>(string)$achievement->id]);
        $this->assertDatabaseMissing('coin_transactions',['reversal_of_id'=>$unrelated->id]);
        $this->remove('solution-'.$solution->id)->assertNotFound();
        $this->assertSame(3,DB::table('coin_transactions')->whereNotNull('reversal_of_id')->count());
    }

    public function test_spent_coins_are_still_revoked_in_full_and_other_solution_receipts_stay_untouched(): void
    {
        DB::table('coin_transactions')->where('user_id',1)->delete();
        $solution=$this->solution(mark:10);$other=$this->solution(mark:10);
        $credit=\App\CoinTransaction::register(1,10,'Task #11',solutionId:$solution->id);
        $otherCredit=\App\CoinTransaction::register(1,5,'Other reward',solutionId:$other->id);
        \App\CoinTransaction::register(1,-30,'Purchase');
        $this->remove('solution-'.$solution->id)->assertOk();
        $this->assertSame(-25,(int)User::find(1)->balance());
        $this->assertDatabaseHas('coin_transactions',['reversal_of_id'=>$credit->id,'price'=>-10]);
        $this->assertDatabaseMissing('coin_transactions',['reversal_of_id'=>$otherCredit->id]);
    }

    public function test_historical_manual_achievement_and_classroom_receipts_are_revoked_once(): void
    {
        $solution=$this->solution(mark:10);
        $achievement=\App\Achievement::create(['user_id'=>1,'course_id'=>1,'task_id'=>11,'solution_id'=>$solution->id,'title'=>'Старое достижение','description'=>'За решение','payload'=>['manual'=>true,'coin_reward'=>15]]);
        $coin=\App\CoinTransaction::register(1,15,'Achievement Task #11 User #1');
        $bonus=\App\CoinTransaction::register(1,20,'Classroom task #11 / course #1');
        DB::table('classroom_bonus_awards')->insert(['user_id'=>1,'course_id'=>1,'task_id'=>11,'solution_id'=>$solution->id,'session_id'=>1,
            'amount'=>20,'coin_transaction_id'=>$bonus->id,'created_at'=>now(),'updated_at'=>now()]);
        $this->remove('solution-'.$solution->id)->assertOk();
        $this->assertDatabaseHas('coin_transactions',['reversal_of_id'=>$coin->id,'price'=>-15]);
        $this->assertDatabaseHas('coin_transactions',['reversal_of_id'=>$bonus->id,'price'=>-20]);
        $this->assertDatabaseMissing('achievements',['id'=>$achievement->id]);
    }

    public function test_a_reward_for_another_submission_of_the_same_task_is_preserved(): void
    {
        $removed=$this->solution(mark:6);$other=$this->solution(mark:10);
        $credit=\App\CoinTransaction::register(1,10,'Task #11',solutionId:$other->id);
        $this->remove('solution-'.$removed->id)->assertOk();
        $this->assertDatabaseMissing('coin_transactions',['reversal_of_id'=>$credit->id]);
    }

    public function test_historical_audit_identifies_the_reward_after_a_later_recheck(): void
    {
        $first=$this->solution(mark:10);$other=$this->solution(mark:10);
        DB::table('solutions')->where('id',$first->id)->update(['checked'=>now()]);
        $coin=\App\CoinTransaction::register(1,10,'Task #11');$coin->created_at=now()->subDays(2);$coin->save();
        $event=\App\CourseActivity::recordSolutionChecked($first);$event->created_at=$coin->created_at;$event->save();
        $this->remove('solution-'.$first->id)->assertOk();
        $this->assertDatabaseHas('coin_transactions',['reversal_of_id'=>$coin->id,'price'=>-10]);
        $this->assertDatabaseHas('solutions',['id'=>$other->id,'mark'=>10]);
    }

    public function test_new_native_and_quiz_rewards_are_bound_to_the_actual_submission(): void
    {
        DB::table('tasks')->where('id',11)->update(['price'=>10]);
        $source=$this->native(state:'awaiting_review');
        app(\App\Services\CodeChecking\Grading::class)->apply($source,['points'=>10,'comment'=>'Верно']);
        $nativeSolution=$source->fresh()->solution_id;
        $this->assertDatabaseHas('coin_transactions',['comment'=>'Task #11','solution_id'=>$nativeSolution]);
        $this->remove($source->id)->assertOk();
        DB::table('tasks')->where('id',11)->update(['is_quiz'=>true,'is_code'=>false,'answer'=>'42']);
        $this->actingAs(User::find(1))->postJson('/insider/courses/1/tasks/11/solution',['text'=>'42'])->assertOk();
        $quiz=Solution::where('task_id',11)->firstOrFail();
        $this->assertDatabaseHas('coin_transactions',['comment'=>'Task #11','solution_id'=>$quiz->id]);
    }
}
