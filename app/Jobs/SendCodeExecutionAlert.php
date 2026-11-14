<?php

namespace App\Jobs;

use App\Services\CodeChecking\ExecutionAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCodeExecutionAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 90;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('notifications');
    }

    public function handle(ExecutionAlerts $alerts): void
    {
        $alerts->send($this->deliveryId);
    }
}
