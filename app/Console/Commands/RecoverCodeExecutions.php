<?php

namespace App\Console\Commands;

use App\CodeExecutionRun;
use App\Jobs\DispatchCodeExecution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RecoverCodeExecutions extends Command
{
    protected $signature = 'code-checking:recover {--limit=100}';
    protected $description = 'Retry code executions whose dispatch was interrupted';

    public function handle(): int
    {
        if (!Schema::hasTable('code_execution_runs')) return self::SUCCESS;
        $runs = CodeExecutionRun::whereNull('dispatched_at')->whereNull('finished_at')->orderBy('created_at')
            ->where(fn ($query) => $query->whereNull('dispatch_attempted_at')->orWhere('dispatch_attempted_at', '<', now()->subMinutes(5)))
            ->limit(max(1, min(1000, (int) $this->option('limit'))))->get();
        foreach ($runs as $run) DispatchCodeExecution::dispatch($run->id);
        $this->info('Queued '.$runs->count().' executions.');
        return self::SUCCESS;
    }
}
