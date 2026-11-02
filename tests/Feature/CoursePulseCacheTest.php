<?php

namespace Tests\Feature;

use App\CourseActivity;
use App\Services\CoursePulse;
use Carbon\Carbon;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class CoursePulseCacheTest extends TestCase
{
    private int $activityReads = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-11-03 12:00:00', 'Europe/Moscow'));
        Schema::create('course_activities', function ($table) {
            $table->increments('id');
            $table->integer('course_id')->nullable();
            $table->string('type');
            $table->timestamps();
        });
        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'course_activities')) {
                $this->activityReads++;
            }
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function activity(?int $courseId = 1, string $type = CourseActivity::TYPE_SOLUTION_SUBMITTED): void
    {
        DB::table('course_activities')->insert([
            'course_id' => $courseId, 'type' => $type,
            'created_at' => now()->subMinutes(6), 'updated_at' => now(),
        ]);
    }

    private function refreshLock()
    {
        $key = (new ReflectionMethod(CoursePulse::class, 'cacheKey'))->invoke(new CoursePulse(), [1]);
        return Cache::lock($key.':lock', 15);
    }

    public function test_course_set_cache_is_shared_independent_of_order_and_duplicates(): void
    {
        $this->activity();
        $pulse = (new CoursePulse())->forCourses([1, 2]);
        $this->activity();
        $this->assertSame($pulse, (new CoursePulse())->forCourses([2, '1', 2, 0, null, -1]));
        $this->assertSame(1, $this->activityReads);
        $this->assertSame(35, $pulse['current']);
    }

    public function test_different_course_sets_and_global_events_are_isolated(): void
    {
        $this->activity(1);
        for ($i = 0; $i < 10; $i++) {
            $this->activity(2);
        }
        $service = new CoursePulse();
        $this->assertSame(35, $service->forCourses([1])['current']);
        $this->assertSame(99, $service->forCourses([2])['current']);
        $this->activity(null); // Course submissions without a course are not public global events.
        $this->assertSame(0, $service->forCourses([])['current']);
        Cache::flush();
        $this->activity(null, CourseActivity::TYPE_RANDOM_COIN_DROP);
        $this->assertGreaterThan(0, $service->forCourses([])['current']);
        $this->assertGreaterThan(35, $service->forCourses([1])['current']);
    }

    public function test_stale_result_returns_without_query_and_refreshes_after_response(): void
    {
        $this->activity();
        $service = new CoursePulse();
        $old = $service->forCourses([1]);
        Carbon::setTestNow(now()->addSeconds(29));
        $this->activity();
        $this->assertSame($old, $service->forCourses([1]));
        $this->assertSame(1, $this->activityReads);

        Carbon::setTestNow(now()->addSeconds(2));
        $this->assertSame($old, $service->forCourses([1]));
        $this->assertSame($old, $service->forCourses([1]));
        $this->assertSame(1, $this->activityReads);
        $this->assertCount(1, app(DeferredCallbackCollection::class));
        app(DeferredCallbackCollection::class)->invoke();
        $this->assertSame(2, $this->activityReads);
        $this->assertGreaterThan($old['current'], $service->forCourses([1])['current']);
        $this->assertSame(2, $this->activityReads);
    }

    public function test_http_kernel_runs_the_deferred_refresh(): void
    {
        Route::get('/__test__/course-pulse', fn () => (new CoursePulse())->forCourses([1]));
        $this->activity();
        $first = $this->getJson('/__test__/course-pulse')->assertOk()->json();
        Carbon::setTestNow(now()->addSeconds(31));
        $this->activity();
        $this->getJson('/__test__/course-pulse')->assertOk()->assertJsonPath('current', $first['current']);
        $this->assertGreaterThan($first['current'], (new CoursePulse())->forCourses([1])['current']);
        $this->assertSame(2, $this->activityReads);
    }

    public function test_expired_result_is_rebuilt_synchronously(): void
    {
        $this->activity();
        $service = new CoursePulse();
        $first = $service->forCourses([1]);
        Carbon::setTestNow(now()->addSeconds(121));
        $this->activity();
        $this->assertNotSame($first, $service->forCourses([1]));
        $this->assertSame(2, $this->activityReads);
        $this->assertCount(0, app(DeferredCallbackCollection::class));
    }

    public function test_only_one_worker_refreshes_a_stale_course_set(): void
    {
        $this->activity();
        $service = new CoursePulse();
        $old = $service->forCourses([1]);
        Carbon::setTestNow(now()->addSeconds(31));
        $this->activity();
        $lock = $this->refreshLock();
        $this->assertTrue($lock->get());
        try {
            $this->assertSame($old, $service->forCourses([1]));
            app(DeferredCallbackCollection::class)->invoke();
            $this->assertSame(1, $this->activityReads);
        } finally {
            $lock->release();
        }
        $service->forCourses([1]);
        app(DeferredCallbackCollection::class)->invoke();
        $this->assertSame(2, $this->activityReads);
    }

    public function test_cold_lock_timeout_does_not_fail_the_page_or_publish_over_another_worker(): void
    {
        // Laravel's blocking lock uses now() to measure its wait deadline.
        Carbon::setTestNow();
        $this->activity();
        $lock = $this->refreshLock();
        $this->assertTrue($lock->get());
        try {
            $this->assertSame(35, (new CoursePulse())->forCourses([1])['current']);
        } finally {
            $lock->release();
        }
        (new CoursePulse())->forCourses([1]);
        $this->assertSame(2, $this->activityReads);
    }

    public function test_failed_refresh_keeps_last_good_result(): void
    {
        $this->activity();
        $service = new CoursePulse();
        $old = $service->forCourses([1]);
        Carbon::setTestNow(now()->addSeconds(31));
        Schema::drop('course_activities');
        Log::shouldReceive('warning')->once();
        $this->assertSame($old, $service->forCourses([1]));
        app(DeferredCallbackCollection::class)->invoke();
        $this->assertSame($old, $service->forCourses([1]));
    }

    public function test_timestamp_calculation_preserves_every_point_of_the_original_series(): void
    {
        $now = Carbon::parse('2026-11-03 12:00:00.123456', 'Europe/Moscow');
        $types = [CourseActivity::TYPE_SOLUTION_SUBMITTED, CourseActivity::TYPE_SOLUTION_CHECKED,
            CourseActivity::TYPE_TASK_AI_SUMMARY, CourseActivity::TYPE_MARKET_DIGITAL_PURCHASE,
            CourseActivity::TYPE_LEARNING_STREAK, CourseActivity::TYPE_PET_ACTION, 'unknown'];
        $events = collect();
        for ($i = 0; $i < 180; $i++) {
            $events->push((new CourseActivity())->newFromBuilder([
                'type' => $types[$i % count($types)], 'created_at' => $now->copy()->subMinutes(6 + $i * 10)->format('Y-m-d H:i:s.uP'),
            ]));
        }
        for ($i = 0; $i < 40; $i++) {
            $events->push((new CourseActivity())->newFromBuilder([
                'type' => CourseActivity::TYPE_SOLUTION_SUBMITTED, 'created_at' => $now->copy()->subMinutes(6)->format('Y-m-d H:i:s.uP'),
            ]));
        }
        $events->push((new CourseActivity())->newFromBuilder(['type' => 'unknown', 'created_at' => $now->copy()->addMinutes(10)->format('Y-m-d H:i:s.uP')]));
        $events->push(new CourseActivity(['type' => 'unknown', 'created_at' => null]));
        $prepared = (new ReflectionMethod(CourseActivity::class, 'preparePulseActivities'))->invoke(null, $events);
        $values = [];
        $calculate = new ReflectionMethod(CourseActivity::class, 'pulseValueAt');
        for ($i = 180; $i >= 0; $i--) {
            $values[] = $calculate->invoke(null, $prepared, $now->copy()->subMinutes($i * 8));
        }
        $values[] = $calculate->invoke(null, $prepared, $now->copy()->subHours(6));
        $expected = json_decode(file_get_contents(base_path('tests/Fixtures/course-pulse-series.json')), true);
        $this->assertCount(182, $expected);
        $this->assertSame($expected, $values);
    }
}
