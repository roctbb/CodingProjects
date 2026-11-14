<?php
namespace Tests\Feature;

use App\Course;
use App\Lesson;
use App\ProgramStep;
use App\Task;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\DeadlineFeatureCase;

class BusinessAccessTest extends DeadlineFeatureCase
{
    public function test_student_cannot_author_or_reorder_materials_or_remove_a_plagiarism_block(): void
    {
        $this->actingAs(User::find(1)); $before=ProgramStep::count();
        $this->post('/insider/courses/1/lessons/1/create',['name'=>'Unauthorized'])->assertForbidden();
        $this->assertSame($before,ProgramStep::count());
        $chapter=DB::table('program_chapters')->first();
        $this->get('/insider/courses/1/chapters/'.$chapter->id.'/lower')->assertForbidden();
        $this->assertEquals($chapter->sort_index,DB::table('program_chapters')->where('id',$chapter->id)->value('sort_index'));
        DB::table('blocked_tasks')->insert(['course_id'=>1,'task_id'=>11,'user_id'=>1,'reason'=>'plagiarism','blocked_at'=>now()]);
        $this->post('/insider/courses/1/tasks/11/unblock/1')->assertForbidden();
        $this->get('/insider/courses/1/tasks/11/unblock/1')->assertStatus(405);
        $this->assertTrue(Task::find(11)->isBlocked(1,1));
    }

    public function test_assigned_teacher_can_create_steps_but_foreign_objects_and_grades_are_rejected(): void
    {
        $this->actingAs(User::find(2));
        $this->post('/insider/courses/1/lessons/1/create',['name'=>'Authorized','notes'=>'','theory'=>'Text'])->assertRedirect();
        DB::table('courses')->insert(['id'=>900,'program_id'=>900,'name'=>'Foreign','state'=>'started']);
        DB::table('programs')->insert(['id'=>900,'name'=>'Foreign']);
        DB::table('program_chapters')->insert(['id'=>900,'program_id'=>900,'name'=>'Foreign','sort_index'=>1]);
        DB::table('lessons')->insert(['id'=>900,'program_id'=>900,'chapter_id'=>900,'name'=>'Foreign']);
        $this->post('/insider/courses/900/lessons/900/edit',[])->assertForbidden();
        $this->post('/insider/courses/1/lessons/900/edit',[])->assertNotFound();
        $this->get('/insider/courses/1/chapters/900/lower')->assertNotFound();
        $this->assertSame('Foreign',Lesson::find(900)->name);
        DB::table('program_steps')->insert(['id'=>900,'program_id'=>900,'lesson_id'=>900,'name'=>'Foreign']);
        $task=Task::find(11)->replicate();$task->step_id=900;$task->save();
        $this->post('/insider/courses/1/tasks/'.$task->id.'/edit',[])->assertNotFound();
        $solution=$this->solution();$solution->course_id=900;$solution->save();
        $this->post('/insider/courses/1/solution/'.$solution->id,['mark'=>5])->assertNotFound();
        $this->assertNull($solution->fresh()->mark);
    }

    public function test_closed_and_draft_lessons_are_denied_for_both_submission_protocols_but_early_access_works(): void
    {
        DB::table('tasks')->where('id',11)->update(['is_quiz'=>false,'is_code'=>false,'is_hidden'=>false]);
        DB::table('lesson_info')->where('course_id',1)->where('lesson_id',1)->update(['start_date'=>'2027-01-01']);
        DB::table('lesson_early_accesses')->delete();
        $this->actingAs(User::find(1));
        $this->get('/insider/courses/1/steps/'.Task::find(11)->step_id)->assertForbidden();
        $this->postJson('/insider/courses/1/tasks/11/solution',['text'=>'Answer'])->assertForbidden();
        $this->getJson('/insider/courses/1/tasks/11/code-submissions')->assertForbidden();
        $this->assertDatabaseCount('solutions',0);
        DB::table('lesson_early_accesses')->insert(['course_id'=>1,'lesson_id'=>1,'user_id'=>1,'source'=>'teacher']);
        $this->postJson('/insider/courses/1/tasks/11/solution',['text'=>'Early answer'])->assertOk();
        DB::table('courses')->where('id',1)->update(['state'=>'draft']);
        $this->postJson('/insider/courses/1/tasks/11/solution',['text'=>'Draft answer'])->assertForbidden();
        $this->assertDatabaseCount('solutions',1);
    }

    public function test_teacher_block_and_unblock_are_logged_and_require_post(): void
    {
        DB::statement('CREATE UNIQUE INDEX access_course_stats ON course_student_points(course_id,student_id)');
        DB::statement('CREATE UNIQUE INDEX access_lesson_stats ON lesson_student_stats(course_id,lesson_id,student_id)');
        $this->actingAs(User::find(2));$this->get('/insider/courses/1/tasks/11/block/1')->assertStatus(405);
        $this->post('/insider/courses/1/tasks/11/block/1')->assertRedirect();
        $this->post('/insider/courses/1/tasks/11/block/1')->assertRedirect();
        $this->assertTrue(Task::find(11)->isBlocked(1,1));
        $this->post('/insider/courses/1/tasks/11/unblock/1')->assertRedirect();
        $this->assertFalse(Task::find(11)->isBlocked(1,1));
        $this->assertDatabaseHas('task_block_events',['actor_id'=>2,'student_id'=>1,'course_id'=>1,'task_id'=>11,'action'=>'blocked']);
        $this->assertDatabaseHas('task_block_events',['actor_id'=>2,'student_id'=>1,'action'=>'unblocked']);
        $this->assertDatabaseCount('task_block_events',2);
    }
}
