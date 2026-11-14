<?php
namespace Tests\Feature;

use App\Course;
use App\DeadlinePolicy;
use App\Solution;
use App\Task;
use App\User;
use Carbon\Carbon;
use App\Services\CourseDeadlineService;
use App\CoursePlanner\Models\PlannerCourse;
use App\CoursePlanner\Services\PlannerReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class DeadlinePoliciesTest extends DeadlineFeatureCase
{
    public function test_unlimited_stages_exact_boundaries_arbitrary_final_percentage_and_minimum_one(): void
    {
        $this->policy(stages: [
            ['deadline_date'=>'2026-11-15','retained_percent'=>70], ['deadline_date'=>'2026-11-16','retained_percent'=>40],
            ['deadline_date'=>'2026-11-17','retained_percent'=>0], ['deadline_date'=>'2026-11-18','retained_percent'=>20],
            ['deadline_date'=>'2026-11-19','retained_percent'=>85],
        ]);
        foreach (['2026-11-15 23:59:00'=>10, '2026-11-15 23:59:59'=>10, '2026-11-15 23:59:59.999999'=>10,
            '2026-11-16 00:00:00'=>7, '2026-11-16 23:59:59'=>7, '2026-11-17 00:00:00'=>4,
            '2026-11-18 00:00:00'=>1, '2026-11-19 00:00:00'=>2, '2027-01-01 00:00:00'=>9] as $at=>$mark) {
            $this->assertSame($mark, (int) $this->solution($at, 10)->mark, $at);
        }
        $this->assertDatabaseCount('deadline_stages', 5);
    }

    public function test_rules_freeze_on_submission_not_on_review_and_no_rule_is_also_frozen(): void
    {
        $before = $this->solution();
        $this->policy();
        $late = $this->solution('2026-11-16 12:00:00');
        $this->policy(extra: ['mode'=>'none', 'stages'=>[]]);
        $late = $late->fresh(); $late->applyDeadlinePenalty(10); $late->save();
        $before->applyDeadlinePenalty(10); $before->save();
        $this->assertSame(7, (int) $late->fresh()->mark);
        $this->assertSame(10, (int) $before->fresh()->mark);
        $late->xp_booster_used_at = now(); $late->applyDeadlinePenalty(10);
        $this->assertSame(7, (int) $late->mark);
        $late->deadline_penalty_waived_at = now(); $late->applyDeadlinePenalty(10);
        $this->assertSame(10, (int) $late->mark);
    }

    public function test_day_end_uses_course_timezone_across_daylight_saving_and_round_trips_date(): void
    {
        $this->policy(stages: [['deadline_date'=>'2026-03-08','retained_percent'=>70]], extra:['timezone'=>'America/New_York']);
        $rule = Task::find(11)->getDeadline(1);
        $this->assertSame('2026-03-09 04:00:00', $rule->cutoffAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-08 23:59 -04:00', $rule->deadlineAt()->format('Y-m-d H:i P'));
        $this->assertEquals(1, $rule->at(Carbon::parse('2026-03-09 03:59:59.999999', 'UTC'))->penalty);
        $this->assertEquals(0.7, $rule->at(Carbon::parse('2026-03-09 04:00:00', 'UTC'))->penalty);
        $editor = app(CourseDeadlineService::class)->editor(Course::find(1), 'task', 11);
        $this->assertSame('America/New_York', $editor['timezone']);
        $this->assertSame('2026-03-08', $editor['stages'][0]['deadline_date']);
        $this->actingAs(User::find(2))->getJson('/insider/courses/1/deadline-policies/task/11')
            ->assertOk()->assertJsonPath('stages.0.deadline_date', '2026-03-08');
    }

    public function test_shared_task_chain_editor_keeps_all_stages_when_setting_lesson_deadlines(): void
    {
        $this->policy('task', 11); $this->policy('task', 12);
        $service = app(CourseDeadlineService::class);
        $editor = $service->editor(Course::find(1), 'lesson', 1);
        $this->assertSame([
            ['deadline_date'=>'2026-11-15', 'retained_percent'=>70],
            ['deadline_date'=>'2026-11-22', 'retained_percent'=>0],
        ], $editor['stages']);
        $service->savePolicy(Course::find(1), 'lesson', 1, $editor + ['apply_to_tasks'=>true], 2);
        $this->assertCount(2, Task::find(11)->getDeadline(1)->series);
        $this->assertSame($editor['stages'], $service->editor(Course::find(1), 'lesson', 1)['stages']);
    }

    public function test_existing_timed_submission_snapshot_keeps_original_boundary_after_date_edit(): void
    {
        $policy = DeadlinePolicy::create(['course_id'=>1,'target_type'=>'task','target_id'=>11,'mode'=>'custom',
            'timezone'=>'Europe/Moscow','revision'=>1,'legacy'=>false]);
        $policy->stages()->create(['cutoff_at'=>'2026-11-15 07:00:00','retained_percent'=>70,'position'=>0]);
        $old = $this->solution('2026-11-15 10:00:00');
        $snapshot = $old->deadline_rule_snapshot;
        $this->policy(stages: [['deadline_date'=>'2026-11-15','retained_percent'=>70]]);
        $old->applyDeadlinePenalty(10); $old->save();
        $this->assertSame(7, (int) $old->fresh()->mark);
        $this->assertSame($snapshot, $old->fresh()->deadline_rule_snapshot);
        $this->assertSame(10, (int) $this->solution('2026-11-15 23:59:59', 10)->mark);
    }

    public function test_compatibility_date_endpoint_also_changes_percentage_at_following_midnight(): void
    {
        app(CourseDeadlineService::class)->set(Course::find(1), Task::find(11), '2026-11-15', 0.7);
        $this->assertSame(10, (int) $this->solution('2026-11-15 23:59:59', 10)->mark);
        $this->assertSame(7, (int) $this->solution('2026-11-16 00:00:00', 10)->mark);
    }

    public function test_lesson_inheritance_future_tasks_exemption_and_individual_override(): void
    {
        $this->policy('lesson', 1);
        $this->assertCount(2, Task::find(11)->getDeadline(1)->series);
        $this->policy('task', 11, extra:['mode'=>'none', 'stages'=>[]]);
        $this->assertNull(Task::find(11)->getDeadline(1));
        DB::table('tasks')->where('id',12)->update(['is_star'=>true]);
        $this->assertNull(Task::find(12)->getDeadline(1));
        $task = Task::find(11)->replicate(); $task->save();
        $this->assertCount(2, $task->getDeadline(1)->series);
        $this->policy('lesson',1,extra:['apply_to_tasks'=>true]);
        $this->assertCount(2, Task::find(11)->getDeadline(1)->series);
        $this->assertNull(Task::find(12)->getDeadline(1));
    }

    public function test_revisions_validation_and_course_authorization(): void
    {
        $url = '/insider/courses/1/deadline-policies/task/11';
        $this->actingAs(User::find(1))->getJson($url)->assertForbidden();
        $this->postJson($url,[])->assertForbidden();
        $this->actingAs(User::find(2));
        $this->getJson($url)->assertOk()->assertJsonPath('mode','inherit');
        $input = ['revision'=>0,'mode'=>'custom','timezone'=>'Europe/Moscow','stages'=>[
            ['deadline_date'=>'2026-11-15','retained_percent'=>70]]];
        $this->postJson($url,$input)->assertOk()->assertJsonPath('revision',1);
        $this->postJson($url,$input)->assertStatus(409);
        $input['revision']=1; $input['stages'][]=$input['stages'][0];
        $this->postJson($url,$input)->assertUnprocessable();
        $input['stages'][1]['deadline_date']='2026-11-16'; $input['stages'][1]['retained_percent']=101;
        $this->postJson($url,$input)->assertUnprocessable();
        $input['stages']=[['deadline_date'=>'2026-02-30','retained_percent'=>70]];
        $this->postJson($url,$input)->assertUnprocessable();
        $input['stages']=[['deadline_date'=>'2026-11-15T10:00','retained_percent'=>70]];
        $this->postJson($url,$input)->assertUnprocessable();
        $this->assertDatabaseCount('deadline_stages',1);
        DB::table('courses')->insert(['id'=>99,'program_id'=>999,'name'=>'Other']);
        DB::table('course_teachers')->insert(['course_id'=>99,'user_id'=>2]);
        $this->getJson('/insider/courses/99/deadline-policies/task/11')->assertNotFound();
        DB::table('course_teachers')->where('course_id',1)->delete();
        $this->post('/insider/courses/1/tasks/11/deadline',['deadline'=>'2026-11-20'])->assertForbidden();
    }

    public function test_calendar_shows_all_stages_but_coverage_and_workload_count_task_once(): void
    {
        $this->policy();
        $planner = PlannerCourse::where('course_id',1)->firstOrFail();
        $reader = app(PlannerReader::class);
        $this->assertCount(2,$reader->deadlines(Course::find(1),$planner));
        $summary = $reader->summary(Course::find(1),$planner,'plan');
        $this->assertSame(1,collect($summary['rows'])->where('lesson_id',1)->first()['deadline_count']);
        $this->assertEquals(10,collect($summary['weeks'])->sum(fn($week)=>$week['points']['codingprojects.xp']??0));
        $this->assertSame('2026-11-15', $reader->deadlines(Course::find(1),$planner)[0]['due_on']);
        $this->assertSame('23:59', $reader->deadlines(Course::find(1),$planner)[0]['time']);
        $this->actingAs(User::find(1))->get('/insider/courses/1/deadlines')->assertOk()->assertSee('70%')->assertSee('0%')->assertSee('23:59');
    }

    public function test_legacy_migration_freezes_old_boundaries_and_marks_and_absent_rules(): void
    {
        $migration = require database_path('migrations/2026_11_14_190000_create_deadline_policies.php');
        $migration->down();
        DB::table('task_deadlines')->insert(['course_id'=>1,'task_id'=>11,'expiration'=>'2026-11-15','penalty'=>0.7]);
        DB::table('solutions')->insert(['course_id'=>1,'task_id'=>11,'user_id'=>1,'submitted'=>'2026-11-16 10:00:00','text'=>'old','mark'=>6,'raw_mark'=>9]);
        DB::table('solutions')->insert(['course_id'=>1,'task_id'=>12,'user_id'=>1,'submitted'=>'2026-11-16 10:00:00','text'=>'old','mark'=>10]);
        $migration->up(); app()->forgetInstance(CourseDeadlineService::class);
        $this->assertEquals(6,Solution::where('task_id',11)->first()->mark);
        $rule = Task::find(11)->getDeadline(1);
        $this->assertSame('2026-11-15',$rule->expiration->format('Y-m-d'));
        $this->assertSame(10,(int)$this->solution('2026-11-16 00:00:00',10)->mark);
        $this->assertSame(7,(int)$this->solution('2026-11-16 00:00:01',10)->mark);
        $this->assertSame(['none'=>true],Solution::where('task_id',12)->first()->deadline_rule_snapshot);
    }

    public function test_planner_edits_and_undo_restore_whole_chain_without_reusing_revision(): void
    {
        $this->actingAs(User::find(2));
        $policy=$this->policy();
        $planner=PlannerCourse::where('course_id',1)->first();
        $api='/insider/courses/1/planner/api/commands/';
        $this->postJson($api.'set_deadline',['task_id'=>11,'mode'=>'custom','policy_revision'=>$policy->revision,
            'timezone'=>'Europe/Moscow','stages'=>[['deadline_date'=>'2026-12-01','retained_percent'=>25]],
            'revision'=>$planner->revision,'operation_id'=>(string)Str::uuid()])->assertOk();
        $change=DB::table('planner_changes')->orderByDesc('id')->first();
        $this->postJson($api.'undo',['change_id'=>$change->id,'revision'=>$planner->fresh()->revision,'operation_id'=>(string)Str::uuid()])->assertOk();
        $restored=DeadlinePolicy::find($policy->id);
        $this->assertGreaterThan(2,$restored->revision);
        $this->assertCount(2,$restored->stages);
        $this->assertSame(70,$restored->stages->first()->retained_percent);
    }
}
