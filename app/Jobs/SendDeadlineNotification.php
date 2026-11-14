<?php
namespace App\Jobs;

use App\Services\DeadlineNotificationSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDeadlineNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public int $timeout = 90;
    public function __construct(public int $deliveryId) { $this->onQueue('notifications'); }
    public function handle(DeadlineNotificationSender $sender): void { $sender->send($this->deliveryId); }
}
