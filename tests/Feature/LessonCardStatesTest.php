<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class LessonCardStatesTest extends DeadlineFeatureCase
{
    private function page(int $viewer):\DOMXPath
    {
        DB::table('courses')->where('id',1)->update(['current_lesson_id'=>1]);
        $html=$this->actingAs(User::findOrFail($viewer))->get('/insider/courses/1')->assertOk()->getContent();
        $dom=new \DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($dom);
    }

    private function native(string $state,int $student=1):void
    {
        $id=(string)Str::uuid();$run=(string)Str::uuid();
        DB::table('code_submissions')->insert(['id'=>$id,'course_id'=>1,'task_id'=>11,'user_id'=>$student,'request_id'=>(string)Str::uuid(),
            'payload_hash'=>str_repeat('a',64),'language'=>'python','files'=>'[]','current_run_id'=>$run,'created_at'=>'2026-11-14 09:00:00']);
        DB::table('code_execution_runs')->insert(['id'=>$run,'submission_id'=>$id,'snapshot'=>'{"check_type":"tests"}','state'=>$state,'comment'=>'private-diagnostic']);
    }

    public function test_unopened_lessons_show_the_plan_instead_of_empty_class_counters():void
    {
        DB::table('lesson_info')->where('course_id',1)->where('lesson_id',2)->update(['start_date'=>'2027-01-01']);
        $xpath=$this->page(2);
        $header=$xpath->query('//*[@id="lesson-2"]//*[contains(@class,"course-lesson-compact")]')->item(0)->textContent;
        $this->assertStringContainsString('2 задачи',$header);
        $this->assertStringContainsString('Откроется',$header);
        $this->assertStringNotContainsString('Завершили:',$header);
        $this->assertStringNotContainsString('Без сдач:',$header);
        $this->assertSame(0,$xpath->query('//*[@id="lesson-2"]//*[@role="progressbar"]')->length);
        DB::table('solutions')->insert(['task_id'=>21,'user_id'=>1,'course_id'=>1,'submitted'=>'2026-11-14 08:00:00']);
        $this->assertSame(1,$this->page(2)->query('//*[@id="lesson-2"]//*[@data-lesson-review-link]')->length);
    }

    public function test_automatic_check_is_not_presented_as_an_unsent_work_or_a_teacher_queue_item():void
    {
        $this->solution(mark:10,task:12);
        $this->native('running');
        $xpath=$this->page(1);
        $card=$xpath->query('//*[@id="lesson-1"]')->item(0)->textContent;
        $this->assertStringContainsString('Автопроверка: 1',$card);
        $this->assertStringContainsString('Посмотреть проверку',$card);
        $this->assertSame(1,$xpath->query('//*[@id="marks1"]//*[@data-personal-task="11" and @data-task-status="checking"]')->length);
        $this->assertStringContainsString('Без оценки',$xpath->query('//*[@id="marks1"]')->item(0)->textContent);
        $teacher=$this->page(2);
        $this->assertSame(0,$teacher->query('//*[@id="lesson-1"]//*[@data-lesson-review-link]')->length);
        $this->assertStringContainsString('Автопроверка: 1',$teacher->query('//*[@id="lesson-1"]')->item(0)->textContent);
    }

    public function test_execution_error_does_not_request_a_new_submission_or_expose_diagnostics():void
    {
        $this->solution(mark:10,task:12);
        $this->native('error');
        $this->policy('task',11);
        \Carbon\Carbon::setTestNow('2026-11-17 12:00:00 Europe/Moscow');
        $xpath=$this->page(1);
        $card=$xpath->query('//*[@id="lesson-1"]')->item(0)->textContent;
        $this->assertStringContainsString('Проверка не завершилась',$card);
        $this->assertStringContainsString('Посмотреть проверку',$card);
        $this->assertStringNotContainsString('private-diagnostic',$card);
        $this->assertSame(0,$xpath->query('//*[@id="lesson-1"]//*[@data-lesson-deadline-status="overdue"]')->length);
    }

    public function test_automatic_checks_are_scoped_to_the_student_and_do_not_override_newer_grades():void
    {
        $this->native('running',3);
        $xpath=$this->page(1);
        $this->assertStringNotContainsString('Автопроверка:',$xpath->query('//*[@id="lesson-1"]')->item(0)->textContent);
        $this->native('error');
        $this->solution('2026-11-15 10:00:00',10,11);
        $this->solution('2026-11-15 10:00:00',10,12);
        $xpath=$this->page(1);
        $this->assertStringContainsString('Все задачи решены',$xpath->query('//*[@id="lesson-1"]')->item(0)->textContent);
        $this->assertSame(0,$xpath->query('//*[@id="marks1"]//*[@data-task-status="check_error"]')->length);
    }
}
