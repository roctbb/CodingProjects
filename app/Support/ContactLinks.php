<?php

namespace App\Support;

final class ContactLinks
{
    public static function web(?string $value): ?string
    {
        $value = trim($value ?? '');

        return filter_var($value, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($value, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)
            ? $value : null;
    }

    public static function label(?string $value, bool $telegram = false): string
    {
        $url = $telegram ? self::telegram($value) : self::web($value);
        if (!$url) {
            return trim($value ?? '');
        }
        $host = preg_replace('/^www\./i', '', parse_url($url, PHP_URL_HOST) ?? '');
        $path = trim(rawurldecode(parse_url($url, PHP_URL_PATH) ?? ''), '/');
        if ($telegram && $path !== '' && !str_contains($path, '/') && !str_starts_with($path, '+')) {
            return '@'.$path;
        }
        return $host === 'github.com' && $path !== '' ? $path : $host.($path !== '' ? '/'.$path : '');
    }

    public static function telegram(?string $value): ?string
    {
        $value = trim($value ?? '');
        if (preg_match('/^@?([a-z0-9_]+)$/i', $value, $match)) {
            return 'https://t.me/'.$match[1];
        }
        if (preg_match('~^(t\.me|telegram\.me)/~i', $value)) {
            $value = 'https://'.$value;
        }
        $url = self::web($value);

        return $url && in_array(strtolower(parse_url($url, PHP_URL_HOST) ?? ''), ['t.me', 'telegram.me'], true) ? $url : null;
    }
}
