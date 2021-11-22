<?php

namespace App\Notifications;

use App\Channels\VkChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class IdeaApproved extends Notification implements ShouldQueue
{
    use Queueable;

    private $idea;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($idea)
    {
        $this->idea = $idea;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', VkChannel::class, 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)->greeting('Добрый день!')->subject('Идея проекта одобрена')
                    ->line("Ваша идея для проекта \"".$this->idea->name."\" одобрена. Поздравляем!")->action('Подробнее', url("/insider/ideas/".$this->idea->id));
    }

    public function toVk($notifiable)
    {
        return "💡 Идея для проекта \"".$this->idea->name."\" одобрена. Поздравляем!\n\n🔗 Подробнее: ".url("/insider/ideas/".$this->idea->id);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'text' => "💡 Идея для проекта <a href='".url("/insider/ideas/".$this->idea->id)."'>\"".$this->idea->name."\"</a> одобрена. Поздравляем!",
            'type' => 'success'
        ];
    }
}
