<?php
namespace App\Console\Commands;

use App\Services\CodeChecking\LegacyImport;
use Illuminate\Console\Command;

class ImportGeekPaste extends Command
{
    protected $signature = 'code-checking:import-legacy {directory} {--map= : JSON file with tasks/courses/users mappings} {--task-plan= : Reviewed task settings pinned to export and current public text} {--apply : Write verified records} {--actor= : Administrator ID} {--source-timezone=UTC} {--refresh-changed : Preserve a new historical run for changed export records} {--report= : New private JSONL preflight report path}';
    protected $description = 'Inspect or import a verified GeekPaste export without regrading or deleting source data';

    public function handle(LegacyImport $import): int
    {
        try {
            $map = $this->option('map') ? json_decode(file_get_contents($this->option('map')), true, 64, JSON_THROW_ON_ERROR) : [];
            $plan = $this->option('task-plan') ? json_decode(file_get_contents($this->option('task-plan')), true, 128, JSON_THROW_ON_ERROR) : null;
            $result = $import->run($this->argument('directory'), $map, (bool) $this->option('apply'),
                $this->option('actor') ? (int) $this->option('actor') : null, $this->option('source-timezone'), (bool)$this->option('refresh-changed'), $this->option('report'), $plan);

            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return $result['issues'] ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $error) { $this->error($error->getMessage()); return self::FAILURE; }
    }
}
