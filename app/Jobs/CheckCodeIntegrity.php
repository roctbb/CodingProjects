<?php

namespace App\Jobs;

use App\CodeSubmission;
use App\Services\CodeChecking\Integrity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckCodeIntegrity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 3;
    public $timeout = 240;

    public function __construct(public string $submissionId) {}

    public function handle(Integrity $integrity): void
    {
        if (($submission = CodeSubmission::find($this->submissionId)) && $submission->preparation_state === 'ready') $integrity->check($submission);
    }
}
