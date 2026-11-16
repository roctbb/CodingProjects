<?php

namespace Tests\Feature;

use App\Course;
use App\CoursePlanner\Models\PlannerCourse;
use App\Jobs\SendDeadlineNotification;
use App\Services\CourseDeadlineService;
use App\Services\CourseTelegram;
use App\Services\DeadlineNotifications;
use App\Services\DeadlineNotificationSender;
use App\Task;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DeadlineFeatureCase;

class DeadlineChangeNotificationsTest extends DeadlineFeatureCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 123]])]);
    }

    private function chat(): int
    {
        return DB::table('course_telegram_chats')->insertGetId([
            'course_id' => 1, 'bot_key' => app(CourseTelegram::class)->botKey(), 'chat_id' => '-1009',
            'thread_id' => 17, 'title' => 'Класс Python', 'status' => 'active', 'linked_by' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function deliverAll(): void
    {
        foreach (DB::table('deadline_notification_deliveries')->where('status', 'queued')->pluck('id') as $id) {
            app(DeadlineNotificationSender::class)->send($id);
        }
    }

    public function test_task_creation_and_edits_queue_one_group_message_immediately_with_all_stages(): void
    {
        $this->chat();
        User::find(2)->forceFill(['telegram_chat_id' => '202'])->save();
        User::find(1)->forceFill(['telegram_chat_id' => '101'])->save();
        DB::table('course_notification_preferences')->insert(['course_id' => 1, 'user_id' => 1, 'enabled' => true]);
        $this->actingAs(User::find(2));
        $url = '/insider/courses/1/deadline-policies/task/11';
        $input = ['revision' => 0, 'mode' => 'custom', 'timezone' => 'Europe/Moscow', 'stages' => [
            ['deadline_date' => '2026-11-20', 'retained_percent' => 70],
            ['deadline_date' => '2026-11-27', 'retained_percent' => 0],
        ]];
        $this->postJson($url, $input)->assertOk()->assertJsonPath('revision', 1);
        Queue::assertPushed(SendDeadlineNotification::class, 1);
        $this->assertDatabaseCount('deadline_notification_deliveries', 1);
        $this->deliverAll();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['chat_id'] === '-1009' && $r['message_thread_id'] === 17
            && str_contains($r['text'], 'Установлены сроки сдачи') && str_contains($r['text'], Task::find(11)->name)
            && str_contains($r['text'], '20.11.2026 23:59 (Europe/Moscow)') && str_contains($r['text'], '27.11.2026 23:59'));

        $input['revision'] = 1;
        $input['stages'][0]['deadline_date'] = '2026-11-21';
        $this->postJson($url, $input)->assertOk()->assertJsonPath('revision', 2);
        Queue::assertPushed(SendDeadlineNotification::class, 2);
        $this->deliverAll();
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => $r['chat_id'] === '-1009' && str_contains($r['text'], 'Изменены сроки сдачи')
            && str_contains($r['text'], '21.11.2026 23:59'));
        $this->assertSame(0, DB::table('deadline_notification_deliveries')->where('recipient_type', '!=', 'chat')->count());
    }

    public function test_saving_identical_dates_and_percentages_does_not_repeat_or_invalidate_the_queued_message(): void
    {
        $this->chat();
        $policy = $this->policy();
        $editor = app(CourseDeadlineService::class)->editor(Course::find(1), 'task', 11);
        $editor['stages'] = array_reverse($editor['stages']);
        $editor['stages'][0]['retained_percent'] = '0.0';
        $same = app(CourseDeadlineService::class)->savePolicy(Course::find(1), 'task', 11, $editor, 2);
        $this->assertSame($policy->revision, $same->revision);
        Queue::assertPushed(SendDeadlineNotification::class, 1);
        $this->assertDatabaseCount('deadline_notification_events', 1);
        $this->deliverAll();
        app(DeadlineNotifications::class)->dispatchDue();
        $this->deliverAll();
        Http::assertSentCount(1);
        $this->assertDatabaseHas('deadline_notification_deliveries', ['status' => 'sent', 'attempts' => 1]);
    }

    public function test_changing_only_the_retained_percentage_sends_an_update(): void
    {
        $this->policy();
        $this->chat();
        $this->policy(stages: [['deadline_date' => '2026-11-15', 'retained_percent' => 45]]);
        Queue::assertPushed(SendDeadlineNotification::class, 1);
        $this->deliverAll();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r['text'], 'Изменены сроки сдачи') && str_contains($r['text'], '45% баллов'));
    }

    public function test_whole_lesson_sends_one_message_instead_of_one_per_task(): void
    {
        $this->policy('task', 11);
        $this->policy('task', 12);
        $this->chat();
        $this->actingAs(User::find(2))->post('/insider/courses/1/lessons/1/deadline', [
            'deadline' => '2026-11-20', 'penalty' => 0.5,
        ])->assertRedirect();
        Queue::assertPushed(SendDeadlineNotification::class, 1);
        $this->assertDatabaseCount('deadline_notification_deliveries', 1);
        $this->deliverAll();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r['text'], Task::find(11)->name)
            && str_contains($r['text'], Task::find(12)->name) && str_contains($r['text'], '20.11.2026 23:59'));

        $this->post('/insider/courses/1/lessons/1/deadline', ['deadline' => '2026-11-21', 'penalty' => 0.5])->assertRedirect();
        Queue::assertPushed(SendDeadlineNotification::class, 2);
        $this->deliverAll();
        Http::assertSent(fn ($r) => str_contains($r['text'], 'Изменены сроки сдачи') && str_contains($r['text'], '21.11.2026 23:59'));
    }

    public function test_applying_unchanged_lesson_dates_to_individual_overrides_is_an_update(): void
    {
        $this->policy('lesson', 1);
        $this->policy('task', 11, [['deadline_date' => '2026-12-01', 'retained_percent' => 10]]);
        $this->chat();
        $this->policy('lesson', 1, extra: ['apply_to_tasks' => true]);
        Queue::assertPushed(SendDeadlineNotification::class, 1);
        $this->deliverAll();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r['text'], 'Изменены сроки сдачи') && str_contains($r['text'], Task::find(11)->name));
        $this->policy('lesson', 1, extra: ['apply_to_tasks' => true]);
        Queue::assertPushed(SendDeadlineNotification::class, 1);
    }

    public function test_legacy_task_endpoint_also_queues_notifications(): void
    {
        $this->chat();
        $this->actingAs(User::find(2))->post('/insider/courses/1/tasks/11/deadline', [
            'deadline' => '2026-11-20', 'penalty' => 0.5,
        ])->assertRedirect();
        Queue::assertPushed(SendDeadlineNotification::class, 1);
        $this->deliverAll();
        Http::assertSentCount(1);
    }

    public function test_planner_changes_queue_a_message_after_commit_and_replaying_the_command_is_quiet(): void
    {
        $this->chat();
        $planner = PlannerCourse::where('course_id', 1)->firstOrFail();
        $input = ['task_id' => 11, 'mode' => 'custom', 'policy_revision' => 0, 'timezone' => 'Europe/Moscow',
            'stages' => [['deadline_date' => '2026-11-20', 'retained_percent' => 70]],
            'revision' => $planner->revision, 'operation_id' => (string) Str::uuid()];
        $url = '/insider/courses/1/planner/api/commands/set_deadline';
        $this->actingAs(User::find(2))->postJson($url, $input)->assertOk();
        Queue::assertPushed(SendDeadlineNotification::class, 1);
        $this->postJson($url, $input)->assertOk();
        Queue::assertPushed(SendDeadlineNotification::class, 1);
        $this->deliverAll();
        Http::assertSentCount(1);
    }

    public function test_scheduler_recovers_a_committed_change_if_immediate_publication_was_lost(): void
    {
        $this->chat();
        $this->policy();
        // Simulate a process stopping after the event commit but before preparing its deliveries.
        DB::table('deadline_notification_deliveries')->delete();
        DB::table('deadline_notification_events')->update(['status' => 'pending']);
        Queue::fake();
        $this->assertSame(1, app(DeadlineNotifications::class)->dispatchDue());
        $this->assertSame(0, app(DeadlineNotifications::class)->dispatchDue());
        Queue::assertPushed(SendDeadlineNotification::class, 1);
        $this->deliverAll();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r['text'], 'Установлены сроки сдачи'));
    }

    public function test_disabling_the_chat_after_saving_cancels_the_queued_announcement(): void
    {
        $chat = $this->chat();
        $this->policy();
        Queue::assertPushed(SendDeadlineNotification::class, 1);
        DB::table('course_telegram_chats')->where('id', $chat)->update(['status' => 'disabled']);
        $this->deliverAll();
        Http::assertNothingSent();
        $this->assertDatabaseHas('deadline_notification_deliveries', ['status' => 'cancelled']);
    }

    public function test_many_stages_still_fit_in_one_telegram_message(): void
    {
        $this->chat();
        $stages = [];
        for ($i = 1; $i <= 50; $i++) $stages[] = ['deadline_date' => now()->addDays($i)->toDateString(), 'retained_percent' => 0];
        $this->policy(stages: $stages);
        $this->deliverAll();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => mb_strlen($r['text']) <= 4096 && str_contains($r['text'], 'И ещё 30 сроков')
            && str_contains($r['text'], '/insider/courses/1/deadlines'));
    }

    public function test_publication_waits_for_the_outermost_commit_and_rollback_discards_the_event(): void
    {
        $this->chat();
        DB::beginTransaction();
        $this->policy();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
        DB::rollBack();
        $this->assertDatabaseCount('deadline_policies', 0);
        $this->assertDatabaseCount('deadline_notification_events', 0);
        app(CourseDeadlineService::class)->forget();

        DB::beginTransaction();
        $this->policy();
        Queue::assertNothingPushed();
        DB::commit();
        Queue::assertPushed(SendDeadlineNotification::class, 1);
        $this->deliverAll();
        Http::assertSentCount(1);
    }

    public function test_superseded_edits_and_removed_deadlines_cancel_queued_announcements(): void
    {
        $this->chat();
        $this->policy();
        $this->policy(stages: [['deadline_date' => '2026-12-01', 'retained_percent' => 50]]);
        $this->deliverAll();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r['text'], 'Изменены сроки сдачи') && str_contains($r['text'], '01.12.2026'));
        $this->assertDatabaseHas('deadline_notification_deliveries', ['status' => 'cancelled']);

        $this->policy(stages: [['deadline_date' => '2026-12-02', 'retained_percent' => 50]]);
        $this->policy(extra: ['mode' => 'none', 'stages' => []]);
        $this->deliverAll();
        Http::assertSentCount(1);
    }

    #[DataProvider('quietChanges')]
    public function test_changes_respect_chat_settings_and_public_visibility(string $reason): void
    {
        $chat = $this->chat();
        match ($reason) {
            'pending', 'disabled' => DB::table('course_telegram_chats')->where('id', $chat)->update(['status' => $reason]),
            'other_bot' => DB::table('course_telegram_chats')->where('id', $chat)->update(['bot_key' => str_repeat('0', 64)]),
            'muted' => DB::table('course_notification_settings')->insert(['course_id' => 1, 'chat_enabled' => false]),
            'hidden' => DB::table('tasks')->where('id', 11)->update(['is_hidden' => true]),
            'unopened_lesson' => DB::table('lesson_info')->where('course_id', 1)->where('lesson_id', 1)->update(['start_date' => '2027-01-01']),
            'draft' => DB::table('courses')->where('id', 1)->update(['state' => 'draft']),
        };
        $this->policy();
        app(DeadlineNotifications::class)->dispatchDue();
        Queue::assertNothingPushed();
        $this->deliverAll();
        Http::assertNothingSent();
    }

    public static function quietChanges(): array
    {
        return array_map(fn ($reason) => [$reason], ['pending', 'disabled', 'other_bot', 'muted', 'hidden', 'unopened_lesson', 'draft']);
    }
}
