<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class ChatGptService
{
    public function generate(array $messages, array $options = [])
    {
        $token = config('services.chatgpt.key');
        $gateway = config('services.chatgpt.gateway');
        $model = $options['model'] ?? config('services.chatgpt.model');
        $timeout = $options['timeout'] ?? 90;

        if (!$token || !$gateway || !$model) {
            throw new \RuntimeException('ChatGPT gateway credentials are not configured');
        }

        $input = collect($messages)->map(function ($message) {
            return [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        })->values()->all();

        try {
            $response = (new Client())->post($gateway, [
                'json' => [
                    'token' => $token,
                    'model' => $model,
                    'input' => $input,
                ],
                'timeout' => $timeout,
            ]);
        } catch (RequestException $e) {
            Log::error('ChatGPT gateway request failed', [
                'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : null,
                'response_body' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
                'message' => $e->getMessage(),
            ]);

            throw new \RuntimeException('ChatGPT gateway request failed: ' . $e->getMessage(), 0, $e);
        }

        $data = json_decode($response->getBody()->getContents(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response from ChatGPT gateway: ' . json_last_error_msg());
        }

        if (!empty($data['error'])) {
            throw new \RuntimeException('ChatGPT gateway error: ' . $data['error']);
        }

        $message = collect($data['result']['output'] ?? [])
            ->first(function ($item) {
                return ($item['type'] ?? null) === 'message';
            });

        $text = $message['content'][0]['text'] ?? null;

        if (!is_string($text) || trim($text) === '') {
            Log::error('ChatGPT gateway invalid response format', ['response_data' => $data]);
            throw new \RuntimeException('ChatGPT gateway returned no text');
        }

        return trim($text);
    }
}
