<?php

namespace App\Notifications;

use App\Channels\VkChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class Feedback extends Notification implements ShouldQueue
{
    use Queueable;
    protected $key;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($key)
    {
        $this->key = $key;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', VkChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)->greeting('Добрый день!')->subject('Как прошли занятия?')
            ->line("Пожалуйста, оцените, как прошли занятия в Гекконе сегодня. Буквально пара кликов и бонусный геккоин ваш! :)")->action('Оценить', url("/feedback/".$this->key));
    }

    public function toVk($notifiable)
    {
        return "👀 Как прошли занятия? Буквально пара кликов и бонусный геккоин ваш!\n\n🔗 Оценить: ".url("/feedback/".$this->key);
    }

}
