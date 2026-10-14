<?php

namespace App\Services\Ai;

class ImagesClient
{
    public function __construct(private ApiClient $api)
    {
    }

    public function create(array $messages, string $model, array $options, array $connection, int $timeout, ?string $command): array
    {
        $url = $connection['images_url'] ?? null;
        if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)
            || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null
            || parse_url($url, PHP_URL_QUERY) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null
            || !str_ends_with(rtrim(parse_url($url, PHP_URL_PATH) ?? '', '/'), '/images')) {
            throw new \RuntimeException('AI Images URL must end in /images and contain no credentials, query or fragment');
        }

        $prompt = [];
        $attachments = [];
        foreach ($messages as $message) {
            // Images API is a single edit/generation, not a conversation.
            if (!in_array($message['role'] ?? null, ['system', 'developer', 'user'], true)) {
                throw new \InvalidArgumentException('Images API does not support conversation history');
            }
            $content = $message['content'] ?? null;
            $parts = is_string($content) ? [['type' => 'input_text', 'text' => $content]] : $content;
            if (!is_array($parts)) {
                throw new \InvalidArgumentException('Invalid Images API input');
            }
            foreach ($parts as $part) {
                if (($part['type'] ?? null) === 'input_text' && is_string($part['text'] ?? null)) {
                    $prompt[] = $part['text'];
                } elseif (($part['type'] ?? null) === 'input_image') {
                    $attachments[] = $this->attachment($part['image_url'] ?? null, count($attachments) + 1);
                } else {
                    throw new \InvalidArgumentException('Unsupported Images API content');
                }
            }
        }
        $text = trim(implode("\n\n", $prompt));
        if ($text === '' || mb_strlen($text) > 32000 || count($attachments) > 16) {
            throw new \InvalidArgumentException('Images API requires a prompt up to 32000 characters and at most 16 references');
        }

        $operation = $attachments === [] ? 'generations' : 'edits';
        $connection['endpoint'] = rtrim($url, '/') . '/' . $operation;
        $payload = ['model' => $model, 'prompt' => $text, 'n' => 1];
        foreach (['size', 'quality', 'output_format', 'background'] as $key) {
            if (!empty($options[$key])) {
                $payload[$key] = $options[$key];
            }
        }

        return $this->api->create($payload, 'openai', $connection, $timeout, $command, $attachments, 'images.' . $operation);
    }

    private function attachment(mixed $url, int $index): array
    {
        // Callers provide snapshots as data URLs. Do not fetch arbitrary remote references.
        if (!is_string($url) || !preg_match('~\Adata:image/(png|jpeg|webp);base64,([a-zA-Z0-9+/=]+)\z~', $url, $match)) {
            throw new \InvalidArgumentException('Images API references must be PNG, JPEG or WebP data URLs');
        }
        $bytes = base64_decode($match[2], true);
        $info = $bytes === false ? false : @getimagesizefromstring($bytes);
        $mime = 'image/' . $match[1];
        if (!$info || ($info['mime'] ?? null) !== $mime || strlen($bytes) > 20 * 1024 * 1024) {
            throw new \InvalidArgumentException('Invalid Images API reference');
        }

        return ['name' => 'image[]', 'bytes' => $bytes, 'filename' => 'reference-' . $index . '.' . $match[1], 'mime' => $mime];
    }
}
