<?php

namespace App\Notifications\Channels;

use App\Services\TelegramBot;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;

class TelegramBotChannel
{
    public function send($notifiable, Notification $notification)
    {
        $chatId = $this->resolveChatId($notifiable);

        if (!$chatId) {
            return;
        }

        $text = $this->resolveText($notifiable, $notification);
        if (!$text) {
            return;
        }

        app(TelegramBot::class)->sendMessage($chatId, $text, 'HTML');
    }

    private function resolveChatId($notifiable)
    {
        if (!empty($notifiable->telegram_chat_id)) {
            return $notifiable->telegram_chat_id;
        }

        return null;
    }

    private function resolveText($notifiable, Notification $notification)
    {
        if (method_exists($notification, 'toTelegram')) {
            return $notification->toTelegram($notifiable);
        }

        if (!method_exists($notification, 'toArray')) {
            return null;
        }

        return Arr::get($notification->toArray($notifiable), 'text');
    }
}
