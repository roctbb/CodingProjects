<?php

namespace App\Http\Controllers;

use App\Services\TelegramBot;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GeekExamWifiAlertController extends Controller
{
    public function store(Request $request, TelegramBot $bot): JsonResponse
    {
        $secret = (string) config('services.geekexam_alert_secret');
        abort_if(strlen($secret) < 32, 503);
        $stamp = (string) $request->header('X-Exam-Time');
        $body = $request->getContent();
        abort_if(strlen($body) > 8192, 413);
        abort_unless(strlen($stamp) === 10 && ctype_digit($stamp) && abs(now()->timestamp - (int) $stamp) <= 60, 401);
        $signature = hash_hmac('sha256', "POST\n/api/geekexam/wifi-disconnect\n".$stamp."\n".$body, $secret);
        abort_unless(hash_equals($signature, (string) $request->header('X-Exam-Signature')), 401);
        $data = validator($request->json()->all(), [
            'event_id' => 'required|uuid', 'teacher_id' => 'required|integer|min:1',
            'student_name' => 'required|string|max:255', 'test_title' => 'required|string|max:255',
            'room_name' => 'required|string|max:120', 'disconnected_at' => ['required', 'date', 'regex:/Z$/'],
        ])->validate();

        $id = $data['event_id'];
        $hash = hash('sha256', $body);
        $alerts = DB::table('geekexam_wifi_alerts');
        $alerts->insertOrIgnore(['id' => $id, 'payload_hash' => $hash, 'status' => 'ready', 'updated_at' => now()]);
        $alert = $alerts->where('id', $id);
        abort_unless($alert->value('payload_hash') === $hash, 409);
        // Reserve before contacting Telegram. An ambiguous send must never be replayed automatically.
        if (!(clone $alert)->where('status', 'ready')->update(['status' => 'sending', 'updated_at' => now()])) {
            (clone $alert)->where('status', 'sending')->where('updated_at', '<', now()->subMinutes(5))
                ->update(['status' => 'uncertain', 'updated_at' => now()]);
            $status = $alert->value('status');
            return in_array($status, ['sending', 'ready'], true)
                ? response()->json(['status' => 'retry'], 503, ['Retry-After' => '10'])
                : response()->json(['status' => $status]);
        }

        $chat = User::whereKey($data['teacher_id'])->whereIn('role', ['teacher', 'admin'])->value('telegram_chat_id');
        $wait = 0;
        if (!ctype_digit((string) $chat) || (int) $chat <= 0) {
            $status = 'no_recipient';
        } else {
            $label = static fn (string $value): string => trim(preg_replace('/[\p{C}\s]+/u', ' ', $value));
            $when = Carbon::parse($data['disconnected_at'])->setTimezone('Europe/Moscow')->format('d.m.Y H:i:s');
            $text = "GeekExam · Отключение от Wi-Fi\n".$label($data['student_name']).' · '.$label($data['test_title'])
                ."\nКабинет ".$label($data['room_name'])."\nGuard зафиксировал отключение устройства ".$when.' МСК.';
            $result = $bot->deliver((string) $chat, $text);
            if ($result['ok']) {
                $status = 'sent';
            } elseif (!empty($result['uncertain'])) {
                $status = 'uncertain';
            } elseif (in_array($result['error_code'] ?? null, [400, 403], true)) {
                $status = 'rejected';
            } else {
                $status = 'ready';
                $wait = max(5, (int) ($result['retry_after'] ?? 0));
            }
        }
        $alert->update(['status' => $status, 'updated_at' => now()]);
        return $status === 'ready'
            ? response()->json(['status' => 'retry'], 503, ['Retry-After' => (string) $wait])
            : response()->json(['status' => $status]);
    }
}
