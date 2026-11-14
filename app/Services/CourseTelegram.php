<?php
namespace App\Services;

use App\Course;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CourseTelegram
{
    public function botKey(): string
    {
        $settings = app(TelegramSettings::class)->all();
        return hash('sha256', rtrim($settings['api_url'], '/').':'.explode(':', $settings['bot_token'] ?? '', 2)[0]);
    }

    public function isTeacher(Course $course, User $user): bool
    {
        return $user->role === 'admin' || $course->teachers()->where('users.id', $user->id)->exists();
    }

    public function link(Course $course, User $user): array
    {
        abort_unless($this->isTeacher($course, $user), 403);
        abort_unless((int) $user->telegram_chat_id > 0, 422, 'Сначала подключите личный Telegram в настройках профиля.');
        $bot = app(TelegramSettings::class)->botUsername();
        abort_unless($bot && filled(app(TelegramSettings::class)->all()['bot_token']), 422, 'Бот ещё не настроен администратором.');
        $token = Str::random(40);
        DB::transaction(function () use ($course, $user, $token) {
            DB::table('course_telegram_tokens')->where('course_id', $course->id)->where('user_id', $user->id)->whereNull('consumed_at')->delete();
            DB::table('course_telegram_tokens')->insert(['course_id' => $course->id, 'user_id' => $user->id,
                'token_hash' => hash('sha256', $token), 'bot_key' => $this->botKey(), 'expires_at' => now()->addMinutes(30),
                'created_at' => now(), 'updated_at' => now()]);
        });
        return ['url' => 'https://t.me/'.$bot.'?startgroup=course_'.$token, 'command' => '/start@'.$bot.' course_'.$token];
    }

    public function handle(array $update): bool
    {
        if (!Schema::hasTable('course_telegram_chats')) return false;
        if ($membership = ($update['my_chat_member'] ?? null)) {
            if (in_array($membership['new_chat_member']['status'] ?? '', ['left', 'kicked'], true)) {
                DB::table('course_telegram_chats')->where('bot_key', $this->botKey())->where('chat_id', (string) ($membership['chat']['id'] ?? ''))
                    ->update(['status' => 'disabled', 'updated_at' => now()]);
            }
            return true;
        }
        $m = $update['message'] ?? [];
        if (!in_array($m['chat']['type'] ?? '', ['group', 'supergroup'], true) ||
            !preg_match('/^\/start(?:@([A-Za-z0-9_]+))?\s+course_([A-Za-z0-9]{40})$/', trim($m['text'] ?? ''), $match)) return false;
        if (!empty($match[1]) && strcasecmp($match[1], app(TelegramSettings::class)->botUsername()) !== 0) return false;
        // No group message is sent during binding. The linked teacher confirms the destination on the website.
        DB::transaction(function () use ($m, $match) {
            $token = DB::table('course_telegram_tokens')->where('token_hash', hash('sha256', $match[2]))->where('bot_key', $this->botKey())
                ->whereNull('consumed_at')->where('expires_at', '>', now())->lockForUpdate()->first();
            if (!$token || empty($m['from']['id']) || !empty($m['from']['is_bot'])) return;
            $user = User::find($token->user_id); $course = Course::find($token->course_id);
            $identity = $user?->telegram_user_id ?: $user?->telegram_chat_id;
            if (!$course || !$user || (int) $identity <= 0 || (string) $identity !== (string) $m['from']['id'] || !$this->isTeacher($course, $user)) return;
            DB::table('course_telegram_chats')->updateOrInsert(['course_id' => $course->id, 'bot_key' => $this->botKey(),
                'chat_id' => (string) $m['chat']['id'], 'thread_id' => (int) ($m['message_thread_id'] ?? 0)],
                ['title' => mb_substr($m['chat']['title'] ?? 'Чат курса', 0, 255), 'status' => 'pending', 'linked_by' => $user->id,
                    'confirmed_at' => null, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('course_telegram_tokens')->where('id', $token->id)->update(['consumed_at' => now()]);
        });
        return true;
    }
}
