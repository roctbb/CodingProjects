<?php

namespace App\Notifications;

use App\Channels\VkChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewRank extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [VkChannel::class, 'database'];
    }


    public function toVk($notifiable)
    {
        $message = "🌟 Вы получили новое звание - \"" . $notifiable->rank()->name . "\"! Поздравляем!";

        return $message;

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
            "text" => "🌟 Вы получили новое звание - <strong>\"" . $notifiable->rank()->name. "\"</strong>! Поздравляем!",
            "type" => "success"
        ];
    }
}
