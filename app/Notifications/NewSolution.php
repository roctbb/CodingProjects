<?php

namespace App\Notifications;

use App\Notifications\Channels\TelegramBotChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewSolution extends Notification implements ShouldQueue
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
        return ['mail', TelegramBotChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)->greeting('Добрый день!')->subject('Новое решение')
            ->line($this->solution->User->name . " загрузил новое решение для задачи
                     " . $this->solution->task->name . " (курс " . $this->solution->course->name . ").")
            ->action('Оценить', url("/insider/courses/" . $this->solution->course_id . "/tasks/" . $this->solution->task->id . "/student/" . $this->solution->User->id));
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

    public function toTelegram($notifiable)
    {
        $url = url("/insider/courses/" . $this->solution->course_id . "/tasks/" . $this->solution->task->id . "/student/" . $this->solution->user->id);

        return '📝 Новое решение: <strong>' . e($this->solution->user->name) . '</strong> загрузил(а) решение задачи <strong>"' .
            e($this->solution->task->name) . '"</strong> в курсе <strong>"' . e($this->solution->course->name) . '"</strong>.' . "\n" .
            '<a href="' . e($url) . '">Открыть решение</a>';
    }
}
