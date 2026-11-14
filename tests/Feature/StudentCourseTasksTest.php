<?php

namespace Tests\Feature;

use App\Http\Controllers\StudentCourseTasksController;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentCourseTasksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT, email_verified_at TEXT);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, program_id INTEGER, name TEXT, state TEXT, default_chapter_id INTEGER, current_lesson_id INTEGER);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER, is_remote INTEGER DEFAULT 0, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE programs (id INTEGER PRIMARY KEY, name TEXT);
            CREATE TABLE program_chapters (id INTEGER PRIMARY KEY, program_id INTEGER, name TEXT, sort_index INTEGER);
            CREATE TABLE lessons (id INTEGER PRIMARY KEY, program_id INTEGER, chapter_id INTEGER, name TEXT, sort_index INTEGER);
            CREATE TABLE lesson_info (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, start_date TEXT);
            CREATE TABLE lesson_early_accesses (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, user_id INTEGER);
            CREATE TABLE program_steps (id INTEGER PRIMARY KEY, lesson_id INTEGER, name TEXT, sort_index INTEGER);
            CREATE TABLE tasks (id INTEGER PRIMARY KEY, step_id INTEGER, name TEXT, max_mark INTEGER, is_hidden INTEGER DEFAULT 0, is_star INTEGER DEFAULT 0, sort_index INTEGER);
            CREATE TABLE solutions (id INTEGER PRIMARY KEY, task_id INTEGER, course_id INTEGER, user_id INTEGER, mark INTEGER, submitted TEXT, recheck_requested INTEGER DEFAULT 0, review_skipped INTEGER DEFAULT 0, updated_at TEXT);
            CREATE TABLE blocked_tasks (id INTEGER PRIMARY KEY, course_id INTEGER, task_id INTEGER, user_id INTEGER);
            CREATE TABLE task_deadlines (id INTEGER PRIMARY KEY, course_id INTEGER, task_id INTEGER, expiration TEXT);
            INSERT INTO users VALUES (1,'Student','student','2026-01-01'),(2,'Teacher','teacher','2026-01-01'),(3,'Other','student','2026-01-01');
            INSERT INTO courses VALUES (1,1,'Course','started',2,1),(2,1,'Other course','started',1,NULL);
            INSERT INTO course_students (course_id,user_id) VALUES (1,1),(2,3);
            INSERT INTO course_teachers (course_id,user_id) VALUES (1,2);
            INSERT INTO programs VALUES (1,'Program');
            INSERT INTO program_chapters VALUES (1,1,'First',1),(2,1,'Second',2),(3,99,'Foreign',1);
            INSERT INTO lessons VALUES (1,1,1,'Open lesson',1),(2,1,1,'Closed lesson',2),(3,1,2,'Second lesson',3);
            INSERT INTO lesson_info (course_id,lesson_id,start_date) VALUES (1,1,'2020-01-01'),(1,3,'2020-01-01');
            INSERT INTO program_steps VALUES (1,1,'First step',1),(2,2,'Closed step',1),(3,3,'Second step',1);
            INSERT INTO tasks VALUES (1,1,'Completed',10,0,0,1),(2,1,'Unsolved',10,0,0,2),(3,1,'Review',10,0,0,3),(4,1,'Partial',10,0,0,4),(5,1,'Secret',10,1,0,5),(6,2,'Closed task',10,0,0,1),(7,3,'Second task',10,0,1,1),(8,1,'Blocked',10,0,0,8);
            INSERT INTO solutions (id,task_id,course_id,user_id,mark,submitted) VALUES (1,1,1,1,10,'2026-01-01'),(2,2,2,1,10,'2026-01-01'),(3,2,1,3,10,'2026-01-01'),(4,3,1,1,NULL,'2026-01-01'),(5,4,1,1,5,'2026-01-01'),(6,5,2,1,10,'2026-01-01');
            INSERT INTO blocked_tasks (course_id,task_id,user_id) VALUES (1,8,1);
            SQL);
        $this->actingAs(User::find(1));
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function data(array $query = []): array
    {
        $request = Request::create('/insider/courses/1/chapter-tasks', 'GET', $query);
        $request->setUserResolver(fn () => auth()->user());
        return (new StudentCourseTasksController())->index(1, $request)->getData();
    }

    public function test_selected_chapter_only_contains_open_visible_tasks_and_own_course_results(): void
    {
        $data = $this->data();
        $this->assertSame(1, $data['chapter']->id);
        $rows = $data['taskGroups']->flatMap(fn ($group) => $group['rows'])->values();
        $this->assertSame([1,2,3,4,8], $rows->pluck('task.id')->all());
        $this->assertSame(['done','empty','review','partial','blocked'], $rows->pluck('state')->all());
        $this->assertNull($rows[1]['best']);
        $this->assertSame(5, $rows[3]['best']->mark);
        $this->assertSame(['all'=>5,'pending'=>4,'done'=>1,'review'=>1], $data['counts']);
        $this->assertSame([7], $this->data(['chapter'=>2])['taskGroups']->flatMap(fn ($group) => $group['rows'])->pluck('task.id')->all());
    }

    public function test_personal_early_access_and_existing_hidden_submissions_are_respected(): void
    {
        DB::table('lesson_early_accesses')->insert(['course_id'=>1,'lesson_id'=>2,'user_id'=>1]);
        DB::table('solutions')->insert(['id'=>7,'task_id'=>5,'course_id'=>1,'user_id'=>1,'mark'=>3,'submitted'=>'2026-01-01']);
        $rows = $this->data()['taskGroups']->flatMap(fn ($group) => $group['rows']);
        $this->assertSame([1,2,3,4,5,8,6], $rows->pluck('task.id')->all());
    }

    public function test_non_members_and_teachers_cannot_open_a_students_personal_report(): void
    {
        foreach ([2,3] as $id) {
            $this->actingAs(User::find($id))->get('/insider/courses/1/chapter-tasks')->assertForbidden();
        }
        auth()->logout();
        $this->get('/insider/courses/1/chapter-tasks')->assertRedirect('/login');
    }

    public function test_foreign_chapters_are_rejected(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $this->data(['chapter'=>3]);
    }
}
