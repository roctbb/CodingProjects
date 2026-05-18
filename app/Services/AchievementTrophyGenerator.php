<?php

namespace App\Services;

use App\Achievement;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AchievementTrophyGenerator
{
    private const SIZE = '1024x1024';
    private const QUALITY = 'medium';
    private const MAGENTA_TOLERANCE = 36;

    private ChatGptService $chatGpt;

    public function __construct(ChatGptService $chatGpt)
    {
        $this->chatGpt = $chatGpt;
    }

    public function generateForAchievement(Achievement $achievement): string
    {
        $prompt = $this->buildPrompt($achievement);
        $model = $this->achievementImageModel();

        Log::info('Achievement trophy generation started', [
            'achievement_id' => $achievement->id,
            'model' => $model,
            'size' => self::SIZE,
            'quality' => self::QUALITY,
        ]);

        $image = $this->generateImage($prompt, $model);
        $image = $this->removeMagentaBackground($image);

        $relativePath = $this->storeImage($achievement, $image);
        $payload = $achievement->payload ?: [];
        $oldTrophy = $payload['trophy_image'] ?? null;

        $payload['trophy_image'] = $relativePath;
        $payload['trophy_prompt'] = $prompt;
        $payload['trophy_generated_at'] = now()->toDateTimeString();
        $payload['trophy_model'] = $image['model'] ?? $model;
        $achievement->payload = $payload;
        $achievement->save();

        if ($oldTrophy && $oldTrophy !== $relativePath && Str::startsWith($oldTrophy, 'achievement-trophies/achievement-' . $achievement->id . '-')) {
            Storage::delete($oldTrophy);
        }

        return $relativePath;
    }

    private function systemPrompt(): string
    {
        return 'Ты иллюстратор игровых достижений для платформы по программированию. '
            . 'Сгенерируй именно растровое PNG-изображение, не SVG и не текстовое описание. '
            . 'Нужен один трофей/кубок на чистом сплошном фоне цвета #ff00ff, дружелюбная polished 2D game illustration, как предмет на полке в комнате ученика. '
            . 'Форма и детали кубка должны отражать название и описание достижения: можно менять силуэт, ручки, эмблему, основание, материал, цветовые акценты. '
            . 'Фон обязан быть ровным pure magenta #ff00ff без градиентов, теней, текстур и объектов. '
            . 'Не используй magenta, розовый или фуксию в самом кубке и его бликах. '
            . 'Без рамки, стола, полки, текста, логотипов, водяных знаков и реалистичных фотографий.';
    }

    private function generateImage(string $prompt, string $model): array
    {
        return $this->chatGpt->generateImage([
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $prompt],
        ], [
            'model' => $model,
            'size' => self::SIZE,
            'quality' => self::QUALITY,
            'output_format' => 'png',
            'timeout' => 180,
        ]);
    }

    private function buildPrompt(Achievement $achievement): string
    {
        $achievement->loadMissing('course', 'task');
        $courseName = optional($achievement->course)->name ?: 'курс программирования';
        $taskName = optional($achievement->task)->name ?: 'задача';

        return "Сгенерируй PNG-исходник для кубка достижения на chroma key фоне.\n"
            . 'Название достижения: ' . Str::limit((string) $achievement->title, 120, '') . "\n"
            . 'Описание достижения: ' . Str::limit(strip_tags((string) $achievement->description), 600, '') . "\n"
            . 'Курс: ' . Str::limit((string) $courseName, 140, '') . "\n"
            . 'Задача: ' . Str::limit((string) $taskName, 140, '') . "\n\n"
            . 'Композиция: один кубок целиком, центрирован, много прозрачного отступа по краям, читается маленьким на полке. '
            . 'Фон должен быть только ровным чистым цветом #ff00ff, без теней и без градиентов. '
            . 'Кубок не должен содержать magenta, розовый или фуксию, чтобы фон можно было программно вырезать. '
            . 'Финальный результат должен быть растровым PNG.';
    }

    private function removeMagentaBackground(array $image): array
    {
        if (($image['mime'] ?? null) !== 'image/png' || empty($image['bytes'])) {
            return $image;
        }

        $source = @imagecreatefromstring($image['bytes']);
        if (!$source) {
            Log::warning('Achievement trophy magenta background removal skipped: invalid image bytes');

            return $image;
        }

        imagepalettetotruecolor($source);
        imagealphablending($source, false);
        imagesavealpha($source, true);

        $width = imagesx($source);
        $height = imagesy($source);
        $transparent = imagecolorallocatealpha($source, 255, 0, 255, 127);
        $removed = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($source, $x, $y);
                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;

                if ($this->isMagentaPixel($red, $green, $blue)) {
                    imagesetpixel($source, $x, $y, $transparent);
                    $removed++;
                }
            }
        }

        ob_start();
        imagepng($source, null, 9);
        $png = ob_get_clean();
        imagedestroy($source);

        if (!is_string($png) || $png === '') {
            Log::warning('Achievement trophy magenta background removal skipped: PNG encoding failed');

            return $image;
        }

        Log::info('Achievement trophy magenta background removed', [
            'removed_pixels' => $removed,
            'total_pixels' => $width * $height,
        ]);

        $image['bytes'] = $png;
        $image['mime'] = 'image/png';
        $image['extension'] = 'png';

        return $image;
    }

    private function isMagentaPixel(int $red, int $green, int $blue): bool
    {
        return abs($red - 255) <= self::MAGENTA_TOLERANCE
            && $green <= self::MAGENTA_TOLERANCE
            && abs($blue - 255) <= self::MAGENTA_TOLERANCE;
    }

    private function storeImage(Achievement $achievement, array $image): string
    {
        $basename = 'achievement-' . $achievement->id . '-' . now()->format('YmdHis');
        $extension = $image['extension'] ?? 'png';
        $relativePath = 'achievement-trophies/' . $basename . '.' . $extension;

        if (empty($image['bytes']) || !Storage::put($relativePath, $image['bytes'])) {
            throw new \RuntimeException('Could not store generated achievement trophy image');
        }

        return $relativePath;
    }

    private function achievementImageModel(): string
    {
        return config('services.chatgpt.achievement_image_model')
            ?: config('services.chatgpt.image_model')
            ?: config('services.chatgpt.achievement_model')
            ?: config('services.chatgpt.model');
    }
}
