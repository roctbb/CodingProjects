<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GuardIdentitySync
{
    public function enabled(): bool
    {
        return (string) config('guard.url') !== '' && (string) config('guard.secret') !== '';
    }

    public function send(array $users): void
    {
        if (!$this->enabled() || !$users) return;
        $url = (string) config('guard.url');
        $parts = parse_url($url);
        $local = app()->environment(['local', 'testing']) && in_array($parts['host'] ?? '', ['127.0.0.1', 'localhost'], true);
        if (!$parts || !isset($parts['host']) || (($parts['scheme'] ?? '') !== 'https' && !$local)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true) || strlen((string) config('guard.secret')) < 32) {
            throw new RuntimeException('Guard requires an HTTPS origin and an independent secret of at least 32 bytes.');
        }
        $payload = array_map(static fn (array $user) => [
            'id' => (int) $user['id'], 'name' => (string) $user['name'],
            'role' => $user['role'] === 'novice' ? 'student' : $user['role'],
        ], $users);
        $now = time();
        $token = JWT::encode(['sub' => 'codingprojects', 'aud' => 'guard:identities', 'iat' => $now, 'exp' => $now + 30], config('guard.secret'), 'HS256');
        try {
            $response = Http::withToken($token)->acceptJson()->asJson()->connectTimeout(3)->timeout(10)
                ->withOptions(['allow_redirects' => false])->post($url.'/api/v1/internal/identities', ['users' => $payload]);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Guard identity sync connection failed.');
        }
        if (!$response->successful()) {
            throw new RuntimeException('Guard identity sync HTTP '.$response->status());
        }
    }
}
