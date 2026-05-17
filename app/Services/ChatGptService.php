<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class ChatGptService
{
    private const IMAGE_MIME_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    public function generate(array $messages, array $options = [])
    {
        $token = config('services.chatgpt.key');
        $gateway = config('services.chatgpt.gateway');
        $model = $options['model'] ?? config('services.chatgpt.model');
        $timeout = $options['timeout'] ?? 90;

        if (!$token || !$gateway || !$model) {
            throw new \RuntimeException('ChatGPT gateway credentials are not configured');
        }

        try {
            $response = (new Client())->post($gateway, [
                'json' => [
                    'token' => $token,
                    'model' => $model,
                    'input' => $this->normalizeMessages($messages),
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

    public function generateImage(array $messages, array $options = []): array
    {
        $token = config('services.chatgpt.key');
        $gateway = config('services.chatgpt.gateway');
        $model = $options['model'] ?? config('services.chatgpt.image_model') ?? config('services.chatgpt.model');
        $timeout = $options['timeout'] ?? 180;

        if (!$token || !$gateway || !$model) {
            throw new \RuntimeException('ChatGPT gateway credentials are not configured');
        }

        $imageTool = ['type' => 'image_generation'];
        foreach (['size', 'quality', 'output_format', 'background'] as $optionKey) {
            if (!empty($options[$optionKey])) {
                $imageTool[$optionKey] = $options[$optionKey];
            }
        }

        $requestPayload = [
            'token' => $token,
            'model' => $model,
            'input' => $this->normalizeMessages($messages),
            'params' => [
                'tools' => [$imageTool],
            ],
        ];

        if (!empty($options['tool_choice'])) {
            $requestPayload['params']['tool_choice'] = $options['tool_choice'];
        }

        try {
            $client = new Client();
            $response = $client->post($gateway, [
                'json' => $requestPayload,
                'timeout' => $timeout,
            ]);
        } catch (RequestException $e) {
            Log::error('ChatGPT gateway image request failed', [
                'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : null,
                'response_body' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
                'message' => $e->getMessage(),
            ]);

            throw new \RuntimeException('ChatGPT gateway image request failed: ' . $e->getMessage(), 0, $e);
        }

        $data = json_decode($response->getBody()->getContents(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response from ChatGPT gateway: ' . json_last_error_msg());
        }

        if (!empty($data['error'])) {
            throw new \RuntimeException('ChatGPT gateway error: ' . $data['error']);
        }

        $image = self::imageFromResponsePayload($data);
        if (!empty($image['url']) && empty($image['bytes'])) {
            $image = $this->downloadImage($image['url'], $timeout);
        }

        if (empty($image['bytes']) || !is_string($image['bytes'])) {
            Log::error('ChatGPT gateway invalid image response format', ['response_data' => $data]);
            throw new \RuntimeException('ChatGPT gateway returned no image bytes');
        }

        $mime = $this->detectImageMime($image['bytes'], $image['mime'] ?? null);
        if (!isset(self::IMAGE_MIME_EXTENSIONS[$mime])) {
            throw new \RuntimeException('ChatGPT gateway returned unsupported image type: ' . $mime);
        }

        return [
            'bytes' => $image['bytes'],
            'mime' => $mime,
            'extension' => self::IMAGE_MIME_EXTENSIONS[$mime],
            'model' => $model,
        ];
    }

    public static function imageFromResponsePayload(array $data): array
    {
        $found = self::findImagePayload($data);
        if (!$found) {
            return [];
        }

        if (!empty($found['url'])) {
            return ['url' => $found['url'], 'mime' => $found['mime'] ?? null];
        }

        $base64 = $found['base64'] ?? null;
        if (!is_string($base64) || trim($base64) === '') {
            return [];
        }

        $mime = $found['mime'] ?? null;
        if (preg_match('/^data:(image\/[a-z0-9.+-]+);base64,(.+)$/is', trim($base64), $matches)) {
            $mime = $matches[1];
            $base64 = $matches[2];
        }

        $bytes = base64_decode(preg_replace('/\s+/', '', $base64), true);
        if ($bytes === false || $bytes === '') {
            return [];
        }

        return [
            'bytes' => $bytes,
            'mime' => $mime,
        ];
    }

    private function normalizeMessages(array $messages): array
    {
        return collect($messages)->map(function ($message) {
            return [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        })->values()->all();
    }

    private static function findImagePayload($value): ?array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if (preg_match('/^data:image\/[a-z0-9.+-]+;base64,/i', $trimmed)) {
                return ['base64' => $trimmed];
            }

            return null;
        }

        if (!is_array($value)) {
            return null;
        }

        foreach (['b64_json', 'base64', 'image_base64', 'result'] as $key) {
            if (isset($value[$key]) && is_string($value[$key])) {
                return [
                    'base64' => $value[$key],
                    'mime' => $value['mime'] ?? $value['mime_type'] ?? null,
                ];
            }
        }

        foreach (['url', 'image_url'] as $key) {
            if (isset($value[$key]) && is_string($value[$key]) && preg_match('/^https?:\/\//i', $value[$key])) {
                return [
                    'url' => $value[$key],
                    'mime' => $value['mime'] ?? $value['mime_type'] ?? null,
                ];
            }
        }

        foreach ($value as $child) {
            $found = self::findImagePayload($child);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    private function downloadImage(string $url, int $timeout): array
    {
        try {
            $response = (new Client())->get($url, [
                'timeout' => $timeout,
                'http_errors' => false,
            ]);
        } catch (RequestException $e) {
            throw new \RuntimeException('ChatGPT gateway image download failed: ' . $e->getMessage(), 0, $e);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException('ChatGPT gateway image download failed with status ' . $response->getStatusCode());
        }

        return [
            'bytes' => (string) $response->getBody(),
            'mime' => $response->getHeaderLine('Content-Type') ?: null,
        ];
    }

    private function detectImageMime(string $bytes, ?string $preferredMime = null): string
    {
        $preferredMime = $preferredMime ? strtolower(trim(explode(';', $preferredMime)[0])) : null;
        if ($preferredMime && isset(self::IMAGE_MIME_EXTENSIONS[$preferredMime])) {
            return $preferredMime;
        }

        $detected = function_exists('finfo_buffer')
            ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes)
            : null;
        if (is_string($detected) && isset(self::IMAGE_MIME_EXTENSIONS[$detected])) {
            return $detected;
        }

        if (strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) === 0) {
            return 'image/png';
        }

        if (strncmp($bytes, "\xff\xd8\xff", 3) === 0) {
            return 'image/jpeg';
        }

        if (strncmp($bytes, 'RIFF', 4) === 0 && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return $detected ?: 'application/octet-stream';
    }
}
