<?php
namespace Tests\Feature;

use App\CodeExecutionRun;
use App\CodeSubmission;
use App\CourseActivity;
use App\Services\CodeChecking\Configuration;
use App\Task;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class LegacyTeacherFeedbackTest extends DeadlineFeatureCase
{
    private const RAW_REPORT = "Пройдено тестов: 0 из 1.\n\nТест 1 (публичный): неверный ответ.\n\nВвод:\n<pre>SECRET ORIGINAL INPUT</pre>\n\nВывод программы:\n<pre>SECRET ORIGINAL OUTPUT</pre>";

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('CREATE UNIQUE INDEX feedback_course_stats ON course_student_points(course_id,student_id)');
        DB::statement('CREATE UNIQUE INDEX feedback_lesson_stats ON lesson_student_stats(course_id,lesson_id,student_id)');
    }

    private function legacyTestWork(): array
    {
        $task=Task::findOrFail(11);
        $task->forceFill(['generates_ai_achievement'=>false,'price'=>0])->save();
        $checker=app(Configuration::class)->save($task,['language'=>'python','check_type'=>'tests',
            'test_config'=>['tests'=>[['input'=>'1','output'=>'1','is_public'=>false]]]],2);
        $solution=$this->solution('2026-11-14 10:00:00');
        // Imported automatic grading could already have a teacher_id.
        $solution->forceFill(['teacher_id'=>2,'comment'=>self::RAW_REPORT,'mark'=>5,'raw_mark'=>5,'checked'=>now()])->save();
        $id=(string)Str::uuid();$runId=(string)Str::uuid();
        $submission=CodeSubmission::create(['id'=>$id,'user_id'=>1,'course_id'=>1,'task_id'=>11,'solution_id'=>$solution->id,
            'legacy_id'=>'SAVED-FEEDBACK','source_metadata'=>['legacy_id'=>'SAVED-FEEDBACK'],'request_id'=>Str::uuid(),
            'payload_hash'=>str_repeat('a',64),'language'=>'python','mode'=>'code','files'=>[],
            'preparation_state'=>'ready','current_run_id'=>$runId]);
        $run=CodeExecutionRun::create(['id'=>$runId,'submission_id'=>$id,'state'=>'completed','points'=>5,
            'snapshot'=>app(Configuration::class)->snapshot($task,$checker),'comment'=>self::RAW_REPORT,
            'finished_at'=>now(),'result_hash'=>str_repeat('b',64)]);
        $solution->forceFill(['text'=>url('/insider/code-submissions/'.$id)])->save();
        return [$solution,$run];
    }

    private function eventUrl($solution):string
    {
        return '/insider/courses/1/tasks/11/code-attempts/'.$solution->id;
    }

    private function gradeUrl($solution):string
    {
        return '/insider/courses/1/solution/'.$solution->id;
    }

    public function test_explicit_teacher_test_feedback_survives_resave_without_exposing_the_old_report_or_duplicating_deadline_text():void
    {
        $this->policy('task',11,[['deadline_date'=>'2026-11-13','retained_percent'=>70]]);
        [$solution,$sourceRun]=$this->legacyTestWork();$snapshot=$sourceRun->snapshot;
        foreach([1,2] as $viewer)$this->actingAs(User::find($viewer))->getJson($this->eventUrl($solution))->assertOk()
            ->assertJsonPath('check_type','tests')->assertJsonPath('teacher_feedback',false)
            ->assertJsonPath('comment','')->assertJsonPath('review_comment','')->assertDontSee('SECRET ORIGINAL');
        $human="Проверьте отрицательные числа.\nДобавьте пояснение.";
        $this->actingAs(User::find(2))->postJson($this->gradeUrl($solution),['mark'=>7,'comment'=>$human])->assertOk()
            ->assertJsonPath('check_type','tests')->assertJsonPath('teacher_feedback',true)->assertJsonPath('review_comment',$human)
            ->assertDontSee('SECRET ORIGINAL');
        $saved=$solution->fresh();
        $this->assertTrue($saved->hasActiveDeadlinePenalty());
        $this->assertSame(1,substr_count($saved->comment,'Сдано с опозданием.'));
        $activity=CourseActivity::where('solution_id',$solution->id)->where('type',CourseActivity::TYPE_SOLUTION_CHECKED)->latest('id')->firstOrFail();
        $this->assertSame('teacher',$activity->payload['feedback_source']);
        $this->assertSame(hash('sha256',$saved->comment),$activity->payload['feedback_sha256']);
        $this->assertSame($human,$activity->payload['editable_comment']);
        $detail=$this->actingAs(User::find(1))->getJson($this->eventUrl($solution))->assertOk()->assertJsonPath('teacher_feedback',true)
            ->assertJsonPath('comment',$saved->comment)->assertJsonPath('review_comment',$human)->assertDontSee('SECRET ORIGINAL')->json();
        $this->actingAs(User::find(2))->postJson($this->gradeUrl($solution),['mark'=>7,'comment'=>$detail['review_comment']])->assertOk()
            ->assertJsonPath('review_comment',$human)->assertJsonPath('teacher_feedback',true);
        $this->assertSame(1,substr_count($solution->fresh()->comment,'Сдано с опозданием.'));
        $this->assertSame(self::RAW_REPORT,$sourceRun->fresh()->comment);
        $this->assertSame($snapshot,$sourceRun->fresh()->snapshot);
        $this->assertSame(5,$sourceRun->fresh()->points);
        // Empty feedback is explicit too: do not use the automatic prefix as editor input.
        $this->postJson($this->gradeUrl($solution),['mark'=>7,'comment'=>''])->assertOk()->assertJsonPath('teacher_feedback',true)->assertJsonPath('review_comment','');
        $this->assertSame(1,substr_count($solution->fresh()->comment,'Сдано с опозданием.'));
        $this->actingAs(User::find(3))->getJson($this->eventUrl($solution))->assertForbidden();
        $this->actingAs(User::find(2))->getJson('/insider/courses/1/tasks/11/code-submissions?user_id=1')->assertOk()->assertDontSee($human)->assertDontSee('SECRET ORIGINAL');
    }

    public function test_feedback_marker_must_match_scope_latest_event_and_exact_current_comment():void
    {
        [$solution]=$this->legacyTestWork();
        $payload=['feedback_source'=>'teacher','feedback_sha256'=>hash('sha256',self::RAW_REPORT),'editable_comment'=>'Unrelated teacher feedback'];
        foreach([['course_id'=>2],['task_id'=>12],['user_id'=>3],['solution_id'=>$solution->id+100]] as $wrong){
            CourseActivity::create(array_replace(['course_id'=>1,'task_id'=>11,'user_id'=>1,'solution_id'=>$solution->id,
                'type'=>CourseActivity::TYPE_SOLUTION_CHECKED,'payload'=>$payload],$wrong));
        }
        $this->actingAs(User::find(1))->getJson($this->eventUrl($solution))->assertOk()->assertJsonPath('teacher_feedback',false)
            ->assertJsonPath('comment','')->assertJsonPath('review_comment','')->assertDontSee('SECRET ORIGINAL');
        $human='Учитель проверил обработку границ.';
        $this->actingAs(User::find(2))->postJson($this->gradeUrl($solution),['mark'=>7,'comment'=>$human])->assertOk()->assertJsonPath('teacher_feedback',true);
        $teacherPayload=CourseActivity::where('solution_id',$solution->id)->where('type',CourseActivity::TYPE_SOLUTION_CHECKED)->latest('id')->firstOrFail()->payload;
        // A newer automatic check invalidates an older marker even if its text happens to match.
        CourseActivity::recordSolutionChecked($solution->fresh());
        $this->getJson($this->eventUrl($solution))->assertJsonPath('teacher_feedback',false)->assertJsonPath('comment','');
        // Restoring a stale manual marker cannot disclose a different subsequent test report.
        $activity=CourseActivity::where('solution_id',$solution->id)->where('type',CourseActivity::TYPE_SOLUTION_CHECKED)->latest('id')->firstOrFail();
        $activity->payload=$teacherPayload;$activity->save();
        DB::table('solutions')->where('id',$solution->id)->update(['comment'=>self::RAW_REPORT]);
        $this->getJson($this->eventUrl($solution))->assertJsonPath('teacher_feedback',false)->assertJsonPath('comment','')->assertJsonPath('review_comment','');
        $this->assertSame(self::RAW_REPORT,$solution->fresh()->comment);
    }

    public function test_invalid_or_oversized_feedback_never_changes_the_grade_or_source():void
    {
        [$solution,$sourceRun]=$this->legacyTestWork();$activityCount=CourseActivity::count();
        $this->actingAs(User::find(1))->postJson($this->gradeUrl($solution),['mark'=>7,'comment'=>'Forged feedback','feedback_source'=>'teacher'])->assertForbidden();
        foreach([['nested'=>'not text'],str_repeat('я',20001)] as $comment)$this->actingAs(User::find(2))
            ->postJson($this->gradeUrl($solution),['mark'=>7,'comment'=>$comment])->assertUnprocessable();
        $this->assertSame(5,$solution->fresh()->mark);
        $this->assertSame(self::RAW_REPORT,$solution->fresh()->comment);
        $this->assertSame(self::RAW_REPORT,$sourceRun->fresh()->comment);
        $this->assertSame($activityCount,CourseActivity::count());
    }
}
