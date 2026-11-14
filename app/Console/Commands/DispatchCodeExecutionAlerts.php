<?php

namespace App\Console\Commands;

use App\Services\CodeChecking\ExecutionAlerts;
use Illuminate\Console\Command;

class DispatchCodeExecutionAlerts extends Command
{
    protected $signature = 'code-checking:notify';
    protected $description = 'Recover queued teacher execution alerts from their durable outbox';

    public function handle(ExecutionAlerts $alerts): int
    {
        $this->info('Queued teacher execution alerts: '.$alerts->dispatchDue());
        return self::SUCCESS;
    }
}
