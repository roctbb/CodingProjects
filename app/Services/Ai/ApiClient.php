<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiClient
{
    public function __construct(private TokenUsage $tokens)
    {
    }

    /** One HTTP boundary for the OpenAI-compatible Responses and Images APIs. */
    public function create(array $payload, string $provider, array $settings, int $timeout, ?string $command = null,
        array $attachments = [], string $api = 'responses', ?callable $observeUsage = null, ?int $userId = null): array
    {
        if (!array_key_exists($provider, AiSettings::PROVIDERS)) {
            throw new \RuntimeException('Unknown AI provider');
        }

        $key = $settings['key'] ?? null;
        $endpoint = $settings['endpoint'] ?? null;
        if (!is_string($key) || trim($key) === '') {
            throw new \RuntimeException('AI API key is not configured for ' . $provider);
        }
        if (!is_string($endpoint) || !filter_var($endpoint, FILTER_VALIDATE_URL)
            || !in_array(parse_url($endpoint, PHP_URL_SCHEME), ['https', 'http'], true)
            || parse_url($endpoint, PHP_URL_USER) !== null || parse_url($endpoint, PHP_URL_PASS) !== null
            || parse_url($endpoint, PHP_URL_FRAGMENT) !== null) {
            throw new \RuntimeException('AI endpoint is invalid for ' . $provider);
        }
        $request = Http::acceptJson()->timeout($timeout)->connectTimeout(min(10, $timeout))
            ->withoutRedirecting()->withOptions(['proxy' => $settings['proxy'] ?? '']);
        $request = $request->withToken($key);
        foreach ($attachments as $attachment) {
            $request = $request->attach($attachment['name'], $attachment['bytes'], $attachment['filename'],
                ['Content-Type' => $attachment['mime']]);
        }
        if ($attachments === []) {
            $request = $request->asJson();
        }

        $context = ['provider' => $provider, 'command' => $command, 'model' => $payload['model'], 'api' => $api];
        $purpose = $command ?? (str_starts_with($api, 'images') ? 'image_generation' : 'text_generation');
        $requestedModel = $this->safeModel($payload['model'], $settings);
        try {
            // No automatic retry or provider fallback: either can duplicate paid generation.
            $response = $request->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            if ($api !== 'input_tokens') {
                $this->tokens->record($purpose, $requestedModel, $userId, null);
            }
            $this->fail('AI connection failed', $context);
        }

        $context['status_code'] = $response->status();
        $context['request_id'] = $this->safeIdentifier($response->header('x-request-id'), $settings) ?? '';
        $data = json_decode($response->body(), true);
        $validJson = json_last_error() === JSON_ERROR_NONE;
        $usage = $data['usage'] ?? $data['result']['usage'] ?? null;
        $model = $this->safeModel($data['model'] ?? $data['result']['model'] ?? null, $settings);
        if ($api !== 'input_tokens') {
            $this->tokens->record($purpose, $model ?? $requestedModel, $userId, $usage);
        }
        if ($observeUsage !== null && is_array($data)) {
            $observeUsage($usage, $context['request_id'], $model);
        }
        $error = is_array($data) ? ($data['error'] ?? ($data['result']['error'] ?? null)) : null;
        if (is_array($error)) {
            foreach (['code', 'type', 'param'] as $field) {
                $value = $this->safeIdentifier($error[$field] ?? null, $settings);
                if ($value !== null) {
                    $context['error_' . $field] = $value;
                }
            }
            // NeuroAPI can put its request ID in the message instead of the header.
            // Extract only the identifier; never log the message itself.
            if ($context['request_id'] === '' && is_string($error['message'] ?? null)
                && preg_match('/\(request id: ([a-zA-Z0-9_-]{1,128})\)/', $error['message'], $match)) {
                $context['request_id'] = $this->safeIdentifier($match[1], $settings) ?? '';
            }
        }
        if (!$response->successful()) {
            $this->fail('AI request failed with HTTP ' . $response->status(), $context);
        }

        if (!$validJson) {
            $this->fail('AI returned invalid JSON', $context);
        }
        if (!is_array($data) || !empty($data['error'])) {
            $this->fail('AI returned an invalid response or an upstream error', $context);
        }

        $result = $data;
        if (!is_array($result) || !empty($result['error'])) {
            $this->fail('AI returned an invalid response or an upstream error', $context);
        }
        if (isset($result['status']) && $result['status'] !== 'completed') {
            $this->fail('AI response did not complete', $context);
        }
        if (isset($result['output']) && (!is_array($result['output']) || !array_is_list($result['output']))) {
            $this->fail('AI returned an invalid output', $context);
        }

        return $result;
    }

    private function safeModel(mixed $value, array $settings): ?string
    {
        return $this->safeIdentifier($value, $settings, '/\A[a-zA-Z0-9_.\[\]\/+:@-]{1,200}\z/');
    }

    private function safeIdentifier(mixed $value, array $settings, string $pattern = '/\A[a-zA-Z0-9_.\[\]-]{1,128}\z/'): ?string
    {
        if (!is_string($value) || !preg_match($pattern, $value)) {
            return null;
        }
        foreach (['key', 'proxy'] as $secret) {
            if (!empty($settings[$secret]) && str_contains($value, $settings[$secret])) {
                return null;
            }
        }

        return $value;
    }

    private function fail(string $message, array $context): never
    {
        // Response bodies and HTTP exception messages can contain prompts, images or keys.
        Log::error($message, $context);
        throw new \RuntimeException($message . ' (' . $context['provider'] . ')');
    }
}
