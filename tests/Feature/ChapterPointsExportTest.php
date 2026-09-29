<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ChapterPointsExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('role')->default('student');
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
        Schema::create('program_chapters', function ($table) {
            $table->increments('id');
            $table->integer('program_id');
            $table->string('name');
        });
        Schema::create('lessons', function ($table) {
            $table->increments('id');
            $table->integer('program_id');
            $table->integer('chapter_id');
            $table->string('name');
            $table->integer('sort_index')->default(0);
        });
        Schema::create('lesson_info', function ($table) {
            $table->increments('id');
            $table->integer('course_id');
            $table->integer('lesson_id');
            $table->date('start_date')->nullable();
        });
        Schema::create('program_steps', function ($table) {
            $table->increments('id');
            $table->integer('lesson_id');
            $table->integer('sort_index')->default(0);
        });
        Schema::create('tasks', function ($table) {
            $table->increments('id');
            $table->integer('step_id');
            $table->integer('sort_index')->default(0);
            $table->integer('max_mark');
            $table->boolean('is_star')->default(false);
            $table->boolean('is_hidden')->default(false);
        });
        Schema::create('solutions', function ($table) {
            $table->increments('id');
            $table->integer('task_id');
            $table->integer('course_id');
            $table->integer('user_id');
            $table->integer('mark');
        });
        Schema::create('lesson_student_stats', function ($table) {
            $table->increments('id');
            $table->integer('course_id');
            $table->integer('lesson_id');
            $table->integer('student_id');
            $table->integer('points');
            $table->integer('max_points');
            $table->float('percent');
            $table->timestamps();
            $table->unique(['course_id', 'lesson_id', 'student_id']);
        });

        DB::table('courses')->insert(['id' => 1, 'program_id' => 1]);
        DB::table('program_chapters')->insert(['id' => 1, 'program_id' => 1, 'name' => 'Введение']);
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Teacher', 'role' => 'teacher', 'email_verified_at' => now()],
            ['id' => 2, 'name' => '=1+1', 'role' => 'student', 'email_verified_at' => now()],
            ['id' => 3, 'name' => 'Zero', 'role' => 'student', 'email_verified_at' => now()],
            ['id' => 4, 'name' => 'Hidden', 'role' => 'student', 'email_verified_at' => now()],
        ]);
        DB::table('course_teachers')->insert(['course_id' => 1, 'user_id' => 1]);
        DB::table('course_students')->insert([
            ['course_id' => 1, 'user_id' => 2, 'hidden_from_stats' => false],
            ['course_id' => 1, 'user_id' => 3, 'hidden_from_stats' => false],
            ['course_id' => 1, 'user_id' => 4, 'hidden_from_stats' => true],
        ]);
        $this->actingAs(User::findOrFail(1));
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function testExportRecalculatesLessonPointsAndTotalsInLessonOrder(): void
    {
        $this->addLesson(1, 'Второй урок', 20, 1, 20);
        $this->addLesson(2, '=Первый урок', 10, 1, 10);
        $this->addLesson(3, 'Другая глава', 30, 2, 100);
        $this->addLesson(4, 'Будущий урок', 40, 1, 50, false);
        foreach ([[1, 1, 4], [1, 1, 7], [2, 1, 9], [3, 1, 100], [4, 1, 50], [1, 2, 20]] as [$task, $course, $mark]) {
            DB::table('solutions')->insert(['task_id' => $task, 'course_id' => $course, 'user_id' => 2, 'mark' => $mark]);
        }
        DB::table('lesson_student_stats')->insert([
            'course_id' => 1, 'lesson_id' => 1, 'student_id' => 2,
            'points' => 999, 'max_points' => 999, 'percent' => 100,
        ]);

        $sheet = $this->exportSheet();
        $this->assertSame([
            ['Имя ученика', '=Первый урок — Балл', '=Первый урок — Максимальный балл', 'Второй урок — Балл', 'Второй урок — Максимальный балл', 'Будущий урок — Балл', 'Будущий урок — Максимальный балл', 'Итого баллов', 'Итого максимальный балл'],
            ['=1+1', 9, 10, 7, 20, 0, 0, 16, 30],
            ['Zero', 0, 10, 0, 20, 0, 0, 0, 30],
        ], $sheet->toArray(null, false, false));
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B1')->getDataType());
        $this->assertSame('B2', $sheet->getFreezePane());
    }

    public function testEmptyChapterExportsStudentsWithZeroTotals(): void
    {
        $this->assertSame([
            ['Имя ученика', 'Итого баллов', 'Итого максимальный балл'],
            ['=1+1', 0, 0],
            ['Zero', 0, 0],
        ], $this->exportSheet()->toArray(null, false, false));
    }

    public function testExportSupportsColumnsBeyondZAndNoStudents(): void
    {
        DB::table('course_students')->delete();
        for ($id = 1; $id <= 14; $id++) {
            $this->addLesson($id, 'Урок ' . $id, $id, 1, 10);
        }
        $sheet = $this->exportSheet();
        $this->assertSame('AE', $sheet->getHighestColumn());
        $this->assertSame(1, $sheet->getHighestRow());
        $this->assertSame('Урок 14 — Балл', $sheet->getCell('AB1')->getValue());
        $this->assertSame('Итого баллов', $sheet->getCell('AD1')->getValue());
    }

    public function testChapterMustBelongToCourseProgram(): void
    {
        DB::table('program_chapters')->insert(['id' => 2, 'program_id' => 2, 'name' => 'Чужая глава']);
        $this->get('/insider/courses/1/chapters/2/export-points')->assertNotFound();
        $this->get('/insider/courses/1/chapters/999/export-points')->assertNotFound();
    }

    public function testStudentCannotExportPoints(): void
    {
        $this->actingAs(User::findOrFail(2));
        $this->get('/insider/courses/1/chapters/1/export-points')->assertForbidden();
    }

    public function testTeacherWithoutCourseAccessCannotExportPoints(): void
    {
        DB::table('course_teachers')->delete();
        $this->get('/insider/courses/1/chapters/1/export-points')->assertForbidden();
    }

    private function addLesson(int $id, string $name, int $order, int $chapter, int $max, bool $opened = true): void
    {
        DB::table('lessons')->insert(['id' => $id, 'program_id' => 1, 'chapter_id' => $chapter, 'name' => $name, 'sort_index' => $order]);
        DB::table('lesson_info')->insert(['course_id' => 1, 'lesson_id' => $id, 'start_date' => $opened ? '2020-01-01' : '2099-01-01']);
        DB::table('program_steps')->insert(['id' => $id, 'lesson_id' => $id]);
        DB::table('tasks')->insert(['id' => $id, 'step_id' => $id, 'max_mark' => $max]);
    }

    private function exportSheet()
    {
        $response = $this->get('/insider/courses/1/chapters/1/export-points');
        $response->assertOk();
        $response->assertDownload('chapter-1-points-Vvedenie.xlsx');
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            return IOFactory::load($path)->getActiveSheet();
        } finally {
            unlink($path);
        }
    }
}
