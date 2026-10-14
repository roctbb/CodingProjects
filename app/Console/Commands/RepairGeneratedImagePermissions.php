<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RepairGeneratedImagePermissions extends Command
{
    protected $signature = 'media:repair-generated-permissions {--dry-run : Only count affected generated images}';
    protected $description = 'Make generated room characters, trophies and posters readable by the web process';

    public function handle(): int
    {
        if (config('filesystems.disks.' . config('filesystems.default') . '.driver') !== 'local') {
            $this->error('This repair is only for a local filesystem.');
            return self::FAILURE;
        }
        foreach (['room-characters', 'achievement-trophies', 'course-posters'] as $directory) {
            if (!Storage::directoryExists($directory)) {
                continue;
            }
            $files = array_filter(Storage::files($directory), fn ($path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp'], true));
            if (!$this->option('dry-run')) {
                if (!Storage::setVisibility($directory, 'public')) {
                    $this->error('Could not update directory permissions: ' . $directory);
                    return self::FAILURE;
                }
                foreach ($files as $file) {
                    if (!Storage::setVisibility($file, 'public')) {
                        $this->error('Could not update image permissions: ' . $file);
                        return self::FAILURE;
                    }
                }
            }
            $this->info($directory . ': ' . count($files) . ' images' . ($this->option('dry-run') ? ' (dry run)' : ' repaired'));
        }

        return self::SUCCESS;
    }
}
