<?php

namespace App\Services;

use App\Achievement;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AchievementTrophyGenerator
{
    private ChatGptService $chatGpt;

    public function __construct(ChatGptService $chatGpt)
    {
        $this->chatGpt = $chatGpt;
    }

    public function generateForAchievement(Achievement $achievement): string
    {
        $prompt = $this->buildPrompt($achievement);
        $image = $this->chatGpt->generateImage([
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $prompt],
        ], [
            'model' => $this->achievementImageModel(),
            'size' => '1024x1024',
            'quality' => 'high',
            'output_format' => 'png',
            'background' => 'transparent',
            'timeout' => 180,
        ]);

        $relativePath = $this->storeImage($achievement, $image);
        $payload = $achievement->payload ?: [];
        $oldTrophy = $payload['trophy_image'] ?? null;

        $payload['trophy_image'] = $relativePath;
        $payload['trophy_prompt'] = $prompt;
        $payload['trophy_generated_at'] = now()->toDateTimeString();
        $payload['trophy_model'] = $image['model'] ?? $this->achievementImageModel();
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
            . 'Нужен один трофей/кубок на прозрачном фоне, дружелюбная polished 2D game illustration, как предмет на полке в комнате ученика. '
            . 'Форма и детали кубка должны отражать название и описание достижения: можно менять силуэт, ручки, эмблему, основание, материал, цветовые акценты. '
            . 'Без фона, рамки, стола, полки, текста, логотипов, водяных знаков и реалистичных фотографий.';
    }

    private function buildPrompt(Achievement $achievement): string
    {
        $achievement->loadMissing('course', 'task');
        $courseName = optional($achievement->course)->name ?: 'курс программирования';
        $taskName = optional($achievement->task)->name ?: 'задача';

        return "Сгенерируй прозрачный PNG-исходник для кубка достижения.\n"
            . 'Название достижения: ' . Str::limit((string) $achievement->title, 120, '') . "\n"
            . 'Описание достижения: ' . Str::limit(strip_tags((string) $achievement->description), 600, '') . "\n"
            . 'Курс: ' . Str::limit((string) $courseName, 140, '') . "\n"
            . 'Задача: ' . Str::limit((string) $taskName, 140, '') . "\n\n"
            . 'Композиция: один кубок целиком, центрирован, много прозрачного отступа по краям, читается маленьким на полке. '
            . 'Финальный результат должен быть растровым PNG с прозрачным фоном.';
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
