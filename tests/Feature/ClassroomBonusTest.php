<?php
namespace Tests\Feature;

use App\CodeSubmission;
use App\Services\CodeChecking\Grading;
use App\Solution;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class ClassroomBonusTest extends DeadlineFeatureCase
{
    private function attach(int $amount=5, string $type='task', int $target=11): int
    {
        $planner=DB::table('planner_courses')->where('course_id',1)->first();
        $slot=DB::table('planner_calendar_slots')->insertGetId(['planner_course_id'=>$planner->id,'local_date'=>'2026-11-14','start_time'=>'14:00',
            'duration_minutes'=>90,'position'=>0,'origin'=>'manual','occurrence_key'=>(string)Str::uuid(),'state'=>'available']);
        $session=DB::table('planner_sessions')->insertGetId(['planner_course_id'=>$planner->id,'calendar_slot_id'=>$slot,'title'=>'Практика']);
        $provider=DB::table('planner_providers')->where('key','codingprojects')->value('id');
        $ref=DB::table('planner_resource_refs')->insertGetId(['provider_id'=>$provider,'resource_type'=>$type,'external_id'=>(string)$target,
            'title'=>'Задача на занятии','public_metadata'=>'{}']);
        return DB::table('planner_session_resources')->insertGetId(['session_id'=>$session,'resource_ref_id'=>$ref,'layer'=>'plan',
            'resource_snapshot'=>json_encode(['provider_key'=>'codingprojects','type'=>$type,'external_id'=>(string)$target]),'class_bonus_gc'=>$amount]);
    }
    public function test_exact_window_partial_solutions_and_retries_do_not_farm_coins(): void
    {
        $this->attach();
        $this->solution('2026-11-14 13:59:59',5);
        $this->solution('2026-11-14 14:00:00',5);
        $this->assertDatabaseCount('classroom_bonus_awards',0);
        $accepted=$this->solution('2026-11-14 14:30:00',10);
        $this->assertDatabaseHas('classroom_bonus_awards',['user_id'=>1,'task_id'=>11,'amount'=>5]);
        $accepted->save(); $this->solution('2026-11-14 14:40:00',10);
        $this->solution('2026-11-14 15:30:00',10,user:3);
        $this->assertDatabaseCount('classroom_bonus_awards',1);
        $this->assertEquals(5,DB::table('coin_transactions')->where('comment','like','Classroom task%')->sum('price'));
    }
    public function test_bonus_and_deadline_are_frozen_before_late_manual_review(): void
    {
        $entry=$this->attach(7); $this->policy(stages:[['deadline_date'=>'2026-11-13','retained_percent'=>70]]);
        $pending=$this->solution('2026-11-14 14:30:00');
        DB::table('planner_session_resources')->where('id',$entry)->update(['class_bonus_gc'=>100]);
        Carbon::setTestNow(now()->addDays(3));
        $pending=$pending->fresh(); $pending->applyDeadlinePenalty(10); $pending->save();
        $this->assertEquals(7,$pending->mark);
        $this->assertDatabaseHas('classroom_bonus_awards',['solution_id'=>$pending->id,'amount'=>7]);
    }
    public function test_previously_solved_excluded_unscheduled_and_non_enrolled_work_are_ineligible(): void
    {
        $this->attach(); $this->solution('2026-11-14 13:00:00',10); $this->solution('2026-11-14 14:30:00',10);
        DB::table('planner_calendar_slots')->update(['state'=>'excluded']);
        $this->solution('2026-11-14 14:30:00',10,user:3);
        DB::table('planner_calendar_slots')->update(['state'=>'available','start_time'=>null]);
        $this->solution('2026-11-14 14:30:00',10,user:4);
        $this->assertDatabaseCount('classroom_bonus_awards',0);
    }
    public function test_attached_lesson_covers_tasks_and_native_checks_use_original_submission_time(): void
    {
        $entry=$this->attach(6,'lesson',1);
        $submission=CodeSubmission::create(['id'=>(string)Str::uuid(),'user_id'=>1,'course_id'=>1,'task_id'=>11,'mode'=>'files','language'=>'python',
            'files'=>[],'request_id'=>(string)Str::uuid(),'payload_hash'=>str_repeat('a',64),'current_run_id'=>(string)Str::uuid(),'created_at'=>Carbon::parse('2026-11-14 14:10:00','Europe/Moscow')]);
        $this->assertEquals(6,$submission->class_bonus_snapshot['amount']);
        DB::table('planner_session_resources')->where('id',$entry)->update(['class_bonus_gc'=>0]);
        Carbon::setTestNow(now()->addDays(2));
        DB::statement('CREATE UNIQUE INDEX bonus_test_lesson_stats ON lesson_student_stats(course_id,lesson_id,student_id)');
        app(Grading::class)->apply($submission,['points'=>10,'comment'=>'Верно']);
        $this->assertDatabaseHas('classroom_bonus_awards',['task_id'=>11,'user_id'=>1,'amount'=>6]);
        app(Grading::class)->apply($submission->fresh(),['points'=>10,'comment'=>'Верно']);
        $this->assertDatabaseCount('classroom_bonus_awards',1);
    }

    public function test_minimum_point_and_xp_booster_cannot_turn_an_incorrect_answer_into_a_bonus(): void
    {
        $this->attach(); DB::table('tasks')->where('id',11)->update(['max_mark'=>1]);
        $solution=$this->solution('2026-11-14 14:30:00',0);
        $this->assertEquals(1,$solution->mark);
        $solution->xp_booster_used_at=now(); $solution->applyDeadlinePenalty($solution->raw_mark); $solution->save();
        $this->assertDatabaseCount('classroom_bonus_awards',0);
        $submission=CodeSubmission::create(['id'=>(string)Str::uuid(),'user_id'=>3,'course_id'=>1,'task_id'=>11,'mode'=>'files','language'=>'python',
            'files'=>[],'request_id'=>(string)Str::uuid(),'payload_hash'=>str_repeat('a',64),'current_run_id'=>(string)Str::uuid(),
            'created_at'=>Carbon::parse('2026-11-14 14:10:00','Europe/Moscow')]);
        DB::statement('CREATE UNIQUE INDEX bonus_minimum_lesson_stats ON lesson_student_stats(course_id,lesson_id,student_id)');
        app(Grading::class)->apply($submission,['points'=>0,'comment'=>'Неверно']);
        $this->assertEquals(1,Solution::where('user_id',3)->first()->mark);
        $this->assertDatabaseCount('classroom_bonus_awards',0);
    }
}
