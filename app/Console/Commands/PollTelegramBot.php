<?php

namespace App\Console\Commands;

use App\Services\TelegramBindService;
use App\Services\TelegramBot;
use App\Services\TelegramSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PollTelegramBot extends Command
{
    protected $signature = 'telegram:poll {--once : Process currently available updates and exit}';

    protected $description = 'Poll Telegram bot updates and bind users by deep-link tokens.';

    public function handle(TelegramBot $telegram, TelegramBindService $bindService, TelegramSettings $settings)
    {
        $connection = $settings->all();
        $offsetKey = $settings->pollingOffsetKey($connection);
        // Keep the old cursor only for the bot originally configured on this server.
        $legacyOffset = $offsetKey === $settings->pollingOffsetKey(config('services.telegram'))
            ? Cache::get('telegram:poll:last_update_id') : null;
        $offset = Cache::get($offsetKey, $legacyOffset);
        $updates = $telegram->getUpdates($offset !== null ? $offset + 1 : null, $this->option('once') ? 0 : 10, $connection);
        $bound = 0;

        foreach ($updates as $update) {
            if ($bindService->handleUpdate($update)) {
                $bound++;
            }

            Cache::forever($offsetKey, $update['update_id']);
        }

        $this->info('Telegram updates processed: ' . count($updates) . ', binds: ' . $bound);

        return 0;
    }
}
