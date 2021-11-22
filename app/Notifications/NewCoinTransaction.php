<?php

namespace App\Notifications;

use App\Channels\VkChannel;
use App\CoinTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewCoinTransaction extends Notification
{
    use Queueable;

    private $transaction;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(CoinTransaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $channels = [VkChannel::class];
        if ($this->transaction->price > 0) {
            array_push($channels, 'database');
        }
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toVk($notifiable)
    {
        if ($this->transaction->price > 0) {
            return "🏧 Вам начислено " . $this->transaction->price . " GK (" . $this->transaction->comment . ")";
        } else {
            return "🏧 Списание " . $this->transaction->price . " GK (" . $this->transaction->comment . ")";
        }
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
            "text" => "🏧 Вам начислено " . $this->transaction->price . " GK (" . $this->transaction->comment . ")",
            "type" => "success"
        ];

    }
}
