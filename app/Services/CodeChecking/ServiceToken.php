<?php

namespace App\Services\CodeChecking;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;

class ServiceToken
{
    public function key(): string
    {
        return (string) (config('services.geekexecutor_secret') ?: config('auth.jwt_secret'));
    }

    public function issue(string $purpose, string $audience, array $claims = [], int $ttl = 60): string
    {
        abort_unless(strlen($this->key()) >= 32, 503, 'Checker signing key is not configured.');
        return JWT::encode(array_merge($claims, ['iss' => 'codingprojects', 'aud' => $audience,
            'purpose' => $purpose, 'iat' => time(), 'exp' => time() + $ttl]), $this->key(), 'HS256');
    }

    public function verify(Request $request, string $purpose, bool $body = true): object
    {
        try {
            $claims = JWT::decode((string) $request->bearerToken(), new Key($this->key(), 'HS256'));
            abort_unless(($claims->iss ?? null) === 'geekexecutor' && ($claims->aud ?? null) === 'codingprojects'
                && ($claims->purpose ?? null) === $purpose && is_int($claims->iat ?? null) && is_int($claims->exp ?? null)
                && $claims->exp > $claims->iat && $claims->exp - $claims->iat <= 60, 401);
            if ($body) {
                abort_unless(is_string($claims->body_sha256 ?? null)
                    && hash_equals($claims->body_sha256, hash('sha256', $request->getContent())), 401);
            }
            return $claims;
        } catch (\Exception $exception) {
            abort(401, 'Invalid checker service token.');
        }
    }
}
