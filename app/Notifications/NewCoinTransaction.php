<?php

namespace App\Notifications;

use App\CoinTransaction;
use App\Notifications\Channels\TelegramBotChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewCoinTransaction extends Notification
{
    use Queueable;

    private $transaction;
    private $text;
    private $type;
    private $icon;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(CoinTransaction $transaction, $text = null, $type = 'success', $icon = null)
    {
        $this->transaction = $transaction;
        $this->text = $text;
        $this->type = $type;
        $this->icon = $icon;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return $this->transaction->price > 0 ? ['database', TelegramBotChannel::class] : [];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        $price = (int) $this->transaction->price;
    
        $comment = $this->transaction->comment ?? '';
    
        $comment = e(strip_tags($comment));
    
        $data = [
            "text" => $this->text ?: "🏧 Вам начислено {$price} GC ({$comment})",
            "type" => $this->type ?: "success"
        ];

        if ($this->icon) {
            $data['icon'] = $this->icon;
        }

        return $data;
    }
}
