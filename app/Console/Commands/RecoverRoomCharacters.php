<?php

namespace App\Console\Commands;

use App\RoomCharacterGeneration;
use App\Services\RoomCharacterGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class RecoverRoomCharacters extends Command
{
    protected $signature = 'room-characters:recover {--prune : Also remove orphaned files older than one day}';
    protected $description = 'Refund expired room character jobs and remove completed avatar snapshots';

    public function handle(RoomCharacterGenerator $generator): int
    {
        if (!Schema::hasTable('room_character_generations')) {
            return self::SUCCESS;
        }
        RoomCharacterGeneration::whereIn('status', RoomCharacterGeneration::ACTIVE_STATUSES)
            ->where('created_at', '<=', now()->subMinutes(10))->chunkById(100, function ($generations) use ($generator) {
                foreach ($generations as $generation) {
                    $generator->fail($generation->id, 'expired', true);
                }
            });
        RoomCharacterGeneration::whereNotIn('status', RoomCharacterGeneration::ACTIVE_STATUSES)
            ->whereNotNull('source_path')->chunkById(100, function ($generations) use ($generator) {
                foreach ($generations as $generation) {
                    $generator->cleanupSource($generation->id);
                }
            });
        if ($this->option('prune')) {
            $cutoff = now()->subDay()->timestamp;
            $sources = Storage::disk('room_character_sources');
            foreach ($sources->files() as $path) {
                if ($sources->lastModified($path) < $cutoff
                    && !RoomCharacterGeneration::where('source_path', $path)->exists()) {
                    $sources->delete($path);
                }
            }
            foreach (Storage::files('room-characters') as $path) {
                if (Storage::lastModified($path) < $cutoff
                    && !RoomCharacterGeneration::where('result_path', $path)
                        ->whereExists(function ($query) {
                            $query->selectRaw('1')->from('users')
                                ->whereColumn('users.room_character_generation_id', 'room_character_generations.id');
                        })->exists()) {
                    Storage::delete($path);
                }
            }
        }
        return self::SUCCESS;
    }
}
