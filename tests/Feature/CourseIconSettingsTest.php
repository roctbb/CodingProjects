<?php

namespace Tests\Feature;

use App\Course;
use App\Support\CourseIcons;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CourseIconSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Http::fake();
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT, email_verified_at TEXT);
            CREATE TABLE programs (id INTEGER PRIMARY KEY, name TEXT);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, program_id INTEGER, name TEXT, description TEXT,
                image TEXT, git TEXT, site TEXT, telegram TEXT, weekdays TEXT, start_date TEXT, invite TEXT,
                state TEXT DEFAULT 'draft', mode TEXT DEFAULT 'private', created_at TEXT, updated_at TEXT);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER, is_remote INTEGER DEFAULT 0, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE course_course_category (course_id INTEGER, category_id INTEGER);
            INSERT INTO users VALUES (1, 'Admin', 'admin', '2026-01-01'), (2, 'Teacher', 'teacher', '2026-01-01'),
                (3, 'Student', 'student', '2026-01-01'), (4, 'Other teacher', 'teacher', '2026-01-01');
            INSERT INTO programs VALUES (1, 'Program');
            INSERT INTO courses (id, program_id, name, description, invite) VALUES (1, 1, 'Python', 'Description', 'JOIN');
            INSERT INTO course_teachers VALUES (1, 2, 0);
            INSERT INTO course_students VALUES (1, 3, 1, 0);
            SQL);
        (require database_path('migrations/2026_11_11_120000_add_icon_key_to_courses_table.php'))->up();
        $this->actingAs(User::findOrFail(2));
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function settings(array $overrides = []): array
    {
        return array_merge(['name' => 'Python', 'description' => 'Description', 'invite' => 'JOIN', 'students' => [3]], $overrides);
    }

    public function test_owner_teacher_can_save_icon_without_changing_membership_or_remote_status(): void
    {
        $this->post('/insider/courses/1/edit', $this->settings(['icon_key' => 'rocket']))->assertRedirect('/insider/courses/1');
        $this->assertSame('rocket', CourseIcons::forCourse(Course::findOrFail(1)));
        $this->assertSame('Python', Course::findOrFail(1)->name);
        $this->assertSame(1, DB::table('course_students')->where('user_id', 3)->value('is_remote'));
        $this->assertDatabaseCount('course_teachers', 1);
        Http::assertNothingSent();
    }

    public function test_legacy_edit_keeps_manual_choice_and_auto_restores_name_based_icon(): void
    {
        Course::findOrFail(1)->update(['icon_key' => 'network']);
        $this->post('/insider/courses/1/edit', $this->settings())->assertRedirect();
        $this->assertSame('network', Course::findOrFail(1)->icon_key);
        $this->post('/insider/courses/1/edit', $this->settings(['icon_key' => 'auto', 'name' => 'Minecraft + Python']))->assertRedirect();
        $course = Course::findOrFail(1);
        $this->assertNull($course->icon_key);
        $this->assertSame('blocks', CourseIcons::forCourse($course));
    }

    public function test_teacher_form_can_save_icon_without_the_admin_only_invite_field(): void
    {
        $settings = $this->settings(['icon_key' => 'bot']);
        unset($settings['invite']);
        $this->post('/insider/courses/1/edit', $settings)->assertRedirect('/insider/courses/1')->assertSessionHasNoErrors();
        $course = Course::findOrFail(1);
        $this->assertSame('bot', $course->icon_key);
        $this->assertSame('JOIN', $course->invite);
    }

    public function test_new_courses_accept_manual_and_automatic_choices(): void
    {
        foreach (['brain-circuit', 'auto'] as $key) {
            $this->post('/insider/courses/create', ['name' => 'Нейросети', 'description' => 'Description', 'program' => 1, 'icon_key' => $key])
                ->assertRedirect('/insider/courses');
            $course = Course::orderByDesc('id')->first();
            $this->assertSame($key === 'auto' ? null : $key, $course->icon_key);
            $this->assertSame('brain-circuit', CourseIcons::forCourse($course));
            $this->assertTrue($course->teachers->contains('id', 2));
        }
    }

    public function test_unlisted_svg_path_url_or_array_cannot_be_saved(): void
    {
        foreach (['../../.env', '<svg onload="alert(1)">', 'https://example.com/icon.svg', ['code']] as $key) {
            $this->postJson('/insider/courses/1/edit', $this->settings(['icon_key' => $key]))
                ->assertUnprocessable()->assertJsonValidationErrors('icon_key');
            $this->postJson('/insider/courses/create', ['name' => 'Course', 'description' => 'Description', 'program' => 1, 'icon_key' => $key])
                ->assertUnprocessable()->assertJsonValidationErrors('icon_key');
        }
        $this->assertDatabaseCount('courses', 1);
        $this->assertNull(Course::findOrFail(1)->icon_key);
    }

    public function test_student_and_other_teacher_cannot_change_the_icon(): void
    {
        foreach ([3, 4] as $actor) {
            $this->actingAs(User::findOrFail($actor))->post('/insider/courses/1/edit', $this->settings(['icon_key' => 'rocket']))->assertForbidden();
        }
        $this->assertNull(Course::findOrFail(1)->icon_key);
        $this->actingAs(User::findOrFail(1))->post('/insider/courses/1/edit', $this->settings(['icon_key' => 'rocket', 'teachers' => [2]]))->assertRedirect();
        $this->assertSame('rocket', Course::findOrFail(1)->icon_key);
    }

    public function test_invalid_invite_does_not_partially_change_members_or_course_fields(): void
    {
        DB::unprepared("CREATE TABLE providers (id INTEGER PRIMARY KEY, invite TEXT); INSERT INTO providers VALUES (1, 'TAKEN')");
        $this->actingAs(User::findOrFail(1));
        $this->post('/insider/courses/1/edit', $this->settings(['name' => 'Must not save', 'invite' => 'TAKEN', 'students' => [], 'teachers' => []]))
            ->assertSessionHasErrors('invite');
        $this->assertSame('Python', Course::find(1)->name);
        $this->assertSame('JOIN', Course::find(1)->invite);
        $this->assertSame([3], DB::table('course_students')->pluck('user_id')->all());
        $this->assertSame([2], DB::table('course_teachers')->pluck('user_id')->all());
    }

    public function test_invalid_date_is_rejected_before_members_are_changed(): void
    {
        $this->post('/insider/courses/1/edit', $this->settings(['students' => [], 'start_date' => 'not-a-date']))->assertSessionHasErrors('start_date');
        $this->assertSame([3], DB::table('course_students')->pluck('user_id')->all());
    }

    public function test_icon_picker_renders_after_an_empty_course_name_is_rejected(): void
    {
        session()->flashInput(['name' => null, 'icon_key' => 'auto']);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $html = \Illuminate\Support\Facades\Blade::render('<x-gc-course-icon-picker :course="$course" />', ['course' => Course::find(1), 'errors' => new \Illuminate\Support\ViewErrorBag()]);
        $this->assertStringContainsString('data-icon-picker', $html);
        $this->assertStringContainsString('Автоматически', $html);
    }

    public function test_automatic_icons_work_for_existing_courses_without_database_changes(): void
    {
        foreach (['Python 2026' => 'file-code-2', 'Сети и RSA' => 'network', 'Информатика (ML NN)' => 'brain-circuit',
            'Веб-мастерская' => 'globe', 'Алгоритмы' => 'workflow', 'Хакатон по теории игр' => 'trophy', 'Unknown course' => 'graduation-cap'] as $name => $icon) {
            $course = new Course(['name' => $name]);
            $this->assertSame($icon, CourseIcons::forCourse($course));
            $this->assertNull($course->icon_key);
        }
        $this->assertSame(CourseIcons::svg('graduation-cap'), CourseIcons::svg('../../.env'));
    }

    public function test_all_allowed_assets_are_local_theme_colored_svg_and_migration_is_reversible(): void
    {
        foreach (array_keys(CourseIcons::options()) as $key) {
            $svg = CourseIcons::svg($key);
            $this->assertStringContainsString('stroke="currentColor"', $svg);
            $this->assertDoesNotMatchRegularExpression('/<script|<foreignObject|\bon\w+=|(?:href|src)=/i', $svg);
        }
        (require database_path('migrations/2026_11_11_120000_add_icon_key_to_courses_table.php'))->down();
        $this->assertFalse(Schema::hasColumn('courses', 'icon_key'));
    }
}
