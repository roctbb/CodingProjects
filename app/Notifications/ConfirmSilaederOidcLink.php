<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmSilaederOidcLink extends Notification
{
    use Queueable;

    public string $confirmationUrl;

    public function __construct(string $confirmationUrl)
    {
        $this->confirmationUrl = $confirmationUrl;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Подтверждение входа через ЛК Силаэдра')
            ->greeting('Здравствуйте!')
            ->line('В ЛК Силаэдра выполнен вход с email вашего аккаунта.')
            ->line('Подтвердите адрес, чтобы связать аккаунты и завершить вход.')
            ->action('Подтвердить и связать аккаунты', $this->confirmationUrl)
            ->line('Ссылка действует 30 минут. Если это были не вы, просто проигнорируйте письмо.');
    }
}
