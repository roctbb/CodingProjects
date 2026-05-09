<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramBot
{
    public function getUpdates($offset = null, $timeout = 0)
    {
        $token = config('services.telegram.bot_token');
        if (!$token) {
            return [];
        }

        $payload = [
            'timeout' => $timeout,
            'allowed_updates' => ['message', 'edited_message'],
        ];

        if ($offset !== null) {
            $payload['offset'] = $offset;
        }

        try {
            $response = $this->client()->post('/bot' . $token . '/getUpdates', [
                'json' => $payload,
            ]);

            $data = json_decode((string) $response->getBody(), true);
            if (($data['ok'] ?? false) !== true) {
                Log::warning('Telegram getUpdates failed', ['body' => (string) $response->getBody()]);
                return [];
            }

            return $data['result'] ?? [];
        } catch (Throwable $exception) {
            Log::warning('Telegram getUpdates failed', ['message' => $exception->getMessage()]);
            return [];
        }
    }

    public function sendMessage($chatId, $text, $parseMode = null)
    {
        $token = config('services.telegram.bot_token');
        if (!$token || !$chatId || !$text) {
            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];

        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }

        try {
            $response = $this->client()->post('/bot' . $token . '/sendMessage', [
                'json' => $payload,
            ]);

            if ($response->getStatusCode() < 400) {
                return true;
            }

            Log::warning('Telegram sendMessage failed', [
                'chat_id' => $chatId,
                'status' => $response->getStatusCode(),
                'body' => (string) $response->getBody(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Telegram sendMessage failed', [
                'chat_id' => $chatId,
                'message' => $exception->getMessage(),
            ]);
        }

        return false;
    }

    private function client()
    {
        $options = [
            'base_uri' => config('services.telegram.api_url', 'https://api.telegram.org'),
            'timeout' => config('services.telegram.timeout', 10),
            'http_errors' => false,
        ];

        if (config('services.telegram.proxy')) {
            $options['proxy'] = config('services.telegram.proxy');
        }

        return new Client($options);
    }
}
