<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewExtremeFeedback extends Notification
{
    use Queueable;
    protected $feedback;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($feedback)
    {
        $this->feedback = $feedback;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)->greeting('Добрый день!')->subject('Кажется, что-то идет не так...')
            ->line('Участник ' . $this->feedback->user->name . ' оценил сегодняшние занятия на ' . $this->feedback->mark . ' / 5.')
            ->action('Посмотреть профиль', url('insider/profile/' . $this->feedback->user->id));
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
