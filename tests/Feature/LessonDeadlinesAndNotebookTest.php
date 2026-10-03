<?php

namespace Tests\Feature;

use App\Lesson;
use App\TaskDeadline;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LessonDeadlinesAndNotebookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('role');
            $table->timestamp('email_verified_at')->nullable();
        });
        Schema::create('courses', function ($table) {
            $table->increments('id');
            $table->integer('program_id');
        });
        foreach (['course_students', 'course_teachers'] as $name) {
            Schema::create($name, function ($table) {
                $table->integer('course_id');
                $table->integer('user_id');
                $table->boolean('is_remote')->default(false);
                $table->boolean('hidden_from_stats')->default(false);
            });
        }
        Schema::create('lessons', function ($table) {
            $table->increments('id');
            $table->integer('program_id');
            $table->boolean('is_open')->default(false);
        });
        Schema::create('program_steps', function ($table) {
            $table->increments('id');
            $table->integer('program_id');
            $table->integer('lesson_id');
            $table->integer('sort_index')->default(0);
            $table->boolean('is_notebook')->default(false);
            $table->text('theory')->nullable();
        });
        Schema::create('tasks', function ($table) {
            $table->increments('id');
            $table->integer('step_id');
            $table->integer('sort_index')->default(0);
            $table->boolean('is_star')->default(false);
        });
        Schema::create('task_deadlines', function ($table) {
            $table->increments('id');
            $table->integer('course_id');
            $table->integer('task_id');
            $table->dateTime('expiration');
            $table->float('penalty');
            $table->timestamps();
        });

        DB::table('users')->insert(['id' => 1, 'name' => 'Admin', 'role' => 'admin', 'email_verified_at' => now()]);
        DB::table('courses')->insert([
            ['id' => 1, 'program_id' => 1],
            ['id' => 2, 'program_id' => 1],
            ['id' => 3, 'program_id' => 2],
        ]);
        DB::table('lessons')->insert(['id' => 1, 'program_id' => 1]);
        DB::table('program_steps')->insert([
            ['id' => 1, 'program_id' => 1, 'lesson_id' => 1],
            ['id' => 2, 'program_id' => 1, 'lesson_id' => 1],
        ]);
        DB::table('tasks')->insert([
            ['id' => 1, 'step_id' => 1, 'is_star' => false],
            ['id' => 2, 'step_id' => 2, 'is_star' => false],
            ['id' => 3, 'step_id' => 2, 'is_star' => true],
        ]);
    }

    private function setLessonDeadline(?string $date = '2026-10-20')
    {
        $this->actingAs(User::findOrFail(1));

        return $this->post('/insider/courses/1/lessons/1/deadline', [
            'deadline' => $date, 'penalty' => 0.5,
        ]);
    }

    private function deadline(int $taskId, int $courseId = 1): TaskDeadline
    {
        return TaskDeadline::create([
            'task_id' => $taskId, 'course_id' => $courseId,
            'expiration' => '2026-10-10', 'penalty' => 0.25,
        ]);
    }

    public function test_lesson_deadline_only_updates_regular_tasks_in_selected_course(): void
    {
        $this->deadline(1);
        $this->deadline(1, 2);
        $this->setLessonDeadline()->assertRedirect();

        $this->assertDatabaseCount('task_deadlines', 3);
        foreach ([1, 2] as $taskId) {
            $this->assertDatabaseHas('task_deadlines', [
                'task_id' => $taskId, 'course_id' => 1, 'penalty' => 0.5,
                'expiration' => '2026-10-20 00:00:00',
            ]);
        }
        $this->assertDatabaseMissing('task_deadlines', ['task_id' => 3]);
        $this->assertDatabaseHas('task_deadlines', ['task_id' => 1, 'course_id' => 2, 'penalty' => 0.25]);
    }

    public function test_lesson_deadline_changes_and_removal_preserve_explicit_star_deadlines(): void
    {
        $starDeadline = $this->deadline(3);
        $this->deadline(1, 2);
        $this->setLessonDeadline()->assertRedirect();
        $this->assertTrue($starDeadline->expiration->eq($starDeadline->fresh()->expiration));
        $this->assertEquals(0.25, $starDeadline->fresh()->penalty);

        $this->setLessonDeadline(null)->assertRedirect();
        $this->assertDatabaseCount('task_deadlines', 2);
        $this->assertDatabaseHas('task_deadlines', ['task_id' => 3, 'course_id' => 1]);
        $this->assertDatabaseHas('task_deadlines', ['task_id' => 1, 'course_id' => 2]);
    }

    public function test_shared_deadline_ignores_star_tasks_and_other_courses(): void
    {
        $this->deadline(3);
        $this->deadline(1, 2);
        $this->setLessonDeadline()->assertRedirect();
        $lesson = Lesson::with('steps.tasks.deadlines')->findOrFail(1);

        $this->assertSame('2026-10-20', $lesson->sharedDeadline(1)->expiration->format('Y-m-d'));
        $this->assertNull($lesson->sharedDeadline(2));
    }

    public function test_shared_deadline_is_absent_for_missing_or_different_regular_deadlines(): void
    {
        $this->assertNull(Lesson::findOrFail(1)->sharedDeadline(1));
        $first = $this->deadline(1);
        $this->assertNull(Lesson::findOrFail(1)->sharedDeadline(1));
        $second = $this->deadline(2);
        $this->assertNotNull(Lesson::findOrFail(1)->sharedDeadline(1));

        $second->update(['expiration' => '2026-10-11']);
        $this->assertNull(Lesson::findOrFail(1)->sharedDeadline(1));
        $second->update(['expiration' => $first->expiration, 'penalty' => 0.75]);
        $this->assertNull(Lesson::findOrFail(1)->sharedDeadline(1));
    }

    public function test_lessons_with_only_star_tasks_or_no_tasks_have_no_shared_deadline(): void
    {
        DB::table('tasks')->update(['is_star' => true]);
        $this->setLessonDeadline()->assertRedirect();
        $this->assertDatabaseCount('task_deadlines', 0);
        $this->assertNull(Lesson::findOrFail(1)->sharedDeadline(1));

        DB::table('tasks')->delete();
        $this->setLessonDeadline()->assertRedirect();
        $this->assertNull(Lesson::findOrFail(1)->sharedDeadline(1));
    }

    public function test_migration_removes_old_star_deadlines_in_all_courses(): void
    {
        $this->deadline(1);
        $this->deadline(2, 2);
        $this->deadline(3);
        $this->deadline(3, 2);

        $migration = require database_path('migrations/2026_10_03_120000_remove_existing_star_task_deadlines.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseCount('task_deadlines', 2);
        $this->assertDatabaseMissing('task_deadlines', ['task_id' => 3]);
    }

    public function test_lesson_deadline_rejects_wrong_program_and_student_access(): void
    {
        $this->actingAs(User::findOrFail(1));
        $this->post('/insider/courses/3/lessons/1/deadline', ['deadline' => '2026-10-20'])->assertNotFound();
        DB::table('users')->where('id', 1)->update(['role' => 'student']);
        $this->actingAs(User::findOrFail(1));
        $this->post('/insider/courses/1/lessons/1/deadline', ['deadline' => '2026-10-20'])->assertForbidden();
        $this->assertDatabaseCount('task_deadlines', 0);
    }

    private function notebook(): string
    {
        $json = json_encode([
            'cells' => [['cell_type' => 'markdown', 'metadata' => (object) [], 'source' => ["# Тетрадка\n", 'Текст & < >']]],
            'metadata' => (object) [], 'nbformat' => 4, 'nbformat_minor' => 5,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        DB::table('program_steps')->where('id', 1)->update(['is_notebook' => true, 'theory' => $json]);

        return $json;
    }

    public function test_notebook_download_preserves_original_json_and_uses_attachment(): void
    {
        $json = $this->notebook();
        $this->actingAs(User::findOrFail(1));
        $response = $this->get('/insider/courses/1/steps/1/notebook');
        $response->assertOk()->assertDownload('step-1.ipynb')->assertHeader('Content-Type', 'application/x-ipynb+json');
        $this->assertSame($json, $response->streamedContent());
        $this->get('/insider/courses/3/steps/1/notebook')->assertNotFound();
        $this->get('/insider/courses/1/steps/2/notebook')->assertNotFound();
    }

    public function test_public_download_requires_open_lesson(): void
    {
        $json = $this->notebook();
        $this->get('/open/steps/1/notebook')->assertForbidden();
        DB::table('lessons')->where('id', 1)->update(['is_open' => true]);
        $response = $this->get('/open/steps/1/notebook');
        $response->assertOk()->assertDownload('step-1.ipynb');
        $this->assertSame($json, $response->streamedContent());
        $this->get('/open/steps/2/notebook')->assertNotFound();
    }

    public function test_private_download_requires_login_and_course_access(): void
    {
        $this->notebook();
        $this->get('/insider/courses/1/steps/1/notebook')->assertRedirect('/login');
        DB::table('users')->where('id', 1)->update(['role' => 'student']);
        $this->actingAs(User::findOrFail(1));
        $this->get('/insider/courses/1/steps/1/notebook')->assertForbidden();
    }
}
