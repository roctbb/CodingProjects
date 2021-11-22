<?php

namespace App\Notifications;

use App\Channels\VkChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewForumAnswer extends Notification implements ShouldQueue
{
    use Queueable;

    private $post;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($post)
    {
        $this->post = $post;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', VkChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)->greeting('Добрый день!')->subject('Новый ответ на форуме')
            ->line("Новый ответ по теме '".$this->post->thread->name."'.")
            ->action('Подробнее', url("/insider/forum/".$this->post->thread_id));
    }

    public function toVk($notifiable)
    {
        return "💬 По теме \"".$this->post->thread->name."\" добавлен новый ответ.\n\n🔗 Подробнее: ".url("/insider/forum/".$this->post->thread_id);
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
            //
        ];
    }
}
