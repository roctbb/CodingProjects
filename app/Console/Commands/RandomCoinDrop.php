<?php

namespace App\Console\Commands;

use App\CoinTransaction;
use App\CourseActivity;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RandomCoinDrop extends Command
{
    protected $signature = 'coins:random-drop';

    protected $description = 'Give a daily random GC bonus to one student';

    public function handle()
    {
        $date = Carbon::now()->toDateString();
        $comment = 'Лепрекон ' . $date;

        if (CoinTransaction::existsWithComment($comment)) {
            $this->info('Random GC bonus already awarded for ' . $date . '.');
            return 0;
        }

        $user = User::where('role', 'student')->inRandomOrder()->first();

        if (!$user) {
            $user = User::inRandomOrder()->first();
        }

        if (!$user) {
            $this->warn('No users found.');
            return 0;
        }

        CoinTransaction::register(
            $user->id,
            3,
            $comment,
            'Лепрекон принес вам 3 монеты',
            'success',
            'fas fa-rainbow'
        );
        CourseActivity::recordRandomCoinDrop($user, 3);

        $this->info('Awarded 3 GC to ' . $user->name . '.');
        return 0;
    }
}
