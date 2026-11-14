<?php
namespace App\Services;

use App\Course;
use App\User;
use Illuminate\Support\Facades\DB;

class DeadlineNotificationSender
{
    public function send(int $id): void
    {
        $claimed = DB::table('deadline_notification_deliveries')->where('id', $id)->where('status', 'queued')
            ->update(['status' => 'sending', 'reserved_at' => now()->utc(), 'attempts' => DB::raw('attempts + 1')]);
        if (!$claimed) return;
        $d = DB::table('deadline_notification_deliveries')->find($id);
        $course = Course::find($d->course_id);
        $events = DB::table('deadline_notification_events')->whereIn('id', json_decode($d->event_ids, true))->get();
        $settings = DB::table('course_notification_settings')->where('course_id', $d->course_id)->first();
        $valid = $course && $course->state !== 'draft' && hash_equals($d->bot_key, app(CourseTelegram::class)->botKey());
        $viewer = null;
        if ($d->recipient_type === 'chat') {
            $chat = DB::table('course_telegram_chats')->where('id', $d->recipient_id)->where('status', 'active')->where('bot_key', $d->bot_key)->first();
            $valid = $valid && ($settings?->chat_enabled ?? true) && $chat && $chat->chat_id === $d->chat_id && (int) $chat->thread_id === (int) $d->thread_id;
        } else {
            $viewer = User::find($d->recipient_id);
            $valid = $valid && $viewer && (string) $viewer->telegram_chat_id === $d->chat_id;
            if ($d->recipient_type === 'teacher') $valid = $valid && ($settings?->teachers_enabled ?? true) && $course->teachers()->where('users.id', $d->recipient_id)->exists();
            else $valid = $valid && $course->students()->where('users.id', $d->recipient_id)->exists() && DB::table('course_notification_preferences')->where('course_id', $course->id)->where('user_id', $d->recipient_id)->where('enabled', true)->exists();
        }
        app(CourseDeadlineService::class)->forget($d->course_id);
        $tasks = collect(); $activeEvents = collect();
        if ($valid) foreach ($events as $event) {
            $visible = app(DeadlineNotifications::class)->tasks($event, $d->recipient_type === 'student' ? $viewer : null, $d->recipient_type === 'chat');
            if ($visible->isNotEmpty()) { $activeEvents->push($event); $tasks = $tasks->concat($visible); }
        }
        if (!$valid || $tasks->isEmpty()) { $this->finish($id, 'cancelled'); return; }
        $tasks = $tasks->unique('id'); $event = $activeEvents->first();
        $stage = DB::table('deadline_stages')->find($event->stage_id);
        if (!$stage) { $this->finish($id, 'cancelled'); return; }
        $zone = DB::table('deadline_policies')->where('id', $event->policy_id)->value('timezone');
        $rule = \App\Support\DeadlineRule::fromSnapshot(['timezone' => $zone,
            'stages' => [['at' => $stage->cutoff_at, 'percent' => $stage->retained_percent]]]);
        $date = $rule->deadlineAt()->format('d.m.Y H:i');
        $minutes = max(1, (int) ceil(now()->diffInSeconds($rule->cutoffAt(), false) / 60));
        $remaining = $minutes >= 60 ? intdiv($minutes, 60).' ч '.($minutes % 60).' мин' : $minutes.' мин';
        $text = ($event->kind === 'reminder' ? '⏳ До срока сдачи: '.$remaining : '🏁 Наступил срок сдачи')."\n".
            mb_substr($course->name, 0, 200)."\n".mb_substr($tasks->first()->step->lesson->name, 0, 200)."\n".
            'До '.$date.' ('.$zone.'); после срока — '.($stage->retained_percent + 0)."% баллов\n";
        foreach ($tasks->take(5) as $task) $text .= '• '.mb_substr($task->name, 0, 150)."\n";
        if ($tasks->count() > 5) $text .= 'И ещё '.($tasks->count() - 5)." задач\n";
        $text .= "Сдача остаётся открытой. Уже заработанные баллы сохраняются.\n".url('/insider/courses/'.$course->id.'/deadlines');
        $result = app(TelegramBot::class)->deliver($d->chat_id, $text, (int) $d->thread_id);
        if ($result['ok'] ?? false) {
            $this->finish($id, 'sent', ['message_id' => (string) ($result['result']['message_id'] ?? '')]); return;
        }
        if (!empty($result['uncertain'])) { $this->finish($id, 'uncertain', ['last_error' => 'transport_result_unknown']); return; }
        $code = (int) ($result['error_code'] ?? 500);
        if (!empty($result['migrate_to_chat_id']) && $d->recipient_type === 'chat') {
            $chatId = (string) $result['migrate_to_chat_id'];
            DB::table('course_telegram_chats')->where('id', $d->recipient_id)->update(['chat_id' => $chatId, 'updated_at' => now()]);
            DB::table('deadline_notification_deliveries')->where('id', $id)->update(['chat_id' => $chatId]);
            $code = 429;
        }
        if ($code === 403 && $d->recipient_type === 'chat') DB::table('course_telegram_chats')->where('id', $d->recipient_id)->update(['status' => 'disabled', 'updated_at' => now()]);
        if (($code === 429 || $code >= 500) && $d->attempts < 8) {
            $seconds = max(5, (int) ($result['retry_after'] ?? 0), min(3600, 15 * 2 ** ($d->attempts - 1)));
            $this->finish($id, 'retry', ['last_error' => 'telegram_'.$code, 'available_at' => now()->utc()->addSeconds($seconds)]);
        } else $this->finish($id, 'failed', ['last_error' => 'telegram_'.$code]);
    }

    private function finish(int $id, string $status, array $values = []): void
    {
        DB::table('deadline_notification_deliveries')->where('id', $id)->update($values + ['status' => $status, 'updated_at' => now()->utc()]);
    }
}
