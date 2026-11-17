<?php

namespace App\Console\Commands;

use App\Services\GuardIdentitySync;
use App\User;
use Illuminate\Console\Command;

class SyncGuardIdentities extends Command
{
    protected $signature = 'guard:sync-identities';
    protected $description = 'Refresh account names and roles in Geekclass Guard without exporting account secrets';

    public function handle(GuardIdentitySync $guard): int
    {
        if (!$guard->enabled()) {
            $this->line('Guard identity synchronization is not configured.');
            return self::SUCCESS;
        }
        $count = 0;
        User::query()->whereIn('role', ['student', 'novice', 'teacher', 'admin'])->select(['id', 'name', 'role'])
            ->chunkById(500, function ($users) use ($guard, &$count) {
                $guard->send($users->map(fn ($user) => $user->only(['id', 'name', 'role']))->all());
                $count += $users->count();
            });
        $this->info('Guard identities synchronized: '.$count);
        return self::SUCCESS;
    }
}
