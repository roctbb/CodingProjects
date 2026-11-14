<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CodeChecking\LegacyArchive;

class ArchiveGeekPaste extends Command
{
    protected $signature='code-checking:archive-legacy {directory} {--actor=} {--apply} {--environment= : Private source runtime configuration, encrypted before storage}';
    protected $description='Archive a complete read-only GeekPaste export without assigning ownership or changing results';
    public function handle(LegacyArchive $archive):int
    {
        $this->line(json_encode($archive->run($this->argument('directory'),(int)$this->option('actor'),$this->option('apply'),$this->option('environment')),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        return 0;
    }
}
