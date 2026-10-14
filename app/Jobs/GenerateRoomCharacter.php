<?php

namespace App\Jobs;

use App\Services\RoomCharacterGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateRoomCharacter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 240;
    public $failOnTimeout = true;

    public function __construct(public int $generationId)
    {
        $this->onConnection(config('queue.default') === 'sync' ? 'database' : config('queue.default'));
    }

    public function handle(RoomCharacterGenerator $generator): void
    {
        $generator->generate($this->generationId);
    }

    public function failed(?\Throwable $exception): void
    {
        app(RoomCharacterGenerator::class)->fail($this->generationId, 'worker_failed');
    }
}
