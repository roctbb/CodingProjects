<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramBot
{
    public function __construct(private TelegramSettings $settings)
    {
    }

    public function getUpdates($offset = null, $timeout = 0, ?array $settings = null): array
    {
        $payload = [
            'timeout' => $timeout,
            'allowed_updates' => ['message', 'edited_message', 'my_chat_member'],
        ];
        if ($offset !== null) {
            $payload['offset'] = $offset;
        }
        $data = $this->request('getUpdates', $payload, $settings ?? $this->settings->all());

        return is_array($data['result'] ?? null) ? $data['result'] : [];
    }

    public function sendMessage($chatId, $text, $parseMode = null): bool
    {
        if (!$chatId || !$text) {
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

        return $this->request('sendMessage', $payload, $this->settings->all()) !== null;
    }

    private function request(string $method, array $payload, array $settings): ?array
    {
        $data = $this->requestResult($method, $payload, $settings);
        return ($data['ok'] ?? false) ? $data : null;
    }

    public function deliver(string $chatId, string $text, int $threadId = 0): array
    {
        $payload = ['chat_id' => $chatId, 'text' => $text, 'disable_web_page_preview' => true];
        if ($threadId) $payload['message_thread_id'] = $threadId;
        return $this->requestResult('sendMessage', $payload, $this->settings->all());
    }

    private function requestResult(string $method, array $payload, array $settings): array
    {
        if (empty($settings['bot_token'])) {
            return ['ok' => false, 'error_code' => 401];
        }
        try {
            $response = Http::asJson()->acceptJson()->withoutRedirecting()
                // Explicit empty proxy also disables environment-level proxies.
                ->withOptions(['proxy' => $settings['proxy'] ?? ''])
                ->timeout(max((int) $settings['timeout'], !empty($payload['timeout']) ? (int) $payload['timeout'] + 5 : 1))
                ->post(rtrim($settings['api_url'], '/') . '/bot' . $settings['bot_token'] . '/' . $method, $payload);
            $data = $response->json();
            if ($response->successful() && is_array($data) && ($data['ok'] ?? false) === true) {
                return $data;
            }
            Log::warning('Telegram request failed', [
                'method' => $method,
                'status' => $response->status(),
                'error_code' => is_int($data['error_code'] ?? null) ? $data['error_code'] : null,
            ]);
            return ['ok' => false, 'error_code' => (int) ($data['error_code'] ?? $response->status()),
                'retry_after' => (int) ($data['parameters']['retry_after'] ?? 0),
                'migrate_to_chat_id' => $data['parameters']['migrate_to_chat_id'] ?? null];
        } catch (Throwable $exception) {
            // URLs and exception messages may contain the bot token or proxy password.
            Log::warning('Telegram request failed', ['method' => $method, 'exception' => get_class($exception)]);
            return ['ok' => false, 'uncertain' => true];
        }
    }
}
