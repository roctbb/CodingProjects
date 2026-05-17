<?php

namespace App\Notifications;

use App\Notifications\Channels\TelegramBotChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PetEvent extends Notification
{
    use Queueable;

    private $text;
    private $type;

    public function __construct($text, $type = 'success')
    {
        $this->text = $text;
        $this->type = in_array($type, ['success', 'warning', 'danger', 'info']) ? $type : 'info';
    }

    public function via($notifiable)
    {
        return ['database', TelegramBotChannel::class];
    }

    public function toArray($notifiable)
    {
        return [
            'text' => $this->text,
            'type' => $this->type,
        ];
    }
}
