<?php

namespace App\Notifications;

use App\Notifications\Channels\TelegramBotChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewOrder extends Notification implements ShouldQueue
{
    use Queueable;

    private $deal;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($deal)
    {
        $this->deal = $deal;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', TelegramBotChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)->greeting('Добрый день!')->subject('Новая покупка')
                    ->line($this->deal->User->name." заказал 
                     ".$this->deal->good->name.".");
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
            //
        ];
    }

    public function toTelegram($notifiable)
    {
        return '🛒 Новая покупка: <strong>' . e($this->deal->user->name) . '</strong> заказал(а) <strong>"' .
            e($this->deal->good->name) . '"</strong> за <strong>' . e($this->deal->displayPrice()) . ' GC</strong>.';
    }
}
