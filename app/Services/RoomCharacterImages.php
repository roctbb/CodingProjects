<?php

namespace App\Services;

use App\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RoomCharacterImages
{
    public function snapshot(User $user): string
    {
        if (!function_exists('imagecreatefromstring')) {
            \Illuminate\Support\Facades\Log::error('Room character generation requires PHP GD');
            throw ValidationException::withMessages(['room_character' => 'Генерация временно недоступна. Попробуйте позже.']);
        }
        if (!$user->image || !Storage::exists($user->image) || Storage::size($user->image) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['room_character' => 'Загрузите аватарку PNG, JPEG или WebP размером до 10 МБ в настройках профиля.']);
        }
        try {
            // Profile photos can exceed the generated-image limit. Decode a bounded
            // original, then send only the resized PNG snapshot to the AI provider.
            $source = $this->decode(Storage::get($user->image), maxPixels: 32_000_000);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['room_character' => 'Не удалось прочитать аватарку. Загрузите PNG, JPEG или WebP размером до 10 МБ и до 32 мегапикселей.']);
        }
        try {
            $scale = min(1, 1024 / max(imagesx($source), imagesy($source)));
            $width = max(1, (int) round(imagesx($source) * $scale));
            $height = max(1, (int) round(imagesy($source) * $scale));
            $canvas = $this->canvas($width, $height);
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
            return $this->png($canvas);
        } finally {
            imagedestroy($source);
        }
    }

    public function normalize(string $bytes, string $referencePath): string
    {
        $source = $this->decode($bytes, true);
        $reference = $this->decode(file_get_contents(public_path($referencePath)), true);
        try {
            // Match the template's silhouette bounds, preserving aspect ratio and foot baseline.
            // Re-encoding also discards metadata and produces a real RGBA PNG.
            $bounds = $this->bounds($source);
            $target = $this->bounds($reference);
            $scale = min($target['width'] / $bounds['width'], $target['height'] / $bounds['height']);
            $width = max(1, (int) round($bounds['width'] * $scale));
            $height = max(1, (int) round($bounds['height'] * $scale));
            $canvas = $this->canvas(imagesx($reference), imagesy($reference));
            $x = (int) round($target['x'] + ($target['width'] - $width) / 2);
            $y = $target['y'] + $target['height'] - $height;
            imagecopyresampled($canvas, $source, $x, $y, $bounds['x'], $bounds['y'], $width, $height, $bounds['width'], $bounds['height']);
            return $this->png($canvas);
        } finally {
            imagedestroy($source);
            imagedestroy($reference);
        }
    }

    private function decode(string $bytes, bool $pngOnly = false, int $maxPixels = 16_000_000): \GdImage
    {
        $info = @getimagesizefromstring($bytes);
        if (strlen($bytes) > 10 * 1024 * 1024 || !$info || $info[0] * $info[1] > $maxPixels
            || !in_array($info[2], $pngOnly ? [IMAGETYPE_PNG] : [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            throw new \RuntimeException('Invalid room character image');
        }
        $image = @imagecreatefromstring($bytes);
        if (!$image) {
            throw new \RuntimeException('Unreadable room character image');
        }
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        return $image;
    }

    private function bounds(\GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $left = $width;
        $top = $height;
        $right = $bottom = -1;
        $transparent = 0;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7f;
                if ($alpha >= 120) {
                    $transparent++;
                } else {
                    $left = min($left, $x);
                    $right = max($right, $x);
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                }
            }
        }
        if ($right < $left || $bottom < $top || $transparent < $width * $height * 0.05) {
            throw new \RuntimeException('Room character must have visible content and a transparent background');
        }
        return ['x' => $left, 'y' => $top, 'width' => $right - $left + 1, 'height' => $bottom - $top + 1];
    }

    private function canvas(int $width, int $height): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        return $canvas;
    }

    private function png(\GdImage $image): string
    {
        ob_start();
        try {
            if (!imagepng($image)) {
                throw new \RuntimeException('Could not encode room character PNG');
            }
            return ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($image);
        }
    }
}
