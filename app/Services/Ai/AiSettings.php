<?php

namespace App\Services\Ai;

use App\AiSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiSettings
{
    public const PROVIDERS = ['openai' => 'OpenAI-совместимый API'];
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

        $settings = array_replace_recursive(config('ai'), $stored ?? []);
        $settings['legacy_gateway_disabled'] = ($settings['provider'] ?? '') === 'gateway';
        if ($settings['legacy_gateway_disabled']) $settings['provider'] = 'openai';
        unset($settings['providers']['gateway']);
        foreach ($settings['commands'] as &$command) {
            if (($command['provider'] ?? null) === 'gateway') $command['provider'] = null;
        }
        return $settings;
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
            $settings = array_replace_recursive($stored, $settings);
            $settings['provider'] = 'openai';
            unset($settings['providers']['gateway']);
            if (!empty($settings['shared_models'])) {
                foreach (array_keys(self::COMMANDS) as $command) {
                    $settings['commands'][$command] = ['provider' => null, 'model' => null, 'timeout' => null];
                }
            }
            foreach (array_keys($secrets) as $provider) {
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
                        case 'inherit':
                            Arr::forget($settings, $path);
                            break;
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

    /** The review model is independent of shared models and command overrides. */
    public function recheck(): ?array
    {
        $value = $this->all()['defaults']['recheck'] ?? [];
        $model = $value['model'] ?? null;
        $timeout = filter_var($value['timeout'] ?? 180, FILTER_VALIDATE_INT);
        if (!is_string($model) || !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:\/-]{0,199}\z/', $model)
            || $timeout === false || $timeout < 1 || $timeout > 180) return null;
        return ['model' => $model, 'timeout' => $timeout];
    }

    public function forForm(): array
    {
        $settings = $this->all();
        $legacyGatewayDisabled = $settings['legacy_gateway_disabled'];
        unset($settings['legacy_gateway_disabled']);
        $configured = [];
        foreach (array_keys(self::PROVIDERS) as $provider) {
            foreach (['key', 'proxy'] as $field) {
                $configured[$provider][$field] = filled($settings['providers'][$provider][$field] ?? null);
                unset($settings['providers'][$provider][$field]);
            }
        }

        $hasCommandOverrides = empty($settings['shared_models']) && collect($settings['commands'])
            ->contains(fn ($command) => filled($command['model'] ?? null) || filled($command['provider'] ?? null));
        return ['settings' => $settings, 'configured' => $configured, 'hasCommandOverrides' => $hasCommandOverrides, 'legacyGatewayDisabled' => $legacyGatewayDisabled];
    }
}
