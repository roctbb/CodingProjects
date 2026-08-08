<?php

namespace App\Services;

use App\Exceptions\OidcAuthenticationException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class SilaederOidcClient
{
    private const CACHE_TTL_SECONDS = 300;

    public function isEnabled(): bool
    {
        return (bool) config('services.silaeder_oidc.enabled')
            && $this->issuer() !== ''
            && $this->clientId() !== ''
            && $this->clientSecret() !== '';
    }

    public function createAuthorizationRequest(string $redirectUri): array
    {
        $this->ensureConfigured();
        $this->validateEndpointUrl($redirectUri);

        $metadata = $this->discovery();
        $state = $this->randomUrlSafeString(32);
        $nonce = $this->randomUrlSafeString(32);
        $codeVerifier = $this->randomUrlSafeString(64);
        $codeChallenge = $this->base64UrlEncode(hash('sha256', $codeVerifier, true));

        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid profile email roles',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'url' => $metadata['authorization_endpoint'] . '?' . $query,
            'flow' => [
                'state_hash' => hash('sha256', $state),
                'nonce' => $nonce,
                'code_verifier' => $codeVerifier,
                'redirect_uri' => $redirectUri,
                'created_at' => time(),
            ],
        ];
    }

    public function authenticate(string $code, array $flow): array
    {
        $this->ensureConfigured();

        if ($code === '' || empty($flow['code_verifier']) || empty($flow['redirect_uri']) || empty($flow['nonce'])) {
            throw new OidcAuthenticationException('OIDC callback data is incomplete.');
        }

        $metadata = $this->discovery();
        $tokenResponse = Http::acceptJson()
            ->asForm()
            ->withBasicAuth($this->clientId(), $this->clientSecret())
            ->timeout(10)
            ->post($metadata['token_endpoint'], [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $flow['redirect_uri'],
                'client_id' => $this->clientId(),
                'code_verifier' => $flow['code_verifier'],
            ]);

        if (!$tokenResponse->successful()) {
            throw new OidcAuthenticationException('OIDC token endpoint rejected the authorization code.');
        }

        $token = $tokenResponse->json();
        if (!is_array($token) || !is_string($token['id_token'] ?? null) || !is_string($token['access_token'] ?? null)) {
            throw new OidcAuthenticationException('OIDC token response is incomplete.');
        }

        $claims = $this->decodeIdToken($token['id_token']);
        $this->validateIdTokenClaims($claims, $flow['nonce']);

        $userinfoResponse = Http::acceptJson()
            ->withToken($token['access_token'])
            ->timeout(10)
            ->get($metadata['userinfo_endpoint']);

        if (!$userinfoResponse->successful()) {
            throw new OidcAuthenticationException('OIDC userinfo endpoint rejected the access token.');
        }

        $userinfo = $userinfoResponse->json();
        if (!is_array($userinfo) || !is_string($userinfo['sub'] ?? null) || $userinfo['sub'] !== $claims['sub']) {
            throw new OidcAuthenticationException('OIDC userinfo subject does not match the ID token.');
        }

        return [
            'issuer' => $this->issuer(),
            'subject' => $claims['sub'],
            'claims' => $claims,
            'userinfo' => $userinfo,
        ];
    }

    public function createLogoutRequest(string $postLogoutRedirectUri): array
    {
        $this->ensureConfigured();
        $this->validateEndpointUrl($postLogoutRedirectUri);

        $metadata = $this->discovery();
        $state = $this->randomUrlSafeString(32);
        $query = http_build_query([
            'client_id' => $this->clientId(),
            'post_logout_redirect_uri' => $postLogoutRedirectUri,
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'url' => $metadata['end_session_endpoint'] . '?' . $query,
            'flow' => [
                'state_hash' => hash('sha256', $state),
                'created_at' => time(),
            ],
        ];
    }

    private function discovery(): array
    {
        $cacheKey = 'silaeder_oidc.discovery.' . hash('sha256', $this->issuer());

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get($this->issuer() . '/.well-known/openid-configuration');

            if (!$response->successful()) {
                throw new OidcAuthenticationException('Unable to load OIDC discovery metadata.');
            }

            $metadata = $response->json();
            $required = [
                'issuer',
                'authorization_endpoint',
                'token_endpoint',
                'userinfo_endpoint',
                'jwks_uri',
                'end_session_endpoint',
            ];

            if (!is_array($metadata)) {
                throw new OidcAuthenticationException('OIDC discovery metadata is invalid.');
            }

            foreach ($required as $key) {
                if (!is_string($metadata[$key] ?? null) || $metadata[$key] === '') {
                    throw new OidcAuthenticationException("OIDC discovery metadata is missing {$key}.");
                }
            }

            if (rtrim($metadata['issuer'], '/') !== $this->issuer()) {
                throw new OidcAuthenticationException('OIDC discovery issuer does not match the configured issuer.');
            }

            foreach (array_slice($required, 1) as $key) {
                $this->validateEndpointUrl($metadata[$key]);
            }

            $algorithms = $metadata['id_token_signing_alg_values_supported'] ?? [];
            if (!is_array($algorithms) || !in_array('RS256', $algorithms, true)) {
                throw new OidcAuthenticationException('OIDC provider does not advertise RS256 ID tokens.');
            }

            $challengeMethods = $metadata['code_challenge_methods_supported'] ?? [];
            if (!is_array($challengeMethods) || !in_array('S256', $challengeMethods, true)) {
                throw new OidcAuthenticationException('OIDC provider does not advertise PKCE S256.');
            }

            return $metadata;
        });
    }

    private function decodeIdToken(string $idToken): array
    {
        try {
            return (array) JWT::decode($idToken, JWK::parseKeySet($this->jwks(), 'RS256'));
        } catch (Throwable $firstError) {
            Cache::forget($this->jwksCacheKey());

            try {
                return (array) JWT::decode($idToken, JWK::parseKeySet($this->jwks(), 'RS256'));
            } catch (Throwable $secondError) {
                throw new OidcAuthenticationException('OIDC ID token validation failed.', 0, $secondError);
            }
        }
    }

    private function jwks(): array
    {
        return Cache::remember($this->jwksCacheKey(), self::CACHE_TTL_SECONDS, function () {
            $metadata = $this->discovery();
            $response = Http::acceptJson()->timeout(10)->get($metadata['jwks_uri']);
            $jwks = $response->json();

            if (!$response->successful() || !is_array($jwks) || !is_array($jwks['keys'] ?? null)) {
                throw new OidcAuthenticationException('Unable to load OIDC signing keys.');
            }

            return $jwks;
        });
    }

    private function validateIdTokenClaims(array $claims, string $expectedNonce): void
    {
        $now = time();
        if (!is_int($claims['iat'] ?? null)
            || !is_int($claims['exp'] ?? null)
            || $claims['iat'] > $now + 60
            || $claims['exp'] <= $now
        ) {
            throw new OidcAuthenticationException('OIDC ID token timestamps are invalid.');
        }

        if (($claims['iss'] ?? null) !== $this->issuer()) {
            throw new OidcAuthenticationException('OIDC ID token issuer is invalid.');
        }

        $audience = $claims['aud'] ?? null;
        $audiences = is_string($audience) ? [$audience] : $audience;
        if (!is_array($audiences) || !in_array($this->clientId(), $audiences, true)) {
            throw new OidcAuthenticationException('OIDC ID token audience is invalid.');
        }
        if (count($audiences) > 1 && ($claims['azp'] ?? null) !== $this->clientId()) {
            throw new OidcAuthenticationException('OIDC ID token authorized party is invalid.');
        }

        if (!is_string($claims['sub'] ?? null) || $claims['sub'] === '' || strlen($claims['sub']) > 255) {
            throw new OidcAuthenticationException('OIDC ID token subject is invalid.');
        }

        if (!is_string($claims['nonce'] ?? null) || !hash_equals($expectedNonce, $claims['nonce'])) {
            throw new OidcAuthenticationException('OIDC ID token nonce is invalid.');
        }
    }

    private function validateEndpointUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        $localHttp = $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true);

        if (($scheme !== 'https' && !$localHttp) || $host === '') {
            throw new OidcAuthenticationException('OIDC discovery contains an unsafe endpoint URL.');
        }
    }

    private function ensureConfigured(): void
    {
        if (!$this->isEnabled()) {
            throw new OidcAuthenticationException('OIDC authentication is not configured.');
        }

        $this->validateEndpointUrl($this->issuer());
    }

    private function issuer(): string
    {
        return rtrim((string) config('services.silaeder_oidc.issuer'), '/');
    }

    private function clientId(): string
    {
        return (string) config('services.silaeder_oidc.client_id');
    }

    private function clientSecret(): string
    {
        return (string) config('services.silaeder_oidc.client_secret');
    }

    private function jwksCacheKey(): string
    {
        return 'silaeder_oidc.jwks.' . hash('sha256', $this->issuer());
    }

    private function randomUrlSafeString(int $bytes): string
    {
        return $this->base64UrlEncode(random_bytes($bytes));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
