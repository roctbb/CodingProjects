<?php

namespace App\Services\CodeChecking;

use GuzzleHttp\Client;

class RunnerClient
{
    public function __construct(private ServiceToken $tokens) {}

    public function dispatch(string $executionId, string $submissionId): void
    {
        $body = json_encode(['execution_id' => $executionId, 'submission_id' => $submissionId], JSON_THROW_ON_ERROR);
        $token = $this->tokens->issue('code-execution', 'geekexecutor', ['body_sha256' => hash('sha256', $body)]);
        $response = (new Client(['connect_timeout' => 3, 'timeout' => 15]))->post(
            rtrim(config('services.geekexecutor_url'), '/').'/api/executions', [
                'headers' => ['Authorization' => 'Bearer '.$token, 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'body' => $body,
            ]);
        $data = json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR);
        if (($data['execution_id'] ?? null) !== $executionId || !($data['accepted'] ?? false)) {
            throw new \RuntimeException('GeekExecutor did not acknowledge the execution.');
        }
    }

    public function subscription(string $executionId, int $userId): array
    {
        return ['url' => config('services.geekexecutor_socket_url') ?: config('services.geekexecutor_url'),
            'namespace' => '/executions', 'event' => 'execution_progress', 'transport' => 'websocket',
            'execution_id' => $executionId, 'expires_at' => time() + 3600,
            'token' => $this->tokens->issue('execution-subscribe', 'geekexecutor',
                ['execution_id' => $executionId, 'user_id' => $userId], 3600)];
    }

    public function status(): array
    {
        $token = $this->tokens->issue('executor-admin', 'geekexecutor', ['role' => 'admin']);
        $response = (new Client(['connect_timeout' => 3, 'timeout' => 10]))->get(
            rtrim(config('services.geekexecutor_url'), '/').'/system/status',
            ['headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json']]);
        return json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR);
    }
}
