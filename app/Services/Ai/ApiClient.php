<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiClient
{
    /** One HTTP boundary for Responses, Images and the legacy gateway envelope. */
    public function create(array $payload, string $provider, array $settings, int $timeout, ?string $command = null,
        array $attachments = [], string $api = 'responses'): array
    {
        if (!in_array($provider, ['gateway', 'openai'], true)) {
            throw new \RuntimeException('Unknown AI provider; expected gateway or openai');
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
        if ($provider === 'gateway' && $api !== 'responses') {
            throw new \RuntimeException('The gateway only supports Responses requests');
        }

        $request = Http::acceptJson()->timeout($timeout)->connectTimeout(min(10, $timeout))
            ->withoutRedirecting()->withOptions(['proxy' => $settings['proxy'] ?? '']);
        if ($provider === 'openai') {
            $request = $request->withToken($key);
        } else {
            $params = $payload;
            unset($params['model'], $params['input']);
            $payload = [
                'token' => $key,
                'model' => $payload['model'],
                'input' => $payload['input'],
            ];
            if ($params !== []) {
                $payload['params'] = $params;
            }
        }
        foreach ($attachments as $attachment) {
            $request = $request->attach($attachment['name'], $attachment['bytes'], $attachment['filename'],
                ['Content-Type' => $attachment['mime']]);
        }
        if ($attachments === []) {
            $request = $request->asJson();
        }

        $context = ['provider' => $provider, 'command' => $command, 'model' => $payload['model'], 'api' => $api];
        try {
            // No automatic retry or provider fallback: either can duplicate paid generation.
            $response = $request->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            $this->fail('AI connection failed', $context);
        }

        $context['status_code'] = $response->status();
        $context['request_id'] = $this->safeIdentifier($response->header('x-request-id'), $settings) ?? '';
        $data = json_decode($response->body(), true);
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

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail('AI returned invalid JSON', $context);
        }
        if (!is_array($data) || !empty($data['error'])) {
            $this->fail('AI returned an invalid response or an upstream error', $context);
        }

        $result = $provider === 'gateway' ? ($data['result'] ?? null) : $data;
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

    private function safeIdentifier(mixed $value, array $settings): ?string
    {
        if (!is_string($value) || !preg_match('/\A[a-zA-Z0-9_.\[\]-]{1,128}\z/', $value)) {
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
