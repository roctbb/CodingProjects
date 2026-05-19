<?php

namespace App\Console\Commands;

use App\Achievement;
use App\Jobs\GenerateAchievementTrophy;
use Illuminate\Console\Command;

class DispatchMissingAchievementTrophies extends Command
{
    protected $signature = 'achievements:generate-missing-trophies {--limit=100 : Maximum achievements to scan} {--sync : Generate immediately instead of queueing jobs}';

    protected $description = 'Dispatch trophy generation jobs for achievements without generated trophy images';

    public function handle()
    {
        $limit = max(1, (int) $this->option('limit'));
        $sync = (bool) $this->option('sync');
        $dispatched = 0;

        foreach (Achievement::query()->where('status', Achievement::STATUS_PUBLISHED)->orderBy('id')->cursor() as $achievement) {
            if ($dispatched >= $limit) {
                break;
            }

            if ($achievement->trophyImageUrl()) {
                continue;
            }

            if ($sync) {
                GenerateAchievementTrophy::dispatchSync($achievement->id);
            } else {
                GenerateAchievementTrophy::dispatch($achievement->id);
            }

            $dispatched++;
            $this->line(($sync ? 'Generated' : 'Dispatched') . " trophy job for achievement #{$achievement->id}: {$achievement->title}");
        }

        $this->info(($sync ? 'Generated' : 'Dispatched') . " {$dispatched} achievement trophy job(s).");

        return 0;
    }
}
