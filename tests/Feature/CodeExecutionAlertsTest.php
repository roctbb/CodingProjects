<?php

namespace Tests\Feature;

use App\CodeExecutionRun;
use App\CodeSubmission;
use App\Jobs\DispatchCodeExecution;
use App\Jobs\SendCodeExecutionAlert;
use App\Services\CodeChecking\Artifacts;
use App\Services\CodeChecking\ExecutionAlerts;
use App\Services\CodeChecking\Grading;
use App\Services\CodeChecking\Integrity;
use App\Services\CodeChecking\Results;
use App\Services\TaskLocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class CodeExecutionAlertsTest extends DeadlineFeatureCase
{
    private $telegramResponse;

    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_11_15_040000_create_code_execution_alerts.php'))->up();
        config(['code_checking.ai_detection_enabled' => false]);
        Storage::fake('code_checking'); Storage::fake('code_originals');
        DB::table('users')->where('id', 2)->update(['telegram_chat_id' => '2222']);
        DB::table('users')->where('id', 5)->update(['telegram_chat_id' => '5555']);
        $this->telegramResponse = fn () => Http::response(['ok' => true, 'result' => ['message_id' => 17]]);
        Http::fake(['https://telegram.example/*' => fn ($request) => ($this->telegramResponse)($request)]);
    }

    private function execution(int $author = 1, string $state = 'queued'): CodeExecutionRun
    {
        $id = (string) Str::uuid(); $runId = (string) Str::uuid();
        CodeSubmission::withoutEvents(fn () => CodeSubmission::create([
            'id' => $id, 'user_id' => $author, 'course_id' => 1, 'task_id' => 11,
            'request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'language' => 'python',
            'files' => [], 'current_run_id' => $runId, 'preparation_state' => 'ready',
        ]));
        return CodeExecutionRun::create(['id' => $runId, 'submission_id' => $id, 'snapshot' => ['points' => 10, 'check_type' => 'tests'],
            'state' => $state, 'finished_at' => $state === 'error' ? now() : null]);
    }

    private function sendAll(): void
    {
        foreach (DB::table('code_execution_alerts')->where('status', 'queued')->pluck('id') as $id) {
            $job = new SendCodeExecutionAlert((int) $id);
            $job->handle(app(ExecutionAlerts::class));
            $job->handle(app(ExecutionAlerts::class));
        }
    }

    private function report(CodeExecutionRun $run, int $percent = 95, bool $dismissed = false): void
    {
        DB::table('code_integrity_reports')->updateOrInsert(['submission_id' => $run->submission_id], [
            'state' => 'completed', 'plagiarism' => json_encode(['state' => 'done', 'warning' => true,
                'matches' => [['submission_id' => 'PRIVATE OTHER SOURCE', 'percent' => $percent]]]),
            'ai' => json_encode(['state' => 'done', 'warning' => true, 'confidence' => 'high', 'reasons' => ['PRIVATE AI REASON']]),
            'dismissed_at' => $dismissed ? now() : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_durable_error_callback_notifies_only_assigned_teachers_once_without_raw_payload(): void
    {
        DB::table('course_teachers')->insert(['course_id' => 1, 'user_id' => 6]);
        DB::table('users')->where('id', 6)->update(['role' => 'teacher', 'telegram_chat_id' => '6666']);
        $run = $this->execution();
        $data = ['execution_id' => $run->id, 'state' => 'execution error', 'points' => 0,
            'comment' => 'SECRET TOKEN and PRIVATE CODE / hidden test / stacktrace'];
        $hash = hash('sha256', json_encode($data));
        app(Results::class)->accept($run, $data, $hash);
        app(Results::class)->accept($run->fresh(), $data, $hash);
        $this->assertDatabaseCount('code_execution_alerts', 2);
        Queue::assertPushed(SendCodeExecutionAlert::class, 2);
        $this->sendAll();
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['chat_id'] === '2222'
            && str_contains($request['text'], app(TaskLocation::class)->submission($run->submission))
            && !str_contains($request['text'], 'SECRET') && !str_contains($request['text'], 'PRIVATE')
            && !str_contains($request['text'], 'stacktrace') && !isset($request['parse_mode']));
        Http::assertNotSent(fn ($request) => $request['chat_id'] === '5555');
        $this->assertSame(2, DB::table('code_execution_alerts')->where('status', 'sent')->count());
    }

    public function test_zero_point_completed_result_is_not_a_technical_alert(): void
    {
        $run = $this->execution();
        $this->mock(Grading::class)->shouldReceive('apply')->once();
        $data = ['execution_id' => $run->id, 'state' => 'done', 'points' => 0, 'comment' => 'Wrong answer / compilation error'];
        app(Results::class)->accept($run, $data, hash('sha256', json_encode($data)));
        $this->assertDatabaseCount('code_execution_alerts', 0);
        Queue::assertNotPushed(SendCodeExecutionAlert::class);
    }

    public function test_teacher_own_technical_error_is_delivered_but_staff_work_is_not_moderated_for_plagiarism(): void
    {
        $run = $this->execution(2, 'error');
        app(ExecutionAlerts::class)->technicalError($run);
        DB::table('course_students')->insert(['course_id' => 1, 'user_id' => 2]);
        $this->report($run);
        app(ExecutionAlerts::class)->plagiarism($run);
        $this->assertDatabaseCount('code_execution_alerts', 1);
        $this->assertDatabaseHas('code_execution_alerts', ['kind' => 'technical_error', 'recipient_user_id' => 2]);
        $this->sendAll(); Http::assertSentCount(1);
    }

    public function test_only_high_confidence_current_course_student_matches_alert_and_dismissal_cancels_delivery(): void
    {
        $run = $this->execution();
        $this->report($run, 89);
        app(ExecutionAlerts::class)->plagiarism($run);
        $this->assertDatabaseCount('code_execution_alerts', 0);
        $this->report($run, 95);
        app(ExecutionAlerts::class)->plagiarism($run);
        app(ExecutionAlerts::class)->plagiarism($run);
        $this->assertDatabaseCount('code_execution_alerts', 1);
        DB::table('code_integrity_reports')->where('submission_id', $run->submission_id)->update(['dismissed_at' => now()]);
        $this->sendAll(); Http::assertNothingSent();
        $this->assertDatabaseHas('code_execution_alerts', ['status' => 'cancelled']);
    }

    public function test_integrity_pipeline_emits_one_alert_after_classifying_multiple_matches(): void
    {
        $first = $this->execution(3); $second = $this->execution(4); $checked = $this->execution(1);
        $source = str_repeat('total = total + value * 17; output(total); ', 30);
        $artifact = app(Artifacts::class)->put($source);
        foreach ([$first, $second, $checked] as $run) {
            $run->submission->update(['files' => [['name' => 'main.py', 'artifact' => $artifact]]]);
        }
        app(Integrity::class)->check($first->submission->fresh());
        app(Integrity::class)->check($second->submission->fresh());
        DB::table('code_execution_alerts')->delete(); Queue::fake();
        app(Integrity::class)->check($checked->submission->fresh());
        app(Integrity::class)->check($checked->submission->fresh());
        $this->assertDatabaseCount('code_execution_alerts', 1);
        Queue::assertPushed(SendCodeExecutionAlert::class, 1);
        $this->sendAll(); Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request['text'], '100%') && !str_contains($request['text'], $source));
    }

    public function test_removed_student_or_changed_teacher_destination_cannot_receive_stale_alerts(): void
    {
        $run = $this->execution(); $this->report($run);
        DB::table('course_students')->where('course_id', 1)->where('user_id', 1)->delete();
        app(ExecutionAlerts::class)->plagiarism($run);
        $this->assertDatabaseCount('code_execution_alerts', 0);
        $run->update(['state' => 'error', 'finished_at' => now()]);
        app(ExecutionAlerts::class)->technicalError($run);
        DB::table('users')->where('id', 2)->update(['telegram_chat_id' => '9999']);
        $this->sendAll(); Http::assertNothingSent();
        $this->assertDatabaseHas('code_execution_alerts', ['status' => 'cancelled']);
    }

    public function test_sending_revalidates_course_membership_and_bot_identity(): void
    {
        $run = $this->execution(1, 'error');
        app(ExecutionAlerts::class)->technicalError($run);
        DB::table('course_teachers')->where('course_id', 1)->where('user_id', 2)->delete();
        $this->sendAll(); Http::assertNothingSent();
        $this->assertDatabaseHas('code_execution_alerts', ['status' => 'cancelled']);
    }

    public function test_definitive_rate_limit_retries_but_unknown_transport_result_never_duplicates(): void
    {
        $run = $this->execution(1, 'error');
        app(ExecutionAlerts::class)->technicalError($run);
        $sequence = Http::sequence()
            ->push(['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 30]], 429)
            ->push(['ok' => true, 'result' => ['message_id' => 18]]);
        $this->telegramResponse = fn ($request) => $sequence($request);
        $this->sendAll();
        $this->assertDatabaseHas('code_execution_alerts', ['status' => 'retry']);
        $this->assertSame(0, app(ExecutionAlerts::class)->dispatchDue());
        Carbon::setTestNow(now()->addSeconds(31));
        $this->assertSame(1, app(ExecutionAlerts::class)->dispatchDue());
        $this->sendAll(); Http::assertSentCount(2);
        $this->assertDatabaseHas('code_execution_alerts', ['status' => 'sent', 'attempts' => 2]);

        $other = $this->execution(1, 'error');
        app(ExecutionAlerts::class)->technicalError($other);
        $this->telegramResponse = fn () => throw new \Illuminate\Http\Client\ConnectionException('UNTRUSTED SECRET URL');
        $this->sendAll();
        $this->assertDatabaseHas('code_execution_alerts', ['execution_run_id' => $other->id, 'status' => 'uncertain', 'last_error' => 'transport_result_unknown']);
        Carbon::setTestNow(now()->addHour());
        $this->assertSame(0, app(ExecutionAlerts::class)->dispatchDue());
    }

    public function test_recovery_requeues_lost_queue_jobs_but_never_replays_a_crashed_sender(): void
    {
        $run = $this->execution(1, 'error'); app(ExecutionAlerts::class)->technicalError($run);
        DB::table('code_execution_alerts')->update(['reserved_at' => now()->utc()->subMinutes(11)]);
        Queue::fake();
        $this->artisan('code-checking:notify')->assertSuccessful();
        Queue::assertPushed(SendCodeExecutionAlert::class, 1);
        DB::table('code_execution_alerts')->update(['status' => 'sending', 'reserved_at' => now()->utc()->subMinutes(11)]);
        Queue::fake();
        $this->artisan('code-checking:notify')->assertSuccessful();
        Queue::assertNotPushed(SendCodeExecutionAlert::class);
        $this->assertDatabaseHas('code_execution_alerts', ['status' => 'uncertain', 'last_error' => 'worker_interrupted']);
    }

    public function test_exhausted_dispatch_notifies_once_and_cannot_overwrite_a_completed_callback(): void
    {
        $run = $this->execution();
        (new DispatchCodeExecution($run->id))->failed(new \RuntimeException('SECRET PROVIDER CREDENTIAL'));
        (new DispatchCodeExecution($run->id))->failed(new \RuntimeException('SECOND CALL'));
        $this->assertDatabaseCount('code_execution_alerts', 1);
        $this->sendAll(); Http::assertSentCount(1);
        Http::assertSent(fn ($request) => !str_contains($request['text'], 'SECRET'));

        $finished = $this->execution(); $finished->update(['state' => 'completed', 'finished_at' => now()]);
        (new DispatchCodeExecution($finished->id))->failed(new \RuntimeException('late worker failure'));
        $this->assertSame('completed', $finished->fresh()->state);
        $this->assertDatabaseCount('code_execution_alerts', 1);
    }

    public function test_technical_alert_is_cancelled_when_a_new_run_has_replaced_the_failure(): void
    {
        $run = $this->execution(1, 'error'); app(ExecutionAlerts::class)->technicalError($run);
        $replacement = CodeExecutionRun::create(['id' => (string) Str::uuid(), 'submission_id' => $run->submission_id,
            'snapshot' => $run->snapshot, 'state' => 'completed', 'finished_at' => now()]);
        $run->submission->update(['current_run_id' => $replacement->id]);
        $this->sendAll(); Http::assertNothingSent();
        $this->assertDatabaseHas('code_execution_alerts', ['execution_run_id' => $run->id, 'status' => 'cancelled']);
    }

    public function test_recheck_during_ai_analysis_does_not_lose_critical_plagiarism_on_immutable_source(): void
    {
        $reference = $this->execution(3); $checked = $this->execution(1);
        $source = str_repeat('total = total + value * 17; output(total); ', 30);
        $artifact = app(Artifacts::class)->put($source);
        foreach ([$reference, $checked] as $run) $run->submission->update(['files' => [['name' => 'main.py', 'artifact' => $artifact]]]);
        app(Integrity::class)->check($reference->submission->fresh());
        config(['code_checking.ai_detection_enabled' => true]);
        $this->mock(\App\Services\ChatGptService::class)->shouldReceive('generate')->once()->andReturnUsing(function () use ($checked) {
            $replacement = CodeExecutionRun::create(['id' => (string) Str::uuid(), 'submission_id' => $checked->submission_id,
                'snapshot' => $checked->snapshot, 'state' => 'completed', 'finished_at' => now()]);
            $checked->submission->update(['current_run_id' => $replacement->id]);
            return '{"warning":false,"confidence":"low","reasons":[]}';
        });
        app(Integrity::class)->check($checked->submission->fresh());
        $this->assertDatabaseHas('code_execution_alerts', ['execution_run_id' => $checked->id, 'kind' => 'plagiarism']);
        $this->sendAll(); Http::assertSentCount(1);
    }
}
