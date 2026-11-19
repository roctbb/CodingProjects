<?php

namespace Tests\Feature;

use App\Services\TelegramBot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class GeekExamWifiAlertTest extends DeadlineFeatureCase
{
    private const PATH = '/api/geekexam/wifi-disconnect';
    private const SECRET = 'local-test-only-0123456789abcdef0123456789';
    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_11_19_190000_create_geekexam_wifi_alerts.php'))->up();
        config(['services.geekexam_alert_secret' => self::SECRET]);
        DB::table('users')->where('id', 2)->update(['telegram_chat_id' => '2222']);
        $this->payload = ['event_id' => (string) Str::uuid(), 'teacher_id' => 2, 'test_title' => 'Циклы',
            'student_name' => 'Тестовый ученик', 'room_name' => '409', 'disconnected_at' => '2026-11-19T07:05:01.123456Z'];
    }

    private function notify(array $changes = [], ?int $stamp = null, string $secret = self::SECRET)
    {
        $body = json_encode(array_replace($this->payload, $changes), JSON_UNESCAPED_UNICODE);
        $stamp ??= now()->timestamp;
        return $this->call('POST', self::PATH, [], [], [], ['CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json', 'HTTP_X_EXAM_TIME' => (string) $stamp,
            'HTTP_X_EXAM_SIGNATURE' => hash_hmac('sha256', "POST\n".self::PATH."\n".$stamp."\n".$body, $secret)], $body);
    }

    public function test_verified_event_reaches_only_the_owner_and_replay_is_idempotent(): void
    {
        $this->mock(TelegramBot::class)->shouldReceive('deliver')->once()->withArgs(function ($chat, $message) {
            return $chat === '2222' && str_contains($message, 'Тестовый ученик') && str_contains($message, 'Циклы')
                && str_contains($message, '409') && str_contains($message, '19.11.2026 10:05:01 МСК');
        })->andReturn(['ok' => true]);
        $this->notify()->assertOk()->assertJsonPath('status', 'sent');
        $this->notify()->assertOk()->assertJsonPath('status', 'sent');
        $this->notify(['teacher_id' => 3])->assertStatus(409);
        $this->assertDatabaseCount('geekexam_wifi_alerts', 1);
    }

    public function test_invalid_authentication_or_input_has_no_side_effects(): void
    {
        $this->mock(TelegramBot::class)->shouldNotReceive('deliver');
        $this->notify([], null, 'wrong-key')->assertUnauthorized();
        $this->notify([], now()->timestamp - 61)->assertUnauthorized();
        $this->notify(['event_id' => 'invalid'])->assertUnprocessable();
        $this->assertDatabaseCount('geekexam_wifi_alerts', 0);
    }

    public function test_missing_private_chat_or_student_role_does_not_send(): void
    {
        DB::table('users')->where('id', 1)->update(['telegram_chat_id' => '101']);
        DB::table('users')->where('id', 2)->update(['telegram_chat_id' => null]);
        $this->mock(TelegramBot::class)->shouldNotReceive('deliver');
        $this->notify(['teacher_id' => 1])->assertOk()->assertJsonPath('status', 'no_recipient');
        $this->notify(['event_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('status', 'no_recipient');
    }

    public function test_rate_limit_can_retry_and_ambiguous_delivery_is_never_duplicated(): void
    {
        $this->mock(TelegramBot::class)->shouldReceive('deliver')->twice()->andReturn(
            ['ok' => false, 'error_code' => 429, 'retry_after' => 37], ['ok' => false, 'uncertain' => true]);
        $this->notify()->assertStatus(503)->assertHeader('Retry-After', '37');
        $this->notify()->assertOk()->assertJsonPath('status', 'uncertain');
        $this->notify()->assertOk()->assertJsonPath('status', 'uncertain');
    }

    public function test_in_flight_send_stays_reserved_after_worker_crash(): void
    {
        $this->mock(TelegramBot::class)->shouldNotReceive('deliver');
        $this->notify(['teacher_id' => 1])->assertOk();
        $rows = DB::table('geekexam_wifi_alerts')->where('id', $this->payload['event_id']);
        $rows->update(['status' => 'sending']);
        $this->notify(['teacher_id' => 1])->assertStatus(503);
        $rows->update(['updated_at' => now()->subMinutes(6)]);
        $this->notify(['teacher_id' => 1])->assertOk()->assertJsonPath('status', 'uncertain');
    }
}
