<?php

namespace Tests\Feature;

use App\Http\Controllers\TasksController;
use App\Task;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaskAuthoringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:']);
        DB::purge('sqlite');
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Queue::fake();
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT, email_verified_at TEXT);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, program_id INTEGER, name TEXT);
            CREATE TABLE programs (id INTEGER PRIMARY KEY, name TEXT);
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER, is_remote INTEGER DEFAULT 0, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE lessons (id INTEGER PRIMARY KEY, program_id INTEGER, name TEXT);
            CREATE TABLE program_steps (id INTEGER PRIMARY KEY, program_id INTEGER, lesson_id INTEGER, name TEXT, sort_index INTEGER);
            CREATE TABLE tasks (id INTEGER PRIMARY KEY, step_id INTEGER, name TEXT, text TEXT, solution TEXT, answer TEXT, price INTEGER,
                max_mark INTEGER, sort_index INTEGER, is_star INTEGER DEFAULT 0, is_hidden INTEGER DEFAULT 0, is_code INTEGER DEFAULT 0,
                is_quiz INTEGER DEFAULT 0, only_class INTEGER DEFAULT 0, only_remote INTEGER DEFAULT 0, xp_booster_enabled INTEGER DEFAULT 0,
                generates_ai_achievement INTEGER DEFAULT 0, ai_achievement_instruction TEXT, created_at TEXT, updated_at TEXT);
            INSERT INTO users VALUES (1,'Teacher','teacher','2026-01-01'),(2,'Student','student','2026-01-01');
            INSERT INTO courses VALUES (1,1,'Course');
            INSERT INTO programs VALUES (1,'Program'),(2,'Foreign program');
            INSERT INTO course_teachers VALUES (1,1,0);
            INSERT INTO course_students VALUES (1,2,0,0);
            INSERT INTO lessons VALUES (1,1,'Lesson'),(2,2,'Foreign lesson');
            INSERT INTO program_steps VALUES (1,1,1,'Step',100),(2,2,2,'Foreign step',100);
            SQL);
        (require database_path('migrations/2026_11_18_120000_add_quiz_settings_to_tasks.php'))->up();
        $this->actingAs(User::find(1));
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    #[DataProvider('modes')]
    public function testExplicitModeCreatesAndEditsTheSameTaskType(string $mode, bool $code, bool $quiz): void
    {
        $data=['name'=>'Question','text'=>'Solve it','max_mark'=>10,'price'=>0,'is_hidden'=>'on','answer_mode'=>$mode,'answer'=>'0'];
        $this->post('/insider/courses/1/steps/1/task',$data)->assertRedirect()->assertSessionHasNoErrors();
        $task=Task::first();
        $this->assertSame($code,(bool)$task->is_code);
        $this->assertSame($quiz,(bool)$task->is_quiz);
        if ($quiz) {
            $this->assertSame('0', $task->answer);
            $this->assertSame(2, $task->quizAttemptLimit());
        }
        $this->post('/insider/courses/1/tasks/'.$task->id.'/edit',$data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($code,(bool)$task->fresh()->is_code);
        $this->assertSame($quiz,(bool)$task->fresh()->is_quiz);
    }

    public static function modes(): array
    {
        return [['manual',false,false],['code',true,false],['short',false,true]];
    }

    public function test_choice_configuration_validates_correct_options_and_persists_the_attempt_limit(): void
    {
        $url = '/insider/courses/1/steps/1/task';
        $taskData = ['name' => 'Question', 'text' => 'Choose', 'max_mark' => 10, 'answer_mode' => 'single',
            'answer_options' => [0 => 'First', 2 => 'Second', 4 => 'Third'], 'correct_options' => [2], 'max_attempts' => 4];

        $this->postJson($url, array_replace($taskData, ['correct_options' => [0, 2], 'max_attempts' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors(['correct_options', 'max_attempts']);
        $this->postJson($url, array_replace($taskData, ['correct_options' => [1]]))
            ->assertUnprocessable()->assertJsonValidationErrors('correct_options.0');
        $this->postJson($url, $taskData)->assertRedirect();

        $task = Task::sole();
        $this->assertTrue((bool) $task->is_quiz);
        $this->assertSame('single', $task->quizAnswerMode());
        $this->assertSame(4, $task->quizAttemptLimit());
        $this->assertSame([false, true, false], array_column($task->quiz_choices['options'], 'correct'));
        $this->assertArrayNotHasKey('quiz_choices', $task->toArray());

        $editUrl = '/insider/courses/1/tasks/'.$task->id.'/edit';
        $this->postJson($editUrl, array_replace($taskData, ['answer_mode' => 'multiple']))
            ->assertUnprocessable()->assertJsonValidationErrors('correct_options');
        $this->postJson($editUrl, array_replace($taskData, ['answer_mode' => 'multiple', 'correct_options' => [2, 4]]))
            ->assertRedirect();
        $this->assertSame('multiple', $task->fresh()->quizAnswerMode());
        $this->assertSame([false, true, true], array_column($task->fresh()->quiz_choices['options'], 'correct'));

        $this->postJson($editUrl, array_replace($taskData, ['answer_mode' => 'short', 'answer' => '0']))->assertRedirect();
        $this->assertNull($task->fresh()->quiz_choices);
        $this->assertSame('0', $task->fresh()->answer);
    }

    public function test_native_settings_and_task_save_atomically(): void
    {
        \Illuminate\Support\Facades\Storage::fake('code_checking'); \Illuminate\Support\Facades\Storage::fake('code_originals');
        (require database_path('migrations/2026_11_14_120000_create_code_checking_tables.php'))->up();
        (require database_path('migrations/2026_11_14_130000_expand_task_submissions.php'))->up();
        (require database_path('migrations/2026_11_14_140000_complete_native_submission_storage.php'))->up();
        $data=['name'=>'Files task','text'=>'Upload work','max_mark'=>10,'price'=>0,'answer_mode'=>'native',
            'checker_config'=>json_encode(['check_type'=>'manual','language'=>'text','submission_modes'=>['files','text']])];
        $this->post('/insider/courses/1/steps/1/task',$data)->assertRedirect()->assertSessionHasNoErrors();
        $task=Task::firstOrFail();
        $this->assertSame('manual',\App\CodeTaskChecker::findOrFail($task->id)->config['check_type']);
        $data['name']='Should roll back';
        $data['checker_config']=json_encode(['check_type'=>'tests','language'=>'python','test_config'=>['tests'=>[]]]);
        $this->post('/insider/courses/1/tasks/'.$task->id.'/edit',$data)->assertRedirect()->assertSessionHasErrors('checker_config');
        $this->assertSame('Files task',$task->fresh()->name);
        $this->assertSame('manual',\App\CodeTaskChecker::findOrFail($task->id)->config['check_type']);
    }

    #[DataProvider('nonTestCheckers')]
    public function test_non_test_checker_with_legacy_null_tests_can_be_created_and_edited(string $checkType): void
    {
        \Illuminate\Support\Facades\Storage::fake('code_checking'); \Illuminate\Support\Facades\Storage::fake('code_originals');
        (require database_path('migrations/2026_11_14_120000_create_code_checking_tables.php'))->up();
        (require database_path('migrations/2026_11_14_130000_expand_task_submissions.php'))->up();
        (require database_path('migrations/2026_11_14_140000_complete_native_submission_storage.php'))->up();
        $config=['check_type'=>$checkType,'language'=>'python','test_mode'=>'environment','test_config'=>null,
            'private_instructions'=>'Критерии проверки','reference_answer'=>'Эталон','gpt_model'=>null,'gpt_rate_limit'=>3];
        $data=['name'=>'AI task','text'=>'Условие задачи','max_mark'=>10,'price'=>0,'answer_mode'=>'native',
            'checker_config'=>json_encode($config)];
        $this->post('/insider/courses/1/steps/1/task',$data)->assertRedirect()->assertSessionHasNoErrors();
        $task=Task::firstOrFail();
        // Imported checkers still contain the unused null field when the author opens the form.
        $checker=\App\CodeTaskChecker::findOrFail($task->id);
        $checker->update(['config'=>$checker->config+['test_config'=>null]]);
        $data['name']='Уточнённая задача';
        $config['private_instructions']='Обновлённые критерии';
        $data['checker_config']=json_encode($config);
        $this->post('/insider/courses/1/tasks/'.$task->id.'/edit',$data)->assertRedirect()->assertSessionHasNoErrors();
        $saved=$checker->fresh()->config;
        $this->assertSame('Уточнённая задача',$task->fresh()->name);
        $this->assertSame($checkType,$saved['check_type']);
        $this->assertSame('Обновлённые критерии',$saved['private_instructions']);
        $this->assertSame('Эталон',$saved['reference_answer']);
        $this->assertSame(3,$saved['gpt_rate_limit']);
        $this->assertArrayNotHasKey('test_config',$saved);
        $this->assertSame($checkType!=='manual',(bool)$task->fresh()->is_code);
    }

    public static function nonTestCheckers(): array
    {
        return ['ai'=>['gpt'],'teacher'=>['manual']];
    }

    public function testShortAnswerRequiresAnAnswerAndInvalidModeDoesNotWrite(): void
    {
        foreach(['short','unknown'] as $mode){
            $this->postJson('/insider/courses/1/steps/1/task',['name'=>'Question','text'=>'Solve','max_mark'=>10,'answer_mode'=>$mode])
                ->assertUnprocessable();
        }
        $this->assertDatabaseCount('tasks',0);
    }

    public function testLegacyTaskClientsKeepTheirExistingModeContract(): void
    {
        $this->post('/insider/courses/1/steps/1/task',['name'=>'Legacy','text'=>'Solve','max_mark'=>5,'is_code'=>'on','is_hidden'=>'on'])->assertRedirect();
        $this->assertTrue((bool)Task::first()->is_code);
    }

    public function testCreatePageResolvesItsStepAndRejectsAnotherProgram(): void
    {
        $this->assertSame(1,(new TasksController())->createForm(1,1)->getData()['step']->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new TasksController())->createForm(1,2);
    }

    public function testStudentCannotOpenTheAuthoringPage(): void
    {
        $this->actingAs(User::find(2))->get('/insider/courses/1/steps/1/task/create')->assertForbidden();
    }
}
