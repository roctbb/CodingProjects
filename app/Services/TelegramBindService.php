<?php

namespace App\Services;

use App\User;
use Carbon\Carbon;

class TelegramBindService
{
    public function handleUpdate(array $update)
    {
        if (app(CourseTelegram::class)->handle($update)) return true;
        $message = $update['message'] ?? $update['edited_message'] ?? null;
        $text = trim($message['text'] ?? '');
        $chat = $message['chat'] ?? null;

        if (!$chat || ($chat['type'] ?? 'private') !== 'private' || (int) ($chat['id'] ?? 0) <= 0 || !preg_match('/^\/start\s+bind_([A-Za-z0-9]+)$/', $text, $matches)) {
            return false;
        }

        $this->bind($matches[1], $chat, $message['from'] ?? []);

        return true;
    }

    public function bind($token, array $chat, array $from = [])
    {
        if (($chat['type'] ?? 'private') !== 'private' || (int) ($chat['id'] ?? 0) <= 0) return null;
        $telegram = app(TelegramBot::class);
        $chatId = $chat['id'] ?? null;

        $user = User::where('telegram_link_token', $token)
            ->where('telegram_link_token_expires_at', '>', Carbon::now())
            ->first();

        if (!$user) {
            $telegram->sendMessage($chatId, 'Ссылка для привязки устарела. Откройте профиль на сайте и нажмите «Подключить Telegram» ещё раз.');
            return null;
        }

        $user->telegram_chat_id = (string) $chatId;
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'telegram_user_id')) $user->telegram_user_id = (string) ($from['id'] ?? $chatId);
        $user->telegram_link_token = null;
        $user->telegram_link_token_expires_at = null;

        if (!$user->telegram && !empty($from['username'])) {
            $user->telegram = '@' . $from['username'];
        }

        $user->save();

        $telegram->sendMessage($chatId, 'Готово! Telegram подключён к профилю '.config('app.name').'. Уведомления будут приходить сюда. Напоминания о сроках можно включить на странице курса.');

        return $user;
    }
}
