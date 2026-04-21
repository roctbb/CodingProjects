<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewMark extends Notification implements ShouldQueue
{
    use Queueable;

    private $solution;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($solution)
    {
        $this->solution = $solution;
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
        return (new MailMessage)->greeting('Добрый день!')->subject('Решение проверено')
            ->line($this->solution->teacher->name . " проверил ваше решение для задачи
                     " . $this->solution->task->name . " (курс " . $this->solution->course->name . ").")
            ->line('Очков опыта: ' . $this->solution->mark . " / " . $this->solution->task->max_mark)
            ->line('Комментарий: ' . $this->solution->comment)
            ->action('Подробнее', url("/insider/courses/" . $this->solution->course_id . "/steps/" . $this->solution->task->step->id . "#task" . $this->solution->task->id));
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
