<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class GeekPasteClient
{
    const EXTRA_ATTEMPT_COST = 5;

    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'connect_timeout' => 1,
            'timeout' => 2,
        ]);
    }

    public function gptRateLimitStatus(int $userId, int $taskId, ?int $courseId = null): ?array
    {
        return $this->request('GET', '/api/gpt_rate_limit/status', [
            'query' => array_filter([
                'user_id' => $userId,
                'task_id' => $taskId,
                'course_id' => $courseId,
            ], function ($value) {
                return $value !== null;
            }),
        ]);
    }

    public function addExtraGptAttempt(int $userId, int $taskId, ?int $courseId = null): ?array
    {
        return $this->request('POST', '/api/gpt_rate_limit/extra_attempt', [
            'json' => array_filter([
                'user_id' => $userId,
                'task_id' => $taskId,
                'course_id' => $courseId,
            ], function ($value) {
                return $value !== null;
            }),
        ]);
    }

    public function taskSolutions(int $taskId, int $perPage = 500, ?int $page = null, ?string $checkState = 'done'): ?array
    {
        $query = [
            'task_id' => $taskId,
            'per_page' => max(1, min($perPage, 500)),
        ];

        if ($page !== null) {
            $query['page'] = $page;
        }

        if ($checkState !== null) {
            $query['check_state'] = $checkState;
        }

        return $this->request('GET', '/api/solutions', [
            'query' => $query,
            'connect_timeout' => 2,
            'timeout' => 15,
        ]);
    }

    public function canBuyExtraGptAttempt(int $userId, int $taskId, ?int $courseId = null): bool
    {
        $status = $this->gptRateLimitStatus($userId, $taskId, $courseId);

        return $this->allowsExtraAttempt($status);
    }

    public function allowsExtraAttempt(?array $status): bool
    {
        return (bool) ($status['can_buy_extra_attempt'] ?? false);
    }

    protected function request(string $method, string $path, array $options): ?array
    {
        $baseUrl = rtrim((string) config('services.geekpaste_url'), '/');
        $apiKey = config('services.geekpaste_api_key');

        if ($baseUrl === '' || !$apiKey) {
            Log::warning('GeekPaste request skipped because credentials are not configured', [
                'method' => $method,
                'path' => $path,
                'base_url_configured' => $baseUrl !== '',
                'api_key_configured' => (bool) $apiKey,
            ]);

            return null;
        }

        $options['http_errors'] = false;
        $options['headers']['Authorization'] = 'Bearer ' . $apiKey;
        $options['headers']['Accept'] = 'application/json';

        try {
            $response = $this->client->request($method, $baseUrl . $path, $options);
        } catch (GuzzleException $e) {
            Log::warning('GeekPaste request failed: ' . $e->getMessage(), [
                'method' => $method,
                'path' => $path,
                'base_url' => $baseUrl,
            ]);

            return null;
        }

        $body = (string) $response->getBody();

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            Log::warning('GeekPaste request returned non-success status', [
                'method' => $method,
                'path' => $path,
                'base_url' => $baseUrl,
                'status' => $response->getStatusCode(),
                'response_body' => mb_substr($body, 0, 1000),
            ]);

            return null;
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            Log::warning('GeekPaste request returned invalid JSON', [
                'method' => $method,
                'path' => $path,
                'base_url' => $baseUrl,
                'status' => $response->getStatusCode(),
                'json_error' => json_last_error_msg(),
                'response_body' => mb_substr($body, 0, 1000),
            ]);

            return null;
        }

        return $data;
    }
}
