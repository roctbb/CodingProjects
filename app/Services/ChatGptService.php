<?php

namespace App\Services;

use App\Services\Ai\ApiClient;
use App\Services\Ai\ImagesClient;
use App\Services\Ai\AiSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ChatGptService
{
    private const IMAGE_MIME_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    public function __construct(private ApiClient $api, private AiSettings $aiSettings, private ImagesClient $images)
    {
    }

    public function generate(array $messages, array $options = []): string
    {
        $settings = $this->settings('text', $options);
        $data = $this->api->create([
            'model' => $settings['model'],
            'input' => $this->normalizeMessages($messages),
        ], $settings['provider'], $settings['connection'], $settings['timeout'], $options['command'] ?? null);

        $parts = [];
        foreach ($data['output'] ?? [] as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach (is_array($item['content'] ?? null) ? $item['content'] : [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $parts[] = $content['text'];
                }
            }
        }
        $text = trim(implode("\n", $parts));
        if ($text === '') {
            throw new \RuntimeException('AI returned no text');
        }

        return $text;
    }

    public function generateImage(array $messages, array $options = []): array
    {
        $settings = $this->settings('image', $options);
        $startedAt = hrtime(true);
        $imageApi = $settings['connection']['image_api'] ?? 'responses';
        if ($settings['provider'] === 'openai' && $imageApi === 'images') {
            $data = $this->images->create($messages, $settings['model'], $options, $settings['connection'],
                $settings['timeout'], $options['command'] ?? null);
        } elseif ($imageApi === 'responses') {
            $data = $this->generateImageWithResponses($messages, $options, $settings);
        } else {
            throw new \RuntimeException('Unknown or unsupported AI image API');
        }

        $image = self::imageFromResponsePayload($data);
        if (!empty($image['url']) && empty($image['bytes'])) {
            $remaining = $settings['timeout'] - (hrtime(true) - $startedAt) / 1e9;
            if ($remaining <= 0) {
                throw new \RuntimeException('AI image request timed out before download');
            }
            $image = $this->downloadImage($image['url'], $remaining, $settings['connection']['proxy'] ?? null);
        }
        if (empty($image['bytes']) || !is_string($image['bytes'])) {
            throw new \RuntimeException('AI returned no image bytes');
        }

        // Validate the actual bytes, not a provider's MIME label or data URL.
        $info = @getimagesizefromstring($image['bytes']);
        $mime = $info['mime'] ?? null;
        if (!isset(self::IMAGE_MIME_EXTENSIONS[$mime])) {
            throw new \RuntimeException('AI returned an invalid or unsupported image');
        }

        return [
            'bytes' => $image['bytes'],
            'mime' => $mime,
            'extension' => self::IMAGE_MIME_EXTENSIONS[$mime],
            'model' => $settings['model'],
        ];
    }

    private function generateImageWithResponses(array $messages, array $options, array $settings): array
    {
        $imageTool = ['type' => 'image_generation'];
        foreach (['size', 'quality', 'output_format', 'background'] as $optionKey) {
            if (!empty($options[$optionKey])) {
                $imageTool[$optionKey] = $options[$optionKey];
            }
        }
        return $this->api->create([
            'model' => $settings['model'],
            'input' => $this->normalizeMessages($messages),
            'tools' => [$imageTool],
            'tool_choice' => ['type' => 'image_generation'],
        ], $settings['provider'], $settings['connection'], $settings['timeout'], $options['command'] ?? null);
    }

    private function settings(string $type, array $options): array
    {
        $command = $options['command'] ?? null;
        $configuration = $this->aiSettings->all();
        $commands = $configuration['commands'];
        if ($command !== null && (!is_string($command) || !isset($commands[$command]))) {
            throw new \InvalidArgumentException('Unknown AI command');
        }
        $settings = $command === null ? [] : $commands[$command];
        if (isset($settings['type']) && $settings['type'] !== $type) {
            throw new \InvalidArgumentException('AI command does not support ' . $type . ' generation');
        }
        $settings = [
            'provider' => ($settings['provider'] ?? null) ?: $configuration['provider'],
            'model' => $options['model'] ?? (($settings['model'] ?? null) ?: $configuration['defaults'][$type]['model']),
            'timeout' => $options['timeout'] ?? $settings['timeout'] ?? $configuration['defaults'][$type]['timeout'],
        ];
        if (!is_string($settings['model']) || trim($settings['model']) === '') {
            throw new \RuntimeException('AI model is not configured');
        }
        if (!is_string($settings['provider']) || trim($settings['provider']) === '') {
            throw new \RuntimeException('AI provider is not configured');
        }
        if (!is_int($settings['timeout']) || $settings['timeout'] <= 0) {
            throw new \InvalidArgumentException('AI timeout must be a positive integer');
        }

        $settings['connection'] = $configuration['providers'][$settings['provider']] ?? [];

        return $settings;
    }

    public static function imageFromResponsePayload(array $data): array
    {
        $data = is_array($data['result'] ?? null) ? $data['result'] : $data;
        $output = is_array($data['output'] ?? null) ? $data['output'] : [];
        $found = null;
        foreach ($output as $item) {
            if (($item['type'] ?? null) === 'image_generation_call') {
                $found = self::findImagePayload($item);
                if ($found) {
                    break;
                }
            }
        }
        // Some gateways return Images-style data or a data URL in output_text.
        if (!$found && is_array($data['data'] ?? null)) {
            $found = self::findImagePayload($data['data']);
        }
        if (!$found) {
            foreach ($output as $item) {
                if (($item['type'] ?? null) !== 'message') {
                    continue;
                }
                foreach (is_array($item['content'] ?? null) ? $item['content'] : [] as $content) {
                    if (($content['type'] ?? null) === 'output_text') {
                        $found = self::findImagePayload($content['text'] ?? null);
                        if ($found) {
                            break 2;
                        }
                    }
                }
            }
        }
        if (!$found) {
            $found = self::findImagePayload(array_intersect_key($data, array_flip([
                'b64_json', 'base64', 'image_base64', 'url', 'image_url', 'mime', 'mime_type',
            ])));
        }
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

    private function downloadImage(string $url, float $timeout, ?string $proxy): array
    {
        try {
            // A fresh request deliberately carries no provider credentials.
            $response = Http::timeout($timeout)->connectTimeout(min(10, $timeout))
                ->withOptions(['proxy' => $proxy ?? ''])->get($url);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('AI image download failed');
        }
        if (!$response->successful()) {
            throw new \RuntimeException('AI image download failed with HTTP ' . $response->status());
        }

        return ['bytes' => $response->body()];
    }
}
