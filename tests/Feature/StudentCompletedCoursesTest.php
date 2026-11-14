<?php

namespace Tests\Feature;

use App\Queries\StudentCompletedCourses;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentCompletedCoursesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT, email_verified_at TEXT);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT, state TEXT, invite TEXT, remote_invite TEXT, end_date TEXT);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER, is_remote INTEGER DEFAULT 0, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE completed_courses (id INTEGER PRIMARY KEY, user_id INTEGER, course_id INTEGER, name TEXT, mark TEXT);
            INSERT INTO users VALUES (1,'Student','student','2026-01-01'),(2,'Other','student','2026-01-01');
            INSERT INTO courses VALUES (1,'Archived','ended','OLD','OLD-R','2026-06-01'),(2,'Active','started','NEW','NEW-R',NULL),(3,'Private archive','ended','PRIVATE','PRIVATE-R','2026-06-01');
            SQL);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_late_invitation_appears_in_completed_courses_without_an_invented_grade(): void
    {
        foreach (['OLD', 'OLD-R'] as $invite) {
            DB::table('course_students')->delete();
            $student = User::findOrFail(1);
            $this->actingAs($student)->from('/insider/courses')->get('/insider/invite?invite='.$invite)->assertRedirect('/insider/courses');
            $entries = app(StudentCompletedCourses::class)->forStudent($student);
            $this->assertSame([1], $entries->pluck('course_id')->all());
            $this->assertTrue($entries->first()->is_linked);
            $this->assertNull($entries->first()->mark);
            $this->assertSame('Archived', $entries->first()->course->name);
            $this->assertDatabaseCount('completed_courses', 0);
        }
    }

    public function test_old_missing_snapshots_are_recovered_but_active_and_unenrolled_courses_are_not(): void
    {
        DB::table('course_students')->insert([['course_id' => 1, 'user_id' => 1], ['course_id' => 2, 'user_id' => 1], ['course_id' => 3, 'user_id' => 2]]);
        $entries = app(StudentCompletedCourses::class)->forStudent(User::find(1));
        $this->assertSame([1], $entries->pluck('course_id')->all());
    }

    public function test_existing_marks_and_manual_history_are_preserved_without_duplicate_cards(): void
    {
        DB::table('course_students')->insert(['course_id' => 1, 'user_id' => 1]);
        DB::table('completed_courses')->insert([
            ['id' => 1, 'user_id' => 1, 'course_id' => 1, 'name' => 'Saved course title', 'mark' => '9'],
            ['id' => 2, 'user_id' => 1, 'course_id' => null, 'name' => 'External olympiad', 'mark' => '10'],
        ]);
        $entries = app(StudentCompletedCourses::class)->forStudent(User::find(1));
        $this->assertCount(2, $entries);
        $this->assertSame('9', $entries->firstWhere('course_id', 1)->mark);
        $this->assertSame('Saved course title', $entries->firstWhere('course_id', 1)->name);
        $this->assertFalse($entries->firstWhere('id', 2)->is_linked);
    }
}
