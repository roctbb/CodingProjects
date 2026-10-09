<?php

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;

class RecalculateUserExperience extends Command
{
    protected $signature = 'users:recalculate-experience';

    protected $description = 'Refresh experience and rank caches for all users without changing coins or course history';

    public function handle(): int
    {
        $count = 0;

        User::with('manual_rank')->chunkById(100, function ($users) use (&$count) {
            foreach ($users as $user) {
                $user->rescore();
                $user->score();
                $user->rank();
                // This is a cache refresh, not a promotion: do not award coins or notify.
                $count++;
            }
        });

        $this->info("Recalculated experience and ranks for {$count} user(s).");

        return self::SUCCESS;
    }
}
