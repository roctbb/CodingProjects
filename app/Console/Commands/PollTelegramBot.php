<?php

namespace App\Console\Commands;

use App\Services\TelegramBindService;
use App\Services\TelegramBot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PollTelegramBot extends Command
{
    protected $signature = 'telegram:poll {--once : Process currently available updates and exit}';

    protected $description = 'Poll Telegram bot updates and bind users by deep-link tokens.';

    public function handle(TelegramBot $telegram, TelegramBindService $bindService)
    {
        $offset = Cache::get('telegram:poll:last_update_id');
        $updates = $telegram->getUpdates($offset ? $offset + 1 : null, $this->option('once') ? 0 : 10);
        $bound = 0;

        foreach ($updates as $update) {
            if ($bindService->handleUpdate($update)) {
                $bound++;
            }

            Cache::forever('telegram:poll:last_update_id', $update['update_id']);
        }

        $this->info('Telegram updates processed: ' . count($updates) . ', binds: ' . $bound);

        return 0;
    }
}
