<?php
namespace Tests\Support;

use App\Course;
use App\Services\CourseDeadlineService;
use App\Solution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

abstract class DeadlineFeatureCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'app.timezone' => 'Europe/Moscow',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'session.driver' => 'array', 'cache.default' => 'array',
            'services.telegram' => ['bot_token' => '123:fake-key', 'bot_username' => 'class_test_bot', 'api_url' => 'https://telegram.example',
                'timeout' => 10, 'proxy' => null, 'webhook_secret' => 'test-webhook-secret']]);
        DB::purge('sqlite');
        DB::unprepared(file_get_contents(base_path('scripts/ui-checks/fixture.sql')));
        DB::table('solutions')->delete(); DB::table('task_deadlines')->delete();
        Carbon::setTestNow(Carbon::parse('2026-11-14 10:00:00', 'Europe/Moscow'));
        foreach (['2026_11_14_120000_create_code_checking_tables.php', '2026_11_14_130000_expand_task_submissions.php',
            '2026_11_14_140000_complete_native_submission_storage.php', '2026_11_13_180000_create_reward_notices_table.php',
            '2026_11_14_190000_create_deadline_policies.php', '2026_11_14_200000_create_deadline_notifications.php',
            '2026_11_14_210000_create_classroom_bonuses.php', '2026_11_14_220000_harden_business_processes.php'] as $file) (require database_path('migrations/'.$file))->up();
        app()->forgetInstance(CourseDeadlineService::class);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Http::preventStrayRequests(); Queue::fake(); Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); DB::disconnect('sqlite'); parent::tearDown();
    }

    protected function policy(string $type = 'task', int $id = 11, array $stages = [], array $extra = [])
    {
        $service = app(CourseDeadlineService::class);
        $current = $service->editor(Course::findOrFail(1), $type, $id);
        return $service->savePolicy(Course::findOrFail(1), $type, $id, $extra + ['revision' => $current['revision'],
            'mode' => 'custom', 'timezone' => 'Europe/Moscow', 'stages' => $stages ?: [
                ['deadline_date' => '2026-11-15', 'retained_percent' => 70],
                ['deadline_date' => '2026-11-22', 'retained_percent' => 0],
            ]], 2);
    }

    protected function solution(string $at = '2026-11-14 10:00:00', ?int $mark = null, int $task = 11, int $user = 1): Solution
    {
        $solution = new Solution();
        $solution->forceFill(['course_id' => 1, 'task_id' => $task, 'user_id' => $user, 'submitted' => Carbon::parse($at, 'Europe/Moscow'), 'text' => 'Ответ']);
        if ($mark !== null) $solution->applyDeadlinePenalty($mark);
        $solution->save();
        return $solution;
    }
}
