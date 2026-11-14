<?php

namespace Tests\Feature;

use App\Support\WorkspaceNavigation;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceNavigationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT, state TEXT);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER);
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER);
            INSERT INTO users VALUES (1,'student'),(2,'teacher'),(3,'admin');
            INSERT INTO courses VALUES (99,'Private course','started'),(100,'Ended course','ended'),(101,'Draft course','draft');
            INSERT INTO course_students VALUES (100,1),(101,1);
            SQL);
        foreach (range(1, 11) as $id) {
            DB::table('courses')->insert(['id' => $id, 'name' => sprintf('Course %02d', $id), 'state' => 'started']);
            DB::table('course_students')->insert(['course_id' => $id, 'user_id' => 1]);
            DB::table('course_teachers')->insert(['course_id' => $id, 'user_id' => 2]);
        }
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_shortcuts_are_bounded_keep_the_open_course_and_exclude_unrelated_courses(): void
    {
        $navigation = new WorkspaceNavigation();
        foreach ([1, 2] as $userId) {
            $data = $navigation->forUser(User::findOrFail($userId), Request::create('/insider/courses/10/planner'));
            $this->assertCount(8, $data['courses']);
            $this->assertTrue($data['hasMoreCourses']);
            $this->assertTrue($data['courses']->contains('id', 10));
            $this->assertFalse($data['courses']->contains('id', 99));
            $this->assertFalse($data['courses']->contains('id', 100));
            $this->assertFalse($data['courses']->contains('id', 101));
            $this->assertSame(10, $data['currentCourseId']);
            $this->assertFalse(collect($data['items'])->firstWhere('key', 'courses')['shortcutActive']);
        }
        $admin = $navigation->forUser(User::findOrFail(3), Request::create('/insider/courses/99'));
        $this->assertTrue($admin['courses']->contains('id', 99));
    }

    public function test_primary_destinations_follow_role_and_review_context(): void
    {
        $navigation = new WorkspaceNavigation();
        $student = $navigation->forUser(User::findOrFail(1), Request::create('/insider/courses'));
        $this->assertNotContains('reviews', array_column($student['items'], 'key'));
        $this->assertTrue(collect($student['items'])->firstWhere('key', 'courses')['active']);
        $teacher = $navigation->forUser(User::findOrFail(2), Request::create('/insider/courses/1/tasks/11/student/1'));
        $this->assertTrue(collect($teacher['items'])->firstWhere('key', 'reviews')['active']);
        $this->assertFalse(collect($teacher['items'])->firstWhere('key', 'courses')['active']);
        $this->assertSame(1, $teacher['currentCourseId']);
    }

    public function test_other_peoples_profiles_belong_to_community_and_settings_to_own_profile(): void
    {
        $navigation = new WorkspaceNavigation();
        $own = $navigation->forUser(User::findOrFail(1), Request::create('/insider/profile/1/edit'));
        $this->assertTrue(collect($own['items'])->firstWhere('key', 'profile')['active']);
        $this->assertFalse(collect($own['items'])->firstWhere('key', 'community')['active']);
        $peer = $navigation->forUser(User::findOrFail(1), Request::create('/insider/profile/2'));
        $this->assertTrue(collect($peer['items'])->firstWhere('key', 'community')['active']);
        $this->assertFalse(collect($peer['items'])->firstWhere('key', 'profile')['active']);
        $this->assertEmpty($navigation->forUser(null, Request::create('/login'))['items']);
    }
}
