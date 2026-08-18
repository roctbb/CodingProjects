<?php

namespace Tests\Feature;

use App\Jobs\RecalculateCoursePoints;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

class RecalculateOpenedLessonPointsCommandTest extends TestCase
{
    use WithoutMiddleware;

    private $originalDefaultConnection;
    private $originalSqliteDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = config('database.default');
        $this->originalSqliteDatabase = config('database.connections.sqlite.database');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('lesson_info', function ($table) {
            $table->increments('id');
            $table->integer('course_id')->nullable();
            $table->integer('lesson_id')->nullable();
            $table->date('start_date')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('lesson_info');
        DB::disconnect('sqlite');

        config([
            'database.default' => $this->originalDefaultConnection,
            'database.connections.sqlite.database' => $this->originalSqliteDatabase,
        ]);

        parent::tearDown();
    }

    public function testItQueuesEachCourseWhoseLessonOpensTodayOnce(): void
    {
        Carbon::setTestNow('2026-08-18 00:01:00');
        Bus::fake();

        DB::table('lesson_info')->insert([
            ['course_id' => 10, 'lesson_id' => 1, 'start_date' => '2026-08-18'],
            ['course_id' => 10, 'lesson_id' => 2, 'start_date' => '2026-08-18'],
            ['course_id' => 20, 'lesson_id' => 3, 'start_date' => '2026-08-19'],
        ]);

        $this->artisan('course:recalculate-opened-lessons')->assertSuccessful();

        Bus::assertDispatchedTimes(RecalculateCoursePoints::class, 1);
        Bus::assertDispatched(RecalculateCoursePoints::class, function ($job) {
            $courseId = new ReflectionProperty($job, 'courseId');
            $courseId->setAccessible(true);

            return $courseId->getValue($job) === 10;
        });
    }
}
