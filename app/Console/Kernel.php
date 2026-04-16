<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\BirthdaySender::class,
        Commands\RequestFeedback::class,
        Commands\LowerEmails::class,
        Commands\TestEmails::class,
        Commands\FixTyposCommand::class,
        Commands\RecalculateCoursePoints::class
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('birthdays')->daily();
        $schedule->command('feedback')->dailyAt("22:00");
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
