<?php

namespace Tests\Feature;

use App\Course;
use App\CourseSubgroup;
use App\Services\CourseDeadlineService;
use App\Services\CourseSubgroups;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\DeadlineFeatureCase;

class CourseSubgroupIntegrationTest extends DeadlineFeatureCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('CREATE UNIQUE INDEX subgroup_test_course_points ON course_student_points(course_id,student_id)');
        DB::statement('CREATE UNIQUE INDEX subgroup_test_lesson_points ON lesson_student_stats(course_id,lesson_id,student_id)');
        (require database_path('migrations/2026_11_17_120000_create_course_subgroups.php'))->up();
        app()->forgetInstance(CourseSubgroups::class);
        app()->forgetInstance(CourseDeadlineService::class);
        CourseSubgroup::create(['id'=>1,'course_id'=>1,'name'=>'Подгруппа А']);
        CourseSubgroup::create(['id'=>2,'course_id'=>1,'name'=>'Подгруппа Б']);
        app(CourseSubgroups::class)->assign(Course::find(1),[1,3],1,2);
        app(CourseSubgroups::class)->assign(Course::find(1),[4],2,2);
        $this->actingAs(User::find(2));
    }

    public function test_ranking_and_journal_have_one_validated_roster(): void
    {
        foreach ([['teacher',2,2,[1,3]], ['student',1,2,[1,3]]] as [$role,$actor,$requested,$expected]) {
            $this->actingAs(User::find($actor));
            $this->get('/insider/courses/1?subgroup='.$requested)->assertOk()
                ->assertViewHas('students',fn ($students) => $students->pluck('id')->sort()->values()->all() === ($role==='teacher' ? [4] : $expected));
        }
        $this->actingAs(User::find(2))->get('/insider/courses/1/assessments?subgroup=1')->assertOk()
            ->assertViewHas('course', fn ($course) => $course->students->pluck('id')->sort()->values()->all()===[1,3]);
    }

    public function test_report_comparison_aggregates_underlying_results_and_refuses_other_student_details(): void
    {
        $this->solution('2026-11-14 10:00:00',10,11,1);
        $response=$this->get('/insider/courses/1/report?subgroup=all&from=2026-11-01&to=2026-11-14')->assertOk();
        $statistics=$response->viewData('scopeStatistics');
        $this->assertSame(3,$statistics['summary']['students']);
        $this->assertSame($statistics['summary']['points'],array_sum(array_column($statistics['comparison'],'points')));
        $this->assertSame($statistics['summary']['maximum'],array_sum(array_column($statistics['comparison'],'maximum')));
        $this->assertSame(1,$statistics['summary']['active']);
        $this->get('/insider/courses/1/report?subgroup=1')->assertOk()->assertViewHas('students',fn($s)=>$s->count()===2);
        $this->getJson('/insider/courses/1/report?subgroup=1&student=4&report_detail=1')->assertNotFound();
        $html = $this->getJson('/insider/courses/1/lessons/1/statistics?subgroup=1')->assertOk()->json('html');
        $this->assertStringNotContainsString('Ученик 4', $html);
    }

    public function test_management_form_creates_groups_and_checks_stale_roster_before_transfer(): void
    {
        $page=$this->get('/insider/courses/1/subgroups')->assertOk();
        $version=$page->viewData('membershipVersion');
        $this->post('/insider/courses/1/subgroups',['name'=>'Вечерняя группа'])->assertRedirect();
        $this->post('/insider/courses/1/subgroups/assign',['students'=>[1],'subgroup_id'=>2,'membership_version'=>$version])->assertRedirect();
        $this->postJson('/insider/courses/1/subgroups/assign',['students'=>[3],'subgroup_id'=>2,'membership_version'=>$version])->assertConflict();
        $this->assertSame(1,app(CourseSubgroups::class)->groupFor(1,3));
        $this->actingAs(User::find(1))->get('/insider/courses/1/subgroups')->assertForbidden();
    }

    public function test_bonus_uses_subgroup_at_submission_and_never_another_groups_slot(): void
    {
        DB::table('course_subgroup_memberships')->update(['started_at'=>'2026-09-01']);
        DB::table('planner_courses')->where('id',1)->update(['audience_id'=>2]);
        DB::table('planner_calendar_slots')->update(['local_date'=>'2026-11-14','start_time'=>'10:00','duration_minutes'=>90]);
        DB::table('planner_session_resources')->update(['class_bonus_gc'=>7]);
        $bonus=app(\App\Services\ClassroomBonus::class);
        $at=Carbon::parse('2026-11-14 10:30:00','Europe/Moscow');
        $this->assertSame([],$bonus->at(\App\Task::find(11),1,1,$at));
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:00:00','Europe/Moscow'));
        app(CourseSubgroups::class)->assign(Course::find(1),[1],2,2);
        $this->assertSame([],$bonus->at(\App\Task::find(11),1,1,$at));
        $this->assertSame(7,$bonus->at(\App\Task::find(11),1,1,now()->addMinutes(10))['amount']);
    }

    public function test_transfer_preserves_frozen_submission_deadline(): void
    {
        $deadlines=app(CourseDeadlineService::class);
        $input=['revision'=>0,'mode'=>'custom','timezone'=>'Europe/Moscow','stages'=>[['deadline_date'=>'2026-11-15','retained_percent'=>50]]];
        $a=$deadlines->savePolicy(Course::find(1),'task',11,$input,2,false,1);
        $input['stages'][0]['deadline_date']='2026-11-20';
        $deadlines->savePolicy(Course::find(1),'task',11,$input,2,false,2);
        $solution=$this->solution('2026-11-14 10:00:00',10);
        $points = \App\CourseStudentPoints::recalculate(1,1)->points;
        Carbon::setTestNow(now()->addDay());
        app(CourseSubgroups::class)->assign(Course::find(1),[1],2,2);
        $this->assertSame($a->id,$solution->fresh()->deadline()->policyId);
        $this->assertSame($points,\App\CourseStudentPoints::where('course_id',1)->where('student_id',1)->value('points'));
        $this->assertSame('2026-11-20',\App\Task::find(11)->getDeadline(1,1)->expiration->toDateString());
    }

    public function test_current_lesson_can_override_and_return_to_the_course_default(): void
    {
        $common=Course::find(1)->current_lesson_id;
        $this->post('/insider/courses/1/current-lesson?subgroup=1',['lesson_id'=>2,'chapter'=>1])->assertRedirect();
        $this->assertTrue(CourseSubgroup::find(1)->current_lesson_override);
        $this->assertSame($common,Course::find(1)->current_lesson_id);
        $this->actingAs(User::find(1));
        $this->assertSame(2,Course::with('program.chapters','teachers')->find(1)->currentLessonFor(User::find(1))?->id);
        $this->actingAs(User::find(2))->post('/insider/courses/1/current-lesson?subgroup=1',['inherit_current'=>1])->assertRedirect();
        $this->assertFalse(CourseSubgroup::find(1)->current_lesson_override);
    }
}
