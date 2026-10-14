<?php

namespace App\Services;

use App\Achievement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AchievementTrophyGenerator
{
    public function __construct(private ChatGptService $chatGpt)
    {
    }

    public function generateForAchievement(Achievement $achievement): string
    {
        $achievement->loadMissing('course', 'task');
        $image = $this->chatGpt->generateImage([
            ['role' => 'system', 'content' =>
                'Ты иллюстратор игровых достижений для платформы по программированию. '
                . 'Создай один дружелюбный 2D кубок, отражающий название и описание достижения. '
                . 'Кубок целиком, по центру, с отступами, читается маленьким на полке. '
                . 'PNG с прозрачным фоном. Без текста, рамки, стола, полки и водяных знаков.'],
            ['role' => 'user', 'content' =>
                'Название: ' . $achievement->title . "\n"
                . 'Описание: ' . $achievement->description . "\n"
                . 'Курс: ' . optional($achievement->course)->name . "\n"
                . 'Задача: ' . optional($achievement->task)->name],
        ], [
            'command' => 'achievement_image',
            'size' => '1024x1024',
            'quality' => 'medium',
            'output_format' => 'png',
            'background' => 'transparent',
        ]);

        $bytes = $image['bytes'] ?? null;
        $info = is_string($bytes) && $bytes !== '' ? @getimagesizefromstring($bytes) : false;
        if (!$info || $info[2] !== IMAGETYPE_PNG) {
            throw new \RuntimeException('Achievement trophy generation returned an invalid PNG');
        }

        $path = 'achievement-trophies/achievement-' . $achievement->id . '-' . Str::uuid() . '.png';
        if (!Storage::put($path, $bytes, ['visibility' => 'public', 'directory_visibility' => 'public'])) {
            throw new \RuntimeException('Could not store generated achievement trophy image');
        }

        try {
            $oldTrophy = DB::transaction(function () use ($achievement, $path) {
                $current = Achievement::lockForUpdate()->findOrFail($achievement->id);
                $payload = $current->payload ?: [];
                $oldTrophy = $payload['trophy_image'] ?? null;
                $payload['trophy_image'] = $path;
                $current->payload = $payload;
                $current->save();

                return $oldTrophy;
            });
        } catch (\Throwable $e) {
            Storage::delete($path);
            throw $e;
        }

        $achievement->refresh();
        if (is_string($oldTrophy) && Str::startsWith($oldTrophy, 'achievement-trophies/achievement-' . $achievement->id . '-')) {
            Storage::delete($oldTrophy);
        }

        return $path;
    }
}
