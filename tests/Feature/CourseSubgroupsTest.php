<?php

namespace Tests\Feature;

use App\Course;
use App\CourseSubgroup;
use App\Services\CourseSubgroups;
use App\Services\CourseDeadlineService;
use App\Support\CourseScope;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CourseSubgroupsTest extends CoursePlannerTest
{
    protected function setUp(): void
    {
        parent::setUp();
        (require base_path('database/migrations/2026_11_14_190000_create_deadline_policies.php'))->up();
        (require base_path('database/migrations/2026_11_17_120000_create_course_subgroups.php'))->up();
        DB::table('users')->insert(['id'=>4,'name'=>'Student B','role'=>'student','email_verified_at'=>'2026-01-01']);
        DB::table('course_students')->insert(['course_id'=>1,'user_id'=>4]);
        CourseSubgroup::create(['id'=>1,'course_id'=>1,'name'=>'Группа А']);
        CourseSubgroup::create(['id'=>2,'course_id'=>1,'name'=>'Группа Б']);
        CourseSubgroup::create(['id'=>3,'course_id'=>2,'name'=>'Чужая группа']);
        app(CourseSubgroups::class)->assign(Course::find(1), [3], 1, 1);
        app(CourseSubgroups::class)->assign(Course::find(1), [4], 2, 1);
    }

    private function scopedCommand(string $operation, int $audience, array $input = [])
    {
        $revision = DB::table('planner_courses')->where('course_id',1)->where('audience_id',$audience)->value('revision') ?? 0;
        return $this->postJson('/insider/courses/1/planner/api/commands/'.$operation.'?subgroup='.($audience ?: 'all'),
            $input + ['revision'=>$revision,'operation_id'=>(string)Str::uuid()]);
    }

    private function manual(int $audience, string $title, string $time = '15:00'): int
    {
        return $this->scopedCommand('add_slot',$audience,['date'=>'2026-11-10','start_time'=>$time,'duration_minutes'=>60,'title'=>$title])->assertOk()->json('result.session_id');
    }

    public function test_scope_filters_current_roster_and_ignores_student_requested_scope(): void
    {
        $course = Course::find(1);
        $this->assertSame([3],CourseScope::for($course,User::find(1),new Request(['subgroup'=>'1']))->students()->pluck('id')->all());
        $this->assertSame([3,4],CourseScope::for($course,User::find(1),new Request())->students()->pluck('id')->all());
        $this->assertSame([3],CourseScope::for($course,User::find(3),new Request(['subgroup'=>'2']))->students()->pluck('id')->all());
        DB::table('course_students')->where('user_id',3)->update(['hidden_from_stats'=>1]);
        $this->assertCount(0,CourseScope::for($course,User::find(1),new Request(['subgroup'=>'1']))->students());
    }

    public function test_student_reads_own_and_common_sessions_and_cannot_write_or_read_other_groups(): void
    {
        $a=$this->manual(1,'Только А'); $b=$this->manual(2,'Только Б'); $common=$this->manual(0,'Общее','18:00');
        $this->actingAs(User::find(3));
        $data=$this->getJson('/insider/courses/1/planner/api/state?from=2026-11-09&to=2026-11-15&subgroup=2')->assertOk()->json();
        $this->assertEqualsCanonicalizing([$a,$common],array_column($data['sessions'],'id'));
        $this->assertNotContains($b,array_column($data['sessions'],'id'));
        $this->scopedCommand('update_session',1,['session_id'=>$a,'title'=>'No','pinned'=>false])->assertForbidden();
        $this->getJson('/insider/courses/1/planner/api/providers/codingprojects/resources')->assertForbidden();
        $this->assertSame([], $data['history']);
    }

    public function test_other_group_ids_are_rejected_and_common_sessions_are_read_only_in_group_view(): void
    {
        $a=$this->manual(1,'А');$b=$this->manual(2,'Б');$common=$this->manual(0,'Общее','18:00');
        $data=$this->getJson('/insider/courses/1/planner/api/state?from=2026-11-09&to=2026-11-15&subgroup=1')->assertOk()->json();
        $this->assertTrue(collect($data['sessions'])->firstWhere('id',$a)['editable']);
        $this->assertFalse(collect($data['sessions'])->firstWhere('id',$common)['editable']);
        $this->scopedCommand('update_session',1,['session_id'=>$b,'title'=>'Bad','pinned'=>false])->assertNotFound();
        $this->getJson('/insider/courses/1/planner/api/state?subgroup=3')->assertNotFound();
        $this->getJson('/insider/courses/1/planner/api/state?subgroup[]=1')->assertUnprocessable();
    }

    public function test_transfer_preserves_history_and_current_scope(): void
    {
        $service=app(CourseSubgroups::class);
        $before=now(); Carbon::setTestNow(now()->addHour());
        $service->assign(Course::find(1),[3],2,1);
        $this->assertSame(1,$service->groupFor(1,3,$before));
        $this->assertSame(2,$service->groupFor(1,3));
        $this->assertSame(2,DB::table('course_subgroup_memberships')->where('user_id',3)->count());
        $service->assign(Course::find(1),[3],null,1);
        $this->assertNull($service->groupFor(1,3));
        $this->assertSame([3],CourseScope::for(Course::find(1),User::find(1),new Request(['subgroup'=>'none']))->students()->pluck('id')->all());
    }

    public function test_subgroup_deadlines_override_without_changing_other_groups_and_allow_inheritance(): void
    {
        $service=app(CourseDeadlineService::class);$course=Course::find(1);$task=\App\Task::find(1);
        $input=['revision'=>0,'mode'=>'custom','timezone'=>'Europe/Moscow','stages'=>[['deadline_date'=>'2026-11-12','retained_percent'=>50]]];
        $service->savePolicy($course,'task',1,$input,1,false,0);
        $input['stages'][0]['deadline_date']='2026-11-19';
        $service->savePolicy($course,'task',1,$input,1,false,1);
        $this->assertSame('2026-11-19',$task->getDeadline(1,3)->expiration->toDateString());
        $this->assertSame('2026-11-12',$task->getDeadline(1,4)->expiration->toDateString());
        $input['revision']=1;$input['mode']='inherit';$input['stages']=[];
        $service->savePolicy($course,'task',1,$input,1,false,1);
        $this->assertSame('2026-11-12',$task->getDeadline(1,3)->expiration->toDateString());
        $this->assertSame(2,DB::table('deadline_policies')->count());
    }

    public function test_material_copy_is_independent_and_scoped_to_destination_history(): void
    {
        $a=$this->manual(1,'А');$b=$this->manual(2,'Б');
        $this->scopedCommand('attach',1,['session_id'=>$a,'provider_key'=>'codingprojects','resource_type'=>'step','external_id'=>'1','layer'=>'plan'])->assertOk();
        $copy=$this->scopedCommand('copy_materials',2,['source_session_id'=>$a,'target_session_id'=>$b])->assertOk();
        $this->assertSame(1,$copy->json('result.copied'));
        $rows=DB::table('planner_session_resources')->get();$this->assertCount(2,$rows);
        $this->assertSame($rows[0]->resource_ref_id,$rows[1]->resource_ref_id);
        $this->assertNotSame($rows[0]->id,$rows[1]->id);
        $change=DB::table('planner_changes')->latest('id')->first();
        $this->scopedCommand('undo',2,['change_id'=>$change->id])->assertOk();
        $this->assertSame(1,DB::table('planner_session_resources')->count());
        $this->assertSame($a,DB::table('planner_session_resources')->value('session_id'));
    }

    public function test_subgroup_lesson_opening_is_resolved_for_the_student(): void
    {
        DB::table('course_subgroup_lessons')->insert(['subgroup_id'=>1,'lesson_id'=>1,'start_date'=>'2026-12-01']);
        $lesson=\App\Lesson::find(1);$course=Course::find(1);
        $this->assertFalse($lesson->isStarted($course,User::find(3)));
        $this->assertTrue($lesson->isStarted($course,User::find(4)));
        $this->assertSame('2026-12-01',$lesson->getStartDate($course,User::find(3))->toDateString());
    }

    public function test_calendar_rebuild_and_disabling_regular_sessions_do_not_change_another_group(): void
    {
        $input=['starts_on'=>'2026-11-09','ends_on'=>'2026-11-22','timezone'=>'Europe/Moscow','default_duration_minutes'=>90,
            'rules'=>[['rule_key'=>'weekly','weekday'=>4,'start_time'=>'15:00','duration_minutes'=>90]]];
        $this->scopedCommand('configure',1,$input)->assertOk();
        $input['rules'][0]['start_time']='16:45';
        $this->scopedCommand('configure',2,$input)->assertOk();
        $planner=DB::table('planner_courses')->where('audience_id',2)->value('id');
        $before=DB::table('planner_calendar_slots')->where('planner_course_id',$planner)->get()->toArray();
        $input['rules']=[];
        $this->scopedCommand('configure',1,$input)->assertOk();
        $this->assertEquals($before,DB::table('planner_calendar_slots')->where('planner_course_id',$planner)->get()->toArray());
        $this->assertSame(2,DB::table('planner_calendar_slots')->where('planner_course_id',$planner)->where('state','available')->count());
    }

    public function test_unassigned_student_scope_uses_the_explicit_viewer_and_preserves_anchors(): void
    {
        app(CourseSubgroups::class)->assign(Course::find(1),[4],null,1);
        $scope=CourseScope::for(Course::find(1),User::find(4),new Request(['subgroup'=>'1']));
        $this->assertSame([4],$scope->students()->pluck('id')->all());
        $teacher=CourseScope::for(Course::find(1),User::find(1),new Request(['subgroup'=>'2']));
        $this->assertSame('/insider/courses/1?chapter=7&subgroup=2#lesson-3',$teacher->url('/insider/courses/1?chapter=7#lesson-3'));
    }

    public function test_operations_saved_before_subgroups_can_still_be_undone(): void
    {
        $this->scopedCommand('configure',0,['starts_on'=>'2026-11-09','ends_on'=>'2026-11-15','timezone'=>'Europe/Moscow','default_duration_minutes'=>90,
            'rules'=>[['rule_key'=>'old-weekly','weekday'=>4,'start_time'=>'15:00','duration_minutes'=>90]]])->assertOk();
        $change=\App\CoursePlanner\Models\PlannerChange::latest('id')->first();
        $delta=$change->delta;
        foreach ($delta as &$row) if ($row['table']==='planner_courses') {
            foreach (['before','after'] as $side) if ($row[$side] !== null) unset($row[$side]['audience_id']);
        }
        unset($row);
        $change->update(['delta'=>$delta]);
        $this->scopedCommand('undo',0,['change_id'=>$change->id])->assertOk();
        $this->assertDatabaseHas('planner_courses',['course_id'=>1,'audience_id'=>0,'starts_on'=>null]);
    }
}
