<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->removePayloadKeys('achievements', [
            'svg_icon', 'visual_key', 'tone', 'solution_source', 'language',
            'model', 'prompt_version', 'trophy_prompt', 'trophy_model', 'trophy_generated_at',
        ]);
        $this->removePayloadKeys('course_activities', [
            'icon_key', 'visual_key', 'svg_icon', 'trophy_image',
        ], 'ai_achievement_earned');

        if (Schema::hasColumn('achievements', 'icon_key')) {
            Schema::table('achievements', function (Blueprint $table) {
                $table->dropColumn('icon_key');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('achievements', 'icon_key')) {
            Schema::table('achievements', function (Blueprint $table) {
                $table->string('icon_key', 40)->default('sparkles');
            });
        }

        // Removed icon values and JSON metadata cannot be reconstructed.
    }

    private function removePayloadKeys(string $table, array $keys, ?string $activityType = null): void
    {
        DB::transaction(function () use ($table, $keys, $activityType) {
            $query = DB::table($table)->select('id', 'payload')->whereNotNull('payload');
            if ($activityType !== null) {
                $query->where('type', $activityType);
            }

            // Lock before reading so a concurrent PNG update is never overwritten.
            $query->lockForUpdate()->chunkById(200, function ($rows) use ($table, $keys) {
                foreach ($rows as $row) {
                    $payload = json_decode($row->payload, false, 512, JSON_THROW_ON_ERROR);
                    if (!$payload instanceof \stdClass) {
                        continue;
                    }

                    $changed = false;
                    foreach ($keys as $key) {
                        if (property_exists($payload, $key)) {
                            unset($payload->{$key});
                            $changed = true;
                        }
                    }

                    if ($changed) {
                        DB::table($table)->where('id', $row->id)->update([
                            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                        ]);
                    }
                }
            });
        });
    }
};
