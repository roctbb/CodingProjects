<?php

namespace App\Services;

use App\TelegramSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TelegramSettings
{
    public const SECRETS = ['bot_token', 'proxy', 'webhook_secret'];

    public function all(): array
    {
        // Read per operation: workers must see changes without a restart.
        $stored = Schema::hasTable('telegram_settings') ? TelegramSetting::find(1)?->settings : null;

        return array_replace(config('services.telegram'), $stored ?? []);
    }

    public function botUsername(): string
    {
        return ltrim(trim((string) ($this->all()['bot_username'] ?? '')), '@');
    }

    public function forForm(): array
    {
        $settings = $this->all();
        $configured = [];
        foreach (self::SECRETS as $field) {
            $configured[$field] = filled($settings[$field] ?? null);
            unset($settings[$field]);
        }

        return compact('settings', 'configured');
    }

    public function save(array $settings, array $secrets, int $userId): void
    {
        DB::transaction(function () use ($settings, $secrets, $userId) {
            DB::table('telegram_settings')->insertOrIgnore([
                'id' => 1, 'settings' => encrypt('[]', false), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $record = TelegramSetting::lockForUpdate()->findOrFail(1);
            $stored = $record->settings;
            foreach (self::SECRETS as $field) {
                $input = $secrets[$field];
                switch ($input['action']) {
                    case 'replace':
                        $settings[$field] = $input['value'];
                        break;
                    case 'clear':
                        $settings[$field] = null;
                        break;
                    case 'keep':
                        if (array_key_exists($field, $stored)) {
                            $settings[$field] = $stored[$field];
                        }
                        break;
                    // inherit removes the override and uses the server configuration.
                }
            }
            $record->settings = $settings;
            $record->updated_by = $userId;
            $record->save();
        });
    }

    public function reset(): void
    {
        TelegramSetting::whereKey(1)->delete();
    }

    public function pollingOffsetKey(array $settings): string
    {
        // Token rotation keeps the bot ID; switching bots must not reuse its offset.
        $botId = explode(':', (string) ($settings['bot_token'] ?? ''), 2)[0];

        return 'telegram:poll:' . hash('sha256', rtrim($settings['api_url'], '/') . ':' . $botId) . ':last_update_id';
    }
}
