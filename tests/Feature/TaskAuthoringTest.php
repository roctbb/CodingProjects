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
        if($quiz)$this->assertSame('0',$task->answer);
        $this->post('/insider/courses/1/tasks/'.$task->id.'/edit',$data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($code,(bool)$task->fresh()->is_code);
        $this->assertSame($quiz,(bool)$task->fresh()->is_quiz);
    }

    public static function modes(): array
    {
        return [['manual',false,false],['code',true,false],['short',false,true]];
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
