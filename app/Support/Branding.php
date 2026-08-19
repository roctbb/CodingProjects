<?php

namespace App\Support;

final class Branding
{
    private const DEFAULT_LOGO = 'images/icons/icons8-idea-64.png';

    private const LIGHT_DEFAULTS = [
        'primary' => '#2d9ccc',
        'primary_hover' => '#1f7ca5',
        'primary_light' => '#e8f6fc',
        'secondary' => '#6c757d',
        'accent' => '#2d9ccc',
        'info' => '#17a2b8',
        'success' => '#28a745',
        'warning' => '#ffc107',
        'danger' => '#dc3545',
    ];

    private const DARK_DEFAULTS = [
        'primary' => '#5bbce0',
        'primary_hover' => '#7dccea',
        'primary_light' => '#1a3a4a',
        'secondary' => '#6c757d',
        'accent' => '#5bbce0',
        'info' => '#67c9e8',
        'success' => '#4ade80',
        'warning' => '#fbbf24',
        'danger' => '#f87171',
    ];

    public static function logoUrl(): string
    {
        $configured = trim((string) config('branding.logo_url'));

        if ($configured === '') {
            return url(self::DEFAULT_LOGO);
        }

        if (preg_match('~^https?://~i', $configured) === 1) {
            return $configured;
        }

        return url(ltrim($configured, '/'));
    }

    public static function palette(string $mode = 'light'): array
    {
        if ($mode === 'dark') {
            return self::darkPalette();
        }

        $palette = self::LIGHT_DEFAULTS;

        foreach (array_keys($palette) as $key) {
            $configured = self::configuredColor("colors.{$key}");
            if ($configured !== null) {
                $palette[$key] = $configured;
            }
        }

        if (self::configuredColor('colors.primary') !== null) {
            $palette['primary_hover'] = self::configuredColor('colors.primary_hover')
                ?? self::mix($palette['primary'], '#000000', 18);
            $palette['primary_light'] = self::configuredColor('colors.primary_light')
                ?? self::mix($palette['primary'], '#ffffff', 88);

            if (self::configuredColor('colors.info') === null) {
                $palette['info'] = self::mix($palette['primary'], '#ffffff', 18);
            }
        }

        return $palette;
    }

    public static function buttons(string $mode = 'light'): array
    {
        $palette = self::palette($mode);
        $success = $palette['success'];

        return [
            'primary' => $palette['primary'],
            'primary_hover' => $palette['primary_hover'],
            'primary_text' => self::contrastColor($palette['primary']),
            'secondary' => $palette['secondary'],
            'secondary_hover' => self::mix($palette['secondary'], '#000000', 14),
            'secondary_text' => self::contrastColor($palette['secondary']),
            'info' => $palette['info'],
            'info_hover' => self::mix($palette['info'], '#000000', 14),
            'info_text' => self::contrastColor($palette['info']),
            'success' => $success,
            'success_hover' => self::mix($success, '#000000', 14),
            'success_text' => self::contrastColor($success),
            'warning' => $palette['warning'],
            'warning_hover' => self::mix($palette['warning'], '#000000', 12),
            'warning_text' => self::contrastColor($palette['warning']),
            'danger' => $palette['danger'],
            'danger_hover' => self::mix($palette['danger'], '#000000', 14),
            'danger_text' => self::contrastColor($palette['danger']),
        ];
    }

    public static function hasCustomPalette(): bool
    {
        foreach (['colors', 'dark_colors'] as $group) {
            foreach (array_keys(self::LIGHT_DEFAULTS) as $key) {
                if (self::configuredColor("{$group}.{$key}") !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function rgb(string $color): string
    {
        [$red, $green, $blue] = self::channels($color);

        return "{$red}, {$green}, {$blue}";
    }

    private static function darkPalette(): array
    {
        $light = self::palette();
        $palette = self::DARK_DEFAULTS;

        foreach (array_keys($palette) as $key) {
            $configured = self::configuredColor("dark_colors.{$key}");
            if ($configured !== null) {
                $palette[$key] = $configured;
                continue;
            }

            if (self::configuredColor("colors.{$key}") !== null) {
                $palette[$key] = self::mix($light[$key], '#ffffff', in_array($key, ['warning', 'secondary'], true) ? 10 : 22);
            }
        }

        if (self::configuredColor('colors.primary') !== null) {
            $palette['primary_hover'] = self::configuredColor('dark_colors.primary_hover')
                ?? self::mix($palette['primary'], '#ffffff', 18);
            $palette['primary_light'] = self::configuredColor('dark_colors.primary_light')
                ?? self::mix($light['primary'], '#0f172a', 76);

            if (self::configuredColor('dark_colors.info') === null) {
                $palette['info'] = self::mix($light['info'], '#ffffff', 22);
            }
        }

        return $palette;
    }

    private static function configuredColor(string $key): ?string
    {
        $value = trim((string) config("branding.{$key}"));

        if (preg_match('/^#[0-9a-f]{6}$/i', $value) !== 1) {
            return null;
        }

        return strtolower($value);
    }

    private static function contrastColor(string $color): string
    {
        [$red, $green, $blue] = self::channels($color);
        $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $luminance > 154 ? '#111827' : '#ffffff';
    }

    private static function mix(string $color, string $target, int $targetPercent): string
    {
        $sourceChannels = self::channels($color);
        $targetChannels = self::channels($target);
        $weight = max(0, min(100, $targetPercent)) / 100;
        $mixed = [];

        foreach ($sourceChannels as $index => $channel) {
            $mixed[] = (int) round(($channel * (1 - $weight)) + ($targetChannels[$index] * $weight));
        }

        return sprintf('#%02x%02x%02x', ...$mixed);
    }

    private static function channels(string $color): array
    {
        $hex = ltrim($color, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
