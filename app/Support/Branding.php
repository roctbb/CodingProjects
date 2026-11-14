<?php

namespace App\Support;

final class Branding
{
    private const DEFAULT_LOGO = 'images/icons/icons8-idea-64.png';

    private const LIGHT_DEFAULTS = [
        'primary' => '#345bd1',
        'primary_hover' => '#2e4fb8',
        'primary_light' => '#edf1fe',
        'secondary' => '#6c757d',
        'accent' => '#345bd1',
        'info' => '#5877cf',
        'success' => '#237052',
        'warning' => '#976d18',
        'danger' => '#ad3547',
    ];

    private const DARK_DEFAULTS = [
        'primary' => '#a9bdff',
        'primary_hover' => '#c0ceff',
        'primary_light' => '#293959',
        'secondary' => '#6c757d',
        'accent' => '#a9bdff',
        'info' => '#a9bdff',
        'success' => '#9bdbbc',
        'warning' => '#d5b970',
        'danger' => '#ffafbd',
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

        if (self::configuredColor('colors.accent') === null) {
            $palette['accent'] = $palette['primary'];
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
                ?? self::mix($light['primary'], '#121820', 76);

            if (self::configuredColor('dark_colors.info') === null) {
                $palette['info'] = self::mix($light['info'], '#ffffff', 22);
            }
        }

        if (self::configuredColor('colors.accent') === null && self::configuredColor('dark_colors.accent') === null) {
            $palette['accent'] = $palette['primary'];
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
        $luminance = self::luminance($color);
        $whiteContrast = 1.05 / ($luminance + 0.05);
        $darkContrast = ($luminance + 0.05) / (self::luminance('#111827') + 0.05);

        if ($whiteContrast >= 4.5 && $whiteContrast >= $darkContrast) {
            return '#ffffff';
        }

        return $darkContrast >= 4.5 ? '#111827' : '#000000';
    }

    private static function luminance(string $color): float
    {
        $linear = array_map(function ($channel) {
            $value = $channel / 255;
            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, self::channels($color));

        return $linear[0] * 0.2126 + $linear[1] * 0.7152 + $linear[2] * 0.0722;
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
