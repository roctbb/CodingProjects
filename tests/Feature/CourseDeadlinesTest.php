<?php

namespace Tests\Feature;

use App\Http\Controllers\CoursesController;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourseDeadlinesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00'));
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT, email_verified_at TEXT);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT, program_id INTEGER);
            CREATE TABLE programs (id INTEGER PRIMARY KEY, name TEXT);
            CREATE TABLE program_chapters (id INTEGER PRIMARY KEY, program_id INTEGER, name TEXT, sort_index INTEGER);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER, is_remote INTEGER DEFAULT 0, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE lessons (id INTEGER PRIMARY KEY, name TEXT, program_id INTEGER, chapter_id INTEGER);
            CREATE TABLE lesson_info (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, start_date TEXT);
            CREATE TABLE lesson_early_accesses (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, user_id INTEGER);
            CREATE TABLE program_steps (id INTEGER PRIMARY KEY, name TEXT, lesson_id INTEGER);
            CREATE TABLE tasks (id INTEGER PRIMARY KEY, name TEXT, step_id INTEGER, max_mark INTEGER DEFAULT 10, is_hidden INTEGER DEFAULT 0);
            CREATE TABLE task_deadlines (id INTEGER PRIMARY KEY, task_id INTEGER, course_id INTEGER, expiration TEXT);
            CREATE TABLE solutions (id INTEGER PRIMARY KEY, task_id INTEGER, course_id INTEGER, user_id INTEGER, mark INTEGER);
            INSERT INTO users VALUES (1, 'Student', 'student', '2026-01-01'), (2, 'Teacher', 'teacher', '2026-01-01'), (3, 'Outsider', 'student', '2026-01-01');
            INSERT INTO courses VALUES (1, 'Course', 1), (2, 'Other course', 2);
            INSERT INTO programs VALUES (1, 'Program'), (2, 'Other program');
            INSERT INTO program_chapters VALUES (1, 1, 'First chapter', 1), (2, 1, 'Second chapter', 2), (3, 2, 'Other chapter', 1);
            INSERT INTO course_students (course_id, user_id) VALUES (1, 1);
            INSERT INTO course_teachers (course_id, user_id) VALUES (1, 2);
            INSERT INTO lessons VALUES (1, 'First chapter lesson', 1, 1), (2, 'Second chapter lesson', 1, 2), (3, 'Unopened lesson', 1, 2), (4, 'Other program', 2, 3);
            INSERT INTO lesson_info VALUES (1, 1, 1, '2026-09-01'), (2, 1, 2, '2026-09-01');
            INSERT INTO program_steps VALUES (1, 'Step 1', 1), (2, 'Step 2', 2), (3, 'Step 3', 3), (4, 'Other step', 4);
            SQL);
        foreach (range(1, 11) as $id) {
            DB::table('tasks')->insert([
                'id' => $id, 'name' => 'Task '.$id,
                'step_id' => $id === 9 ? 3 : ($id === 10 ? 4 : ($id >= 5 ? 2 : 1)),
                'is_hidden' => $id === 8,
            ]);
            DB::table('task_deadlines')->insert([
                'id' => $id, 'course_id' => $id === 11 ? 2 : 1, 'task_id' => $id,
                'expiration' => $id === 1 ? '2026-09-17' : ($id === 7 ? '2027-01-15' : '2026-10-10'),
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function deadlineData(int $userId = 1): array
    {
        $this->actingAs(User::findOrFail($userId));

        return app(CoursesController::class)->deadlines(1)->getData();
    }

    public function test_full_list_spans_chapters_and_years_and_excludes_inaccessible_tasks(): void
    {
        $data = $this->deadlineData();
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $data['deadlines']->pluck('task_id')->all());
        $this->assertSame(['2026-09', '2026-10', '2027-01'], $data['deadlineMonths']->keys()->all());
        $this->assertCount(5, $data['deadlineMonths']['2026-10']['2026-10-10']);
        $this->assertTrue($data['deadlines'][0]->is_overdue);
        $this->assertTrue($data['deadlines'][1]->is_today);
        $this->assertFalse($data['deadlines'][1]->is_overdue);
    }

    public function test_completed_tasks_are_hidden_from_the_student_and_marks_are_course_scoped(): void
    {
        DB::table('solutions')->insert([
            ['id' => 1, 'task_id' => 1, 'course_id' => 1, 'user_id' => 1, 'mark' => 10],
            ['id' => 2, 'task_id' => 2, 'course_id' => 2, 'user_id' => 1, 'mark' => 10],
        ]);
        $data = $this->deadlineData();
        $this->assertNotContains(1, $data['deadlines']->pluck('task_id'));
        $this->assertFalse($data['deadlines']->firstWhere('task_id', 2)->is_done);
        $this->assertContains(1, $this->deadlineData(2)['deadlines']->pluck('task_id'));
    }

    public function test_dashboard_keeps_low_marks_and_overdue_work_without_exposing_closed_lessons(): void
    {
        DB::table('solutions')->insert([
            ['id' => 1, 'task_id' => 1, 'course_id' => 1, 'user_id' => 1, 'mark' => 0],
            ['id' => 2, 'task_id' => 2, 'course_id' => 1, 'user_id' => 1, 'mark' => 4],
            ['id' => 3, 'task_id' => 3, 'course_id' => 2, 'user_id' => 1, 'mark' => 10],
            ['id' => 4, 'task_id' => 4, 'course_id' => 1, 'user_id' => 1, 'mark' => 10],
        ]);
        $viewer = User::findOrFail(1);
        $items = app(\App\Queries\CourseDeadlineList::class)->forCourses(\App\Course::all(), $viewer);
        $this->assertSame([1, 2, 3, 5, 6, 7], $items->pluck('task_id')->all());
        $this->assertTrue($items->first()->is_overdue);
        $this->assertNotContains(4, $items->pluck('task_id'));
        $this->assertNotContains(9, $items->pluck('task_id'));
        $this->assertNotContains(11, $items->pluck('task_id'));
    }

    public function test_teacher_sees_hidden_and_unopened_course_tasks(): void
    {
        $this->assertEqualsCanonicalizing(range(1, 9), $this->deadlineData(2)['deadlines']->pluck('task_id')->all());
    }

    public function test_upcoming_list_excludes_past_before_limiting_and_keeps_today(): void
    {
        $viewer = User::findOrFail(1);
        $query = app(\App\Queries\CourseDeadlineList::class);
        $courses = \App\Course::all();
        $items = $query->forCourses($courses, $viewer, upcomingOnly: true);
        $this->assertSame([2, 3, 4, 5, 6, 7], $items->pluck('task_id')->all());
        $this->assertTrue($items->first()->is_today);

        DB::table('task_deadlines')->where('task_id', '<', 7)->update(['expiration' => '2026-09-17']);
        $this->assertSame([7], $query->forCourses($courses, $viewer, upcomingOnly: true)->take(5)->pluck('task_id')->all());
        $this->assertCount(7, $query->forCourses($courses, $viewer, includeCompleted: true));
    }

    public function test_upcoming_list_is_empty_after_last_cutoff_but_full_list_keeps_history(): void
    {
        Carbon::setTestNow('2027-01-16 00:00:00');
        $viewer = User::findOrFail(1);
        $query = app(\App\Queries\CourseDeadlineList::class);
        $courses = \App\Course::all();

        $this->assertEmpty($query->forCourses($courses, $viewer, upcomingOnly: true));
        $this->assertCount(7, $query->forCourses($courses, $viewer, includeCompleted: true));
    }

    public function test_early_access_makes_lesson_deadlines_visible(): void
    {
        DB::table('lesson_early_accesses')->insert(['course_id' => 1, 'lesson_id' => 3, 'user_id' => 1]);
        $this->assertContains(9, $this->deadlineData()['deadlines']->pluck('task_id')->all());
    }

    public function test_deadline_becomes_overdue_at_midnight_after_due_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-11 00:00:00'));
        $this->assertTrue($this->deadlineData()['deadlines']->firstWhere('task_id', 2)->is_overdue);
    }

    public function test_course_outsider_cannot_access_deadlines(): void
    {
        $this->actingAs(User::findOrFail(3))->getJson('/insider/courses/1/deadlines')->assertForbidden();
    }

    public function test_guest_cannot_access_deadlines(): void
    {
        $this->getJson('/insider/courses/1/deadlines')->assertUnauthorized();
    }

    public function test_page_renders_current_conditions_and_task_links(): void
    {
        $this->actingAs(User::findOrFail(1))->get('/insider/courses/1/deadlines')
            ->assertOk()
            ->assertSee('data-deadline-agenda', false)
            ->assertSee('data-deadline-summary', false)
            ->assertSeeInOrder(['Пропущенные дедлайны', 'Эти задачи ещё можно сдать.', '>Task 1<', 'Ближайшие дедлайны', '>Task 2<', '>Task 7<'], false)
            ->assertDontSee('Поиск по дедлайнам')
            ->assertDontSee('Напоминания')
            ->assertDontSee('Срок прошёл')
            ->assertSee('/insider/courses/1/steps/2#task7', false)
            ->assertDontSee('Task 8')
            ->assertDontSee('Task 9');
    }

    public function test_page_has_empty_state_when_no_deadlines_exist(): void
    {
        DB::table('task_deadlines')->delete();
        $this->actingAs(User::findOrFail(1))->get('/insider/courses/1/deadlines')
            ->assertOk()->assertSee('Нет невыполненных задач со сроками');
    }
}
