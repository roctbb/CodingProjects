<?php

namespace App\Notifications;

use App\Notifications\Channels\TelegramBotChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AuctionEvent extends Notification
{
    use Queueable;

    private $text;
    private $type;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($text, $type = 'info')
    {
        $this->text = $text;
        $this->type = in_array($type, ['success', 'warning', 'danger', 'info']) ? $type : 'info';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', TelegramBotChannel::class];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'text' => $this->text,
            'type' => $this->type,
        ];
    }
}
