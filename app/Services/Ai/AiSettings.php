<?php

namespace App\Services\Ai;

use App\AiSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiSettings
{
    public const PROVIDERS = ['gateway' => 'Собственный шлюз', 'openai' => 'OpenAI / совместимый API'];
    public const COMMANDS = [
        'text_improvement' => 'Исправление опечаток и стиля',
        'task_summary' => 'Пересказ решений',
        'achievement_variants' => 'Варианты достижения',
        'achievement_image' => 'Картинка достижения',
        'course_poster' => 'Постер программы',
        'room_character' => 'Персонаж комнаты по аватарке',
    ];

    public function all(): array
    {
        // Read on every operation so long-lived queue workers see changes immediately.
        // Until the migration is deployed, existing .env configuration remains usable.
        $stored = Schema::hasTable('ai_settings') ? AiSetting::find(1)?->settings : null;

        return array_replace_recursive(config('ai'), $stored ?? []);
    }

    public function save(array $settings, array $secrets, int $userId): void
    {
        DB::transaction(function () use ($settings, $secrets, $userId) {
            // Ensure the singleton exists before locking, including concurrent first saves.
            DB::table('ai_settings')->insertOrIgnore([
                'id' => 1, 'settings' => encrypt('[]', false), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $record = AiSetting::lockForUpdate()->findOrFail(1);
            $stored = $record->settings;
            foreach (array_keys(self::PROVIDERS) as $provider) {
                foreach (['key', 'proxy'] as $field) {
                    $path = 'providers.' . $provider . '.' . $field;
                    $input = $secrets[$provider][$field];
                    switch ($input['action']) {
                        case 'replace':
                            Arr::set($settings, $path, $input['value']);
                            break;
                        case 'clear':
                            Arr::set($settings, $path, null);
                            break;
                        case 'keep':
                            if (Arr::has($stored, $path)) {
                                Arr::set($settings, $path, Arr::get($stored, $path));
                            }
                            break;
                        // "inherit" leaves the field absent, so .env supplies it.
                    }
                }
            }
            $record->settings = $settings;
            $record->updated_by = $userId;
            $record->save();
        });
    }

    public function reset(): void
    {
        AiSetting::whereKey(1)->delete();
    }

    public function forForm(): array
    {
        $settings = $this->all();
        $configured = [];
        foreach (array_keys(self::PROVIDERS) as $provider) {
            foreach (['key', 'proxy'] as $field) {
                $configured[$provider][$field] = filled($settings['providers'][$provider][$field] ?? null);
                unset($settings['providers'][$provider][$field]);
            }
        }

        return ['settings' => $settings, 'configured' => $configured];
    }
}
