<?php

namespace App\Notifications;

use App\CoinTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

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
        if ($this->transaction->price > 0) {
            return ['database'];
        }
        return [];
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
    
        return [
            "text" => "🏧 Вам начислено {$price} GK ({$comment})",
            "type" => "success"
        ];
    }
}
