<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class RoleLessonCardsTest extends DeadlineFeatureCase
{
    public function test_code_starter_moves_to_editor_while_zip_and_input_files_stay_with_the_task(): void
    {
        $url='/insider/courses/1/steps/1';
        DB::table('tasks')->where('id',11)->update(['is_code'=>true]);
        DB::table('code_task_checkers')->updateOrInsert(['task_id'=>11], ['config'=>json_encode(['submission_modes'=>['code'],
            'language'=>'python','check_type'=>'manual']), 'revision'=>str_repeat('a',64), 'updated_by'=>2]);
        $starter=(string)Str::uuid();
        $file=['task_id'=>11,'artifact'=>'{}','created_by'=>2,'is_public'=>true,'read_only'=>false];
        DB::table('code_task_files')->insert($file+['id'=>$starter,'path'=>'main.py','kind'=>'starter']);
        DB::table('code_task_files')->insert($file+['id'=>(string)Str::uuid(),'path'=>'input.wav','kind'=>'attachment']);
        $this->actingAs(User::find(1))->get($url)->assertOk()->assertSee('data-editor-starter=',false)->assertSee('input.wav')
            ->assertDontSee('Шаблон кода')->assertDontSee('Баллы и прогресс урока');
        DB::table('code_task_files')->where('id',$starter)->update(['path'=>'project.zip']);
        $this->get($url)->assertOk()->assertSee('project.zip')->assertSee('Шаблон кода')->assertDontSee('data-editor-starter=',false);
    }

    private function attempt(int $task,int $student,?int $points,?int $evaluation=null,string $comment='',int $course=1):int
    {
        return DB::table('solutions')->insertGetId(['course_id'=>$course,'task_id'=>$task,'user_id'=>$student,
            'submitted'=>'2026-11-14 08:00:00','mark'=>$points,'evaluated_mark'=>$evaluation,'comment'=>$comment]);
    }

    private function page(int $viewer):\DOMXPath
    {
        DB::table('courses')->where('id',1)->update(['current_lesson_id'=>1]);
        $response=$this->actingAs(User::findOrFail($viewer))->get('/insider/courses/1')->assertOk();
        $dom=new \DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        return new \DOMXPath($dom);
    }

    public function test_student_sees_actionable_work_and_only_their_feedback():void
    {
        $this->attempt(11,1,3,3,'Проверь условие цикла');
        $this->attempt(12,1,null);
        $this->attempt(11,3,10,10,'Чужой комментарий');
        $xpath=$this->page(1);
        $card=$xpath->query('//*[@id="lesson-1"]')->item(0);
        $this->assertStringContainsString('Исправить 1 задачу',$card->textContent);
        $this->assertStringContainsString('1 на проверке',$card->textContent);
        $this->assertStringContainsString('Проверь условие цикла',$card->textContent);
        $this->assertStringNotContainsString('Чужой комментарий',$card->textContent);
        $this->assertSame(1,$xpath->query('//*[@id="marks1"]//*[@data-personal-task="11" and @data-task-status="revision"]')->length);
        $this->assertSame(1,$xpath->query('//*[@id="marks1"]//*[@data-personal-task="12" and @data-task-status="pending"]')->length);
        $action=$xpath->query('//*[@id="lesson-1"]//a[contains(@class,"course-continue")]')->item(0);
        $this->assertStringContainsString('Исправить работу',$action->textContent);
        $this->assertStringContainsString('task=11',$action->getAttribute('href'));
        $this->assertSame(0,$xpath->query('//*[@id="lesson-1"]//*[@data-lesson-review-link]')->length);
    }

    public function test_pending_and_superseded_attempts_do_not_request_resubmission():void
    {
        $this->attempt(11,1,null);
        $this->attempt(12,1,null);
        $xpath=$this->page(1);
        $this->assertStringContainsString('Посмотреть работу',$xpath->query('//*[@id="lesson-1"]//a[contains(@class,"course-continue")]')->item(0)->textContent);
        $this->attempt(11,1,10,10);
        $xpath=$this->page(1);
        $this->assertSame(1,$xpath->query('//*[@id="marks1"]//*[@data-personal-task="11" and @data-task-status="done"]')->length);
        $meta=$xpath->query('//*[@id="lesson-1"]//*[contains(@class,"course-lesson-compact__meta")]')->item(0)->textContent;
        $this->assertStringContainsString('1 на проверке',$meta);
        $this->assertStringNotContainsString('2 на проверке',$meta);
    }

    public function test_completed_class_lesson_leaves_a_direct_link_to_outstanding_revision(): void
    {
        foreach ([11, 12] as $task) $this->attempt($task, 1, 10, 10);
        $this->attempt(21, 1, 2, 2);
        $xpath = $this->page(1);
        $this->assertStringContainsString('Сейчас в классе', $xpath->query('//*[@id="lesson-1"]')->item(0)->textContent);
        $repeat = $xpath->query('//*[@id="lesson-1"]//a[contains(@class,"course-continue")]')->item(0);
        $this->assertStringNotContainsString('btn-primary', $repeat->getAttribute('class'));
        $revision = $xpath->query('//*[@id="lesson-2"]//a[contains(@class,"course-revision-link")]')->item(0);
        $this->assertStringEndsWith('task=21#task21', $revision->getAttribute('href'));
    }

    public function test_task_link_initially_renders_its_heading_and_best_evaluation_even_after_a_weaker_submission(): void
    {
        $this->attempt(11, 1, 6, 10);
        $this->attempt(11, 1, 7, 7);
        $this->attempt(11, 1, null);
        $this->actingAs(User::findOrFail(1));
        $page = $this->get('/insider/courses/1/steps/1?task=11')->assertOk();
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$page->getContent());
        $xpath = new \DOMXPath($dom);
        $heading = '//*[@id="task11" and contains(@class,"active")]//header[contains(@class,"gc-task-title-row")]';
        $this->assertSame(1, $xpath->query($heading.'//h2')->length);
        $this->assertStringContainsString('10 / 10', $xpath->query($heading.'//*[@data-task-outcome]')->item(0)->textContent);
        $this->assertSame(0, $xpath->query($heading.'//select')->length);
        $this->getJson('/insider/courses/1/tasks/11/code-submissions')
            ->assertOk()->assertJsonPath('task_outcome.label', 'Решение принято')->assertJsonPath('task_outcome.score', '10 / 10');
    }

    public function test_teacher_completion_is_separate_from_cached_xp_and_review_actions_are_lesson_scoped():void
    {
        DB::table('lesson_student_stats')->where('student_id',1)->update(['percent'=>140]);
        foreach([11,12] as $task)$this->attempt($task,3,7,10);
        $pending=$this->attempt(11,1,null);
        $elsewhere=$this->attempt(21,4,null);
        $this->attempt(11,4,null,course:2);
        $xpath=$this->page(2);
        $ring=$xpath->query('//*[@id="lesson-1"]//*[@role="progressbar"]')->item(0);
        $this->assertSame('33',$ring->getAttribute('aria-valuenow'));
        $this->assertStringContainsString('Завершили урок: 1 из 3',$ring->getAttribute('title'));
        $review=$xpath->query('//*[@id="lesson-1"]//*[@data-lesson-review-link]')->item(0);
        $this->assertStringContainsString('На проверке: 1',$review->textContent);
        parse_str(parse_url($review->getAttribute('href'),PHP_URL_QUERY),$filters);
        $this->assertSame(['course'=>'1','lesson'=>'1'],$filters);
        $queue=$this->get($review->getAttribute('href'))->assertOk();
        $queue->assertSee('data-review-queue-solution="'.$pending.'"',false)->assertDontSee('data-review-queue-solution="'.$elsewhere.'"',false);
        $queue->assertSee('name="lesson" value="1"',false);
        $queue->assertSee('Урок: Знакомство с Python');
        $this->post('/insider/reviews/reset-pending',['course'=>1,'lesson'=>1])->assertRedirect();
        $this->assertSame(1,(int)DB::table('solutions')->where('id',$pending)->value('review_skipped'));
        $this->assertSame(0,(int)DB::table('solutions')->where('id',$elsewhere)->value('review_skipped'));
    }

    public function test_correct_late_work_is_complete_without_a_sticker_or_an_overdue_warning():void
    {
        $this->policy('lesson',1);
        \Carbon\Carbon::setTestNow('2026-11-17 12:00:00 Europe/Moscow');
        foreach([11,12] as $task)$this->attempt($task,1,7,10);
        DB::table('lesson_student_stats')->where('student_id',1)->where('lesson_id',1)->update(['points'=>14,'percent'=>70]);
        $xpath=$this->page(1);
        $this->assertStringContainsString('Все задачи решены',$xpath->query('//*[@id="lesson-1"]')->item(0)->textContent);
        $this->assertStringContainsString('Оценка проверки: 10 / 10',$xpath->query('//*[@id="marks1"]')->item(0)->textContent);
        $this->assertSame(0,$xpath->query('//*[@id="lesson-1"]//*[@data-lesson-deadline-status="overdue"]')->length);
        $this->assertSame(0,$xpath->query('//*[contains(@class,"gc-course-learning-focus__deadlines")]//time')->length);
        $this->assertStringContainsString('Повторить урок',$xpath->query('//*[@id="lesson-1"]//a[contains(@class,"course-continue")]')->item(0)->textContent);
    }

    public function test_empty_point_cache_falls_back_to_the_students_actual_results():void
    {
        $this->attempt(11,1,3,3);
        DB::table('lesson_student_stats')->where('student_id',1)->where('lesson_id',1)->update(['points'=>0,'max_points'=>0,'percent'=>0]);
        $xpath=$this->page(1);
        $ring = $xpath->query('//*[@id="lesson-1"]//*[@data-lesson-progress-trigger]')->item(0);
        $this->assertStringContainsString('Прогресс 15%', $ring->getAttribute('aria-label'));
        $this->assertStringContainsString('3 / 20 XP',$xpath->query('//*[@id="marks1"]')->item(0)->textContent);
    }

    public function test_raw_checker_feedback_is_not_disclosed_through_lesson_cards():void
    {
        DB::table('tasks')->where('id',11)->update(['is_code'=>true]);
        $this->attempt(11,1,3,3,'Приватный ввод: secret-test-input');
        $xpath=$this->page(1);
        $text=$xpath->query('//*[@id="marks1"]')->item(0)->textContent;
        $this->assertStringNotContainsString('secret-test-input',$text);
        $this->assertStringContainsString('Открыть результат проверки',$text);
    }

    public function test_verified_teacher_feedback_is_visible_for_code_tasks():void
    {
        DB::table('tasks')->where('id',11)->update(['is_code'=>true]);
        $comment='Автоматический отчёт: private-diagnostic';
        $id=$this->attempt(11,1,3,3,$comment);
        DB::table('course_activities')->insert(['course_id'=>1,'task_id'=>11,'user_id'=>1,'solution_id'=>$id,
            'type'=>\App\CourseActivity::TYPE_SOLUTION_CHECKED,'payload'=>json_encode(['feedback_source'=>'teacher',
                'feedback_sha256'=>hash('sha256',$comment),'editable_comment'=>'Исправь условие цикла.'])]);
        $text=$this->page(1)->query('//*[@id="marks1"]')->item(0)->textContent;
        $this->assertStringContainsString('Исправь условие цикла.',$text);
        $this->assertStringNotContainsString('private-diagnostic',$text);
    }
}
