<?php

namespace App\Services\Ai;

final class Connection
{
    public static function usesUnifiedImages(array $settings): bool
    {
        // RouterAI documents one JSON Images endpoint for generation and references.
        // This is selected before sending, never as a retry of a paid operation.
        return strtolower(parse_url($settings['images_url'] ?? '', PHP_URL_HOST) ?? '') === 'routerai.ru';
    }

    public static function resolve(string $provider, array $settings): array
    {
        if ($provider === 'gateway' || empty($settings['base_url'])) return $settings;
        $base = self::validateBaseUrl($settings['base_url']);
        $settings['endpoint'] = $base.'/responses';
        $settings['images_url'] = $base.'/images';
        return $settings;
    }

    public static function validateBaseUrl(mixed $url): string
    {
        if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)
            || !in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'], true)
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null
            || parse_url($url, PHP_URL_QUERY) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null
            || preg_match('~/(responses|images(?:/(?:edits|generations))?)/?$~', parse_url($url, PHP_URL_PATH) ?? '')) {
            throw new \InvalidArgumentException('AI base URL must contain the API prefix, without a method, credentials, query or fragment');
        }
        return rtrim($url, '/');
    }

    public static function legacyBaseUrl(array $settings): ?string
    {
        $endpoint = $settings['endpoint'] ?? '';
        if (!is_string($endpoint) || !str_ends_with($endpoint, '/responses')) return null;
        $base = substr($endpoint, 0, -strlen('/responses'));
        if (!empty($settings['images_url']) && rtrim($settings['images_url'], '/') !== $base.'/images') return null;
        try { return self::validateBaseUrl($base); } catch (\InvalidArgumentException) { return null; }
    }
}
