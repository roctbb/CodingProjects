<?php
namespace App\Console\Commands;
use App\Services\DeadlineNotifications;
use Illuminate\Console\Command;
class DispatchDeadlineNotifications extends Command
{
    protected $signature = 'deadlines:dispatch';
    protected $description = 'Schedule due deadline notifications and recover their durable outbox.';
    public function handle(DeadlineNotifications $notifications): int
    {
        $this->info('Queued deadline notifications: '.$notifications->dispatchDue());
        return self::SUCCESS;
    }
}
