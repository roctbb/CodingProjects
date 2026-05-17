<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\BirthdaySender::class,
        Commands\LowerEmails::class,
        Commands\TestEmails::class,
        Commands\FixTyposCommand::class,
        Commands\RecalculateCoursePoints::class,
        Commands\PollTelegramBot::class,
        Commands\RandomCoinDrop::class,
        Commands\RunPetDailyActions::class,
        Commands\SyncGeekPasteIntegrity::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('birthdays')->daily();
        $schedule->command('coins:random-drop')->daily();
        $schedule->command('pets:daily-actions')->daily();
        $schedule->command('telegram:poll --once')->everyMinute()->withoutOverlapping();
        //          ->hourly();
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
