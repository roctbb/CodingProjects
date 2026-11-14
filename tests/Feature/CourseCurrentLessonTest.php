<?php

namespace Tests\Feature;

use App\Course;
use App\Lesson;
use App\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CourseCurrentLessonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::statement('PRAGMA foreign_keys = ON');
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT, email_verified_at TEXT);
            CREATE TABLE programs (id INTEGER PRIMARY KEY, name TEXT);
            CREATE TABLE program_chapters (id INTEGER PRIMARY KEY, program_id INTEGER, name TEXT, sort_index INTEGER);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, program_id INTEGER, name TEXT, default_chapter_id INTEGER,
                created_at TEXT, updated_at TEXT);
            CREATE TABLE lessons (id INTEGER PRIMARY KEY, program_id INTEGER, chapter_id INTEGER, name TEXT, sort_index INTEGER, sticker TEXT);
            CREATE TABLE program_steps (id INTEGER PRIMARY KEY, program_id INTEGER, lesson_id INTEGER, sort_index INTEGER);
            CREATE TABLE tasks (id INTEGER PRIMARY KEY, step_id INTEGER, sort_index INTEGER);
            CREATE TABLE lesson_info (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, start_date TEXT);
            CREATE TABLE lesson_early_accesses (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, user_id INTEGER);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER, is_remote INTEGER DEFAULT 0, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER, hidden_from_stats INTEGER DEFAULT 0);
            INSERT INTO users VALUES (1,'Admin','admin','2026-01-01'),(2,'Teacher','teacher','2026-01-01'),
                (3,'Student','student','2026-01-01'),(4,'Other teacher','teacher','2026-01-01');
            INSERT INTO programs VALUES (1,'Program'),(2,'Other program');
            INSERT INTO program_chapters VALUES (1,1,'First',1),(2,1,'Second',2),(3,2,'Other',1);
            INSERT INTO courses (id,program_id,name,default_chapter_id) VALUES (1,1,'Course',1),(2,1,'Another group',1);
            INSERT INTO lessons VALUES (1,1,1,'First lesson',1,'/sticker.png'),(2,1,2,'Second lesson',2,NULL),
                (3,2,3,'Other lesson',1,NULL),(4,1,1,'Empty lesson',3,NULL);
            INSERT INTO program_steps VALUES (1,1,1,1),(2,1,2,1),(3,2,3,1);
            INSERT INTO course_teachers VALUES (1,2,0);
            INSERT INTO course_students VALUES (1,3,1,0),(1,4,0,0);
            SQL);
        $this->migration()->up();
        $this->actingAs(User::findOrFail(2));
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_11_13_120000_add_current_lesson_to_courses_table.php');
    }

    public function test_owner_selects_one_current_lesson_for_this_course_and_can_return_to_auto(): void
    {
        $this->post('/insider/courses/1/current-lesson', ['lesson_id' => 1])->assertRedirect('/insider/courses/1?chapter=1#lesson-1');
        $this->assertSame(1, Course::find(1)->current_lesson_id);
        $this->assertNull(Course::find(2)->current_lesson_id);
        $this->post('/insider/courses/1/current-lesson', ['lesson_id' => 2])->assertRedirect('/insider/courses/1?chapter=2#lesson-2');
        $this->assertSame(2, Course::find(1)->current_lesson_id);
        $this->assertSame(1, Course::find(1)->default_chapter_id);
        $this->post('/insider/courses/1/current-lesson', ['lesson_id' => '', 'chapter' => 2])->assertRedirect('/insider/courses/1?chapter=2');
        $this->assertNull(Course::find(1)->current_lesson_id);
        $this->assertDatabaseCount('lesson_info', 0);
    }

    public function test_student_and_teacher_without_management_rights_cannot_select_or_clear(): void
    {
        DB::table('courses')->where('id', 1)->update(['current_lesson_id' => 1]);
        foreach ([3, 4] as $actor) {
            foreach ([['lesson_id' => 2], ['lesson_id' => null]] as $payload) {
                $this->actingAs(User::find($actor))->post('/insider/courses/1/current-lesson', $payload)->assertForbidden();
                $this->assertSame(1, Course::find(1)->current_lesson_id);
            }
        }
        $this->actingAs(User::find(1))->post('/insider/courses/1/current-lesson', ['lesson_id' => 2])->assertRedirect();
        $this->assertSame(2, Course::find(1)->current_lesson_id);
    }

    public function test_foreign_empty_and_invalid_lessons_are_rejected_without_losing_selection(): void
    {
        DB::table('courses')->where('id', 1)->update(['current_lesson_id' => 1]);
        foreach ([3, 4, 999, 0, 'bad', [1]] as $id) {
            $this->postJson('/insider/courses/1/current-lesson', ['lesson_id' => $id])->assertUnprocessable()->assertJsonValidationErrors('lesson_id');
            $this->assertSame(1, Course::find(1)->current_lesson_id);
        }
    }

    public function test_pinning_does_not_open_materials_and_becomes_visible_when_lesson_opens(): void
    {
        $this->post('/insider/courses/1/current-lesson', ['lesson_id' => 2])->assertRedirect();
        $this->assertNull(Course::find(1)->currentLessonFor(User::find(3)));
        $this->assertSame(2, Course::find(1)->currentLessonFor(User::find(2))->id);
        DB::table('lesson_info')->insert(['course_id' => 1, 'lesson_id' => 2, 'start_date' => now()->subDay()->toDateTimeString()]);
        $this->assertSame(2, Course::find(1)->currentLessonFor(User::find(3))->id);
    }

    public function test_stale_selection_is_ignored_after_program_or_structure_changes(): void
    {
        DB::table('courses')->where('id', 1)->update(['current_lesson_id' => 1]);
        DB::table('program_steps')->where('lesson_id', 1)->delete();
        $this->assertNull(Course::find(1)->currentLessonFor(User::find(2)));
        DB::table('courses')->where('id', 1)->update(['program_id' => 2]);
        $this->assertNull(Course::find(1)->currentLessonFor(User::find(1)));
    }

    public function test_lesson_deletion_clears_pointer_and_migration_can_be_reversed(): void
    {
        DB::table('courses')->where('id', 1)->update(['current_lesson_id' => 1]);
        DB::table('lessons')->where('id', 1)->delete();
        $this->assertNull(Course::find(1)->current_lesson_id);
        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('courses', 'current_lesson_id'));
        $this->migration()->up();
        $this->assertTrue(Schema::hasColumn('courses', 'current_lesson_id'));
    }

    public function test_reward_preview_and_earned_state_have_distinct_accessible_labels_and_no_duplicate_art(): void
    {
        $course = new Course(['name' => 'Python']);
        $lesson = Lesson::find(1);
        $render = fn ($status, $sticker = true) => Blade::render('<x-gc-lesson-reward :course="$course" :lesson="$lesson" :sticker="$sticker" :status="$status" />', ['course' => $course, 'lesson' => $lesson, 'status' => $status, 'sticker' => $sticker]);
        $this->assertStringContainsString('ещё не получена', $render('preview'));
        $this->assertStringContainsString('is-preview', $render('preview'));
        $this->assertStringNotContainsString('is-preview', $render('earned'));
        $this->assertStringContainsString('Полученная наклейка', $render('earned'));
        $this->assertStringNotContainsString('Полученная наклейка', $render('catalog'));
        $this->assertStringNotContainsString('<img', $render('preview', false));
        $this->assertStringContainsString('<svg', $render('preview', false));
    }
}
