<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResponsesClient
{
    /** Send one Responses request and return the unwrapped response. */
    public function create(array $payload, string $provider, array $settings, int $timeout, ?string $command = null): array
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
            || parse_url($endpoint, PHP_URL_USER) !== null || parse_url($endpoint, PHP_URL_PASS) !== null) {
            throw new \RuntimeException('AI Responses endpoint is invalid for ' . $provider);
        }

        $request = Http::acceptJson()->asJson()->timeout($timeout)->connectTimeout(min(10, $timeout))
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

        $context = ['provider' => $provider, 'command' => $command, 'model' => $payload['model']];
        try {
            // No automatic retry or provider fallback: either can duplicate paid generation.
            $response = $request->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            $this->fail('AI connection failed', $context);
        }

        $context['status_code'] = $response->status();
        $context['request_id'] = $response->header('x-request-id');
        if (!$response->successful()) {
            $this->fail('AI request failed with HTTP ' . $response->status(), $context);
        }

        try {
            $data = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
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

    private function fail(string $message, array $context): never
    {
        // Response bodies and HTTP exception messages can contain prompts, images or keys.
        Log::error($message, $context);
        throw new \RuntimeException($message . ' (' . $context['provider'] . ')');
    }
}
