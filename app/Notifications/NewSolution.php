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
        $isRecheck = $this->isRecheckRequest();
        $comment = $this->recheckComment();
        $message = (new MailMessage)
            ->greeting('Добрый день!')
            ->subject($isRecheck ? 'Запрос на перепроверку' : 'Новое решение')
            ->line($isRecheck
                ? $this->solution->User->name . ' просит перепроверить решение задачи "' . $this->solution->task->name . '" в курсе "' . $this->solution->course->name . '".'
                : $this->solution->User->name . " загрузил новое решение для задачи
                     " . $this->solution->task->name . " (курс " . $this->solution->course->name . ").");

        if ($isRecheck) {
            $message->line('С чем ученик не согласен: ' . $comment);
        }

        return $message->action($isRecheck ? 'Открыть перепроверку' : 'Оценить', $this->solutionUrl());
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
        $url = $this->solutionUrl();

        if ($this->isRecheckRequest()) {
            return '🔁 Запрос на перепроверку: <strong>' . e($this->solution->user->name) . '</strong> просит пересмотреть решение задачи <strong>"' .
                e($this->solution->task->name) . '"</strong> в курсе <strong>"' . e($this->solution->course->name) . '"</strong>.' . "\n" .
                'С чем ученик не согласен: ' . e($this->recheckComment()) . "\n" .
                '<a href="' . e($url) . '">Открыть перепроверку</a>';
        }

        return '📝 Новое решение: <strong>' . e($this->solution->user->name) . '</strong> загрузил(а) решение задачи <strong>"' .
            e($this->solution->task->name) . '"</strong> в курсе <strong>"' . e($this->solution->course->name) . '"</strong>.' . "\n" .
            '<a href="' . e($url) . '">Открыть решение</a>';
    }

    private function isRecheckRequest()
    {
        return (bool) $this->solution->recheck_requested;
    }

    private function recheckComment()
    {
        $comment = trim((string) $this->solution->recheck_comment);

        return $comment === '' ? 'Комментарий не указан.' : $comment;
    }

    private function solutionUrl()
    {
        return url("/insider/courses/" . $this->solution->course_id . "/tasks/" . $this->solution->task->id . "/student/" . $this->solution->user->id);
    }
}
