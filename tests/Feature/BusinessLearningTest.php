<?php
namespace Tests\Feature;

use App\Course;
use App\CourseStudentPoints;
use App\LessonStudentStats;
use App\Solution;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DeadlineFeatureCase;

class BusinessLearningTest extends DeadlineFeatureCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('CREATE UNIQUE INDEX learning_course_stats ON course_student_points(course_id,student_id)');
        DB::statement('CREATE UNIQUE INDEX learning_lesson_stats ON lesson_student_stats(course_id,lesson_id,student_id)');
    }

    private function bonus(): void
    {
        $planner=DB::table('planner_courses')->where('course_id',1)->first();
        $slot=DB::table('planner_calendar_slots')->insertGetId(['planner_course_id'=>$planner->id,'local_date'=>'2026-11-14','start_time'=>'14:00',
            'duration_minutes'=>90,'position'=>0,'origin'=>'manual','occurrence_key'=>(string)Str::uuid(),'state'=>'available']);
        $session=DB::table('planner_sessions')->insertGetId(['planner_course_id'=>$planner->id,'calendar_slot_id'=>$slot,'title'=>'Practice']);
        $provider=DB::table('planner_providers')->where('key','codingprojects')->value('id');
        $ref=DB::table('planner_resource_refs')->insertGetId(['provider_id'=>$provider,'resource_type'=>'task','external_id'=>'11','title'=>'Task','public_metadata'=>'{}']);
        DB::table('planner_session_resources')->insert(['session_id'=>$session,'resource_ref_id'=>$ref,'layer'=>'plan','class_bonus_gc'=>5,
            'resource_snapshot'=>json_encode(['provider_key'=>'codingprojects','type'=>'task','external_id'=>'11'])]);
    }

    public function test_minimum_xp_does_not_complete_an_incorrect_quiz_or_award_coins_or_stickers(): void
    {
        $this->bonus();
        DB::table('tasks')->where('id',11)->update(['max_mark'=>1,'is_quiz'=>true,'answer'=>'right','is_hidden'=>false,'is_star'=>false,'price'=>10]);
        DB::table('tasks')->where('id','!=',11)->update(['is_hidden'=>true]);
        Carbon::setTestNow(Carbon::parse('2026-11-14 14:30:00','Europe/Moscow'));
        $balance=User::find(1)->balance();
        $this->actingAs(User::find(1))->postJson('/insider/courses/1/tasks/11/solution',['text'=>'wrong'])->assertOk()->assertJsonPath('mark',1);
        $solution=Solution::where('task_id',11)->firstOrFail();
        $this->assertSame(0,$solution->evaluated_mark);$this->assertFalse(Task::find(11)->isFullDone(1));
        $this->assertFalse(Task::find(11)->isDone(1));$this->assertFalse($solution->qualifiesForTaskPriceReward());
        $this->assertEquals($balance,User::find(1)->balance());$this->assertDatabaseCount('classroom_bonus_awards',0);
        $stats=LessonStudentStats::where('course_id',1)->where('lesson_id',1)->where('student_id',1)->firstOrFail();
        $this->assertEquals(1,$stats->points);$this->assertEquals(0,$stats->percent);
        $this->assertEquals(0,DB::table('reward_notices')->where('kind','sticker')->where('user_id',1)->count());
        $this->postJson('/insider/courses/1/tasks/11/solution',['text'=>'right'])->assertOk();
        $this->assertTrue(Task::find(11)->isFullDone(1));$this->assertDatabaseHas('classroom_bonus_awards',['user_id'=>1,'amount'=>5]);
    }

    public static function reviewOrders(): array
    {
        return [[true,10,0],[false,10,0],[true,5,5],[false,5,5]];
    }

    #[DataProvider('reviewOrders')]
    public function test_first_correct_submission_determines_bonus_regardless_of_review_order(bool $classFirst,int $earlyMark,int $bonus): void
    {
        $this->bonus();$early=$this->solution('2026-11-14 13:00:00');$class=$this->solution('2026-11-14 14:10:00');
        $grade=function ($solution,$mark) { $solution=$solution->fresh();$solution->applyDeadlinePenalty($mark);$solution->checked=now();$solution->save(); };
        if ($classFirst) {
            $grade($class,10);$this->assertDatabaseCount('classroom_bonus_awards',0);
            $this->assertTrue(Solution::whereKey($early->id)->pendingReview()->exists());
            $this->actingAs(User::find(2))->post('/insider/courses/1/tasks/11/solution/'.$early->id.'/skip-review')->assertConflict();
            $grade($early,$earlyMark);
        } else { $grade($early,$earlyMark);$grade($class,10); }
        $this->assertEquals($bonus,DB::table('classroom_bonus_awards')->where('user_id',1)->sum('amount'));
        $class->fresh()->save();$this->assertEquals($bonus,DB::table('classroom_bonus_awards')->where('user_id',1)->sum('amount'));
    }

    public function test_grade_and_bonus_roll_back_together_and_retry_awards_once(): void
    {
        $this->bonus();$pending=$this->solution('2026-11-14 14:10:00');
        DB::statement("CREATE TRIGGER fail_class_bonus BEFORE INSERT ON coin_transactions WHEN NEW.comment LIKE 'Classroom task%' BEGIN SELECT RAISE(ABORT,'bonus failed'); END");
        try { $pending->applyDeadlinePenalty(10);$pending->checked=now();$pending->save();$this->fail('Expected failed reward'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('bonus failed',$e->getMessage()); }
        $this->assertNull($pending->fresh()->mark);$this->assertNull($pending->fresh()->checked);
        $this->assertDatabaseCount('classroom_bonus_awards',0);DB::statement('DROP TRIGGER fail_class_bonus');
        $pending=$pending->fresh();$pending->applyDeadlinePenalty(10);$pending->save();$pending->save();
        $this->assertDatabaseCount('classroom_bonus_awards',1);
        $this->assertEquals(5,DB::table('coin_transactions')->where('comment','like','Classroom task%')->sum('price'));
    }

    public static function nativeResults(): array
    {
        return [[10, 'done', 0], [0, 'partially done', 5], [0, 'execution error', 5], [0, 'dispatch failure', 5]];
    }

    #[DataProvider('nativeResults')]
    public function test_earlier_native_submission_resolves_before_a_later_classroom_bonus(int $points, string $state, int $bonus): void
    {
        $this->bonus();
        $runId=(string)Str::uuid();
        $submission=\App\CodeSubmission::create(['id'=>(string)Str::uuid(),'user_id'=>1,'course_id'=>1,'task_id'=>11,
            'mode'=>'files','language'=>'python','files'=>[],'request_id'=>(string)Str::uuid(),'payload_hash'=>str_repeat('a',64),
            'current_run_id'=>$runId,'created_at'=>Carbon::parse('2026-11-14 13:00:00','Europe/Moscow')]);
        $run=\App\CodeExecutionRun::create(['id'=>$runId,'submission_id'=>$submission->id,'snapshot'=>['points'=>10,'name'=>'Task']]);
        $this->solution('2026-11-14 14:10:00',10);
        $this->assertDatabaseCount('classroom_bonus_awards',0);
        if ($state==='dispatch failure') (new \App\Jobs\DispatchCodeExecution($run->id))->failed(new \RuntimeException('Dispatch unavailable'));
        else {
            $result=['execution_id'=>$runId,'state'=>$state,'points'=>$points,'comment'=>'Checked'];
            app(\App\Services\CodeChecking\Results::class)->accept($run,$result,hash('sha256',json_encode($result)));
            app(\App\Services\CodeChecking\Results::class)->accept($run,$result,hash('sha256',json_encode($result)));
        }
        $this->assertEquals($bonus,DB::table('classroom_bonus_awards')->sum('amount'));
    }

    public function test_incorrect_one_point_answer_does_not_hide_a_later_pending_review(): void
    {
        DB::table('tasks')->where('id',11)->update(['max_mark'=>1]);
        $this->solution(mark:0);$pending=$this->solution('2026-11-14 10:10:00');
        $this->assertTrue(Solution::whereKey($pending->id)->pendingReview()->exists());
    }

    public function test_regrading_a_boosted_answer_updates_its_actual_evaluation(): void
    {
        $answer=$this->solution(mark:5);$answer->xp_booster_used_at=now();$answer->applyDeadlinePenalty(5);$answer->save();
        $this->actingAs(User::find(2))->post('/insider/courses/1/solution/'.$answer->id,['mark'=>0])->assertRedirect();
        $this->assertSame(0,$answer->fresh()->evaluated_mark);
        $this->assertFalse(Task::find(11)->isDone(1));
    }

    public function test_wrong_minimum_xp_keeps_deadlines_recheck_and_journal_consistent(): void
    {
        DB::table('tasks')->where('id',11)->update(['max_mark'=>1,'is_code'=>true,'is_hidden'=>false,'is_star'=>false,'generates_ai_achievement'=>true]);
        $answer=$this->solution(mark:0);$this->policy();
        $deadlines=app(\App\Queries\CourseDeadlineList::class)->forCourses(collect([Course::find(1)]),User::find(1));
        $this->assertTrue($deadlines->contains(fn ($deadline)=>$deadline->task_id===11 && !$deadline->is_done));
        $this->assertTrue(app(\App\Services\SolutionRecheck::class)->status($answer)['available']);
        $this->assertFalse($answer->isEligibleForAiAchievement());
        $runId=(string)Str::uuid();
        $submission=\App\CodeSubmission::create(['id'=>(string)Str::uuid(),'user_id'=>1,'course_id'=>1,'task_id'=>11,'solution_id'=>$answer->id,
            'mode'=>'files','language'=>'python','files'=>[],'request_id'=>(string)Str::uuid(),'payload_hash'=>str_repeat('a',64),'current_run_id'=>$runId]);
        \App\CodeExecutionRun::create(['id'=>$runId,'submission_id'=>$submission->id,'snapshot'=>['points'=>1],'points'=>0,'state'=>'completed','finished_at'=>now()]);
        $this->assertTrue(app(\App\Services\CodeChecking\SubmissionReview::class)->status($submission)['available']);
        $this->actingAs(User::find(2))->get('/insider/courses/1/assessments')->assertOk()->assertSee('data-assessment-state="partial"',false);
    }

    public function test_previously_skipped_early_answer_becomes_visible_when_needed_for_bonus(): void
    {
        $this->bonus();$early=$this->solution('2026-11-14 13:00:00');$early->review_skipped=true;$early->save();
        $this->solution('2026-11-14 14:10:00',10);
        $this->assertTrue($early->fresh()->isPendingReview());
        $this->assertTrue(Solution::whereKey($early->id)->pendingReview()->exists());
        $this->assertDatabaseCount('classroom_bonus_awards',0);
    }

    public function test_final_grade_uses_only_this_course_and_repeated_completion_preserves_snapshot(): void
    {
        DB::table('tasks')->where('id','!=',11)->update(['is_hidden'=>true]);
        DB::table('tasks')->where('id',11)->update(['max_mark'=>10,'is_hidden'=>false,'is_star'=>false]);
        DB::table('courses')->insert(['id'=>900,'program_id'=>1,'name'=>'Repeat','state'=>'started']);
        DB::table('course_students')->insert(['course_id'=>900,'user_id'=>1]);
        $this->solution(mark:10);$course=Course::find(900);
        foreach ($course->lessons as $lesson) $lesson->setStartDate($course,'2026-11-01');
        $this->assertEquals(0,$course->fresh()->points(User::find(1)));$course->end();
        $row=DB::table('completed_courses')->where('course_id',900)->first();$this->assertSame('D',$row->mark);
        $new=$this->solution(mark:10);$new->course_id=900;$new->save();$course->fresh()->end();
        $this->assertSame($row->id,DB::table('completed_courses')->where('course_id',900)->value('id'));
        $this->assertSame('D',DB::table('completed_courses')->where('course_id',900)->value('mark'));
    }

    public function test_failed_course_completion_preserves_all_old_results_and_course_state(): void
    {
        DB::table('course_students')->where('course_id',1)->delete();
        DB::table('course_students')->insert([['course_id'=>1,'user_id'=>1],['course_id'=>1,'user_id'=>3]]);
        DB::table('completed_courses')->delete();
        DB::table('completed_courses')->insert([['course_id'=>1,'user_id'=>1,'name'=>'Saved','mark'=>'S'],['course_id'=>1,'user_id'=>3,'name'=>'Saved','mark'=>'S']]);
        DB::statement("CREATE TRIGGER fail_course_finish BEFORE INSERT ON completed_courses WHEN NEW.user_id = 3 BEGIN SELECT RAISE(ABORT,'finish failed'); END");
        try { Course::find(1)->end();$this->fail('Expected failed finish'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('finish failed',$e->getMessage()); }
        $this->assertSame('started',Course::find(1)->state);
        $this->assertEquals(2,DB::table('completed_courses')->where('course_id',1)->where('mark','S')->count());
    }
}
