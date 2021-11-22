<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Log;

class VkChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        $message = $notification->toVk($notifiable);

        if ($notifiable->vk_id != null) {
            try {

                $client = new \GuzzleHttp\Client();
                $client->post(config('bot.vk_bot').'/notify', [
                    'form_params' => [
                        'message' => $message,
                        'class_id' => $notifiable->id,
                        'key' => config('bot.vk_bot_key')
                    ]
                ]);
            }
            catch (\Exception $e)
            {
                Log::info($e);
            }
        }


    }
}