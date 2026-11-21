<?php

use App\Services\Ai\TokenUsage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_token_usage', function (Blueprint $table) {
            $table->id();
            $table->string('purpose', 64);
            $table->string('model', 200)->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('input_tokens')->nullable();
            $table->unsignedBigInteger('output_tokens')->nullable();
            $table->unsignedBigInteger('total_tokens')->nullable();
            $table->unsignedBigInteger('known_tokens')->nullable();
            $table->timestamp('created_at')->index();
        });

        DB::table('student_tutor_usage')->select(['id', 'user_id', 'created_at', 'usage', 'total_tokens', 'known_tokens'])
            ->chunkById(250, function ($records) {
                $rows = [];
                foreach ($records as $record) {
                    $usage = json_decode($record->usage ?? '', true);
                    $calls = is_array($usage['calls'] ?? null) ? array_filter($usage['calls'], 'is_array') : [];
                    $base = ['purpose' => 'student_tutor', 'user_id' => $record->user_id, 'created_at' => $record->created_at];
                    foreach ($calls as $call) {
                        $model = $call['model'] ?? null;
                        $rows[] = $base + ['model' => is_string($model) && $model !== '' ? mb_substr($model, 0, 200) : null]
                            + TokenUsage::counts($call);
                    }
                    if (!$calls && (int) $record->known_tokens > 0) {
                        $rows[] = $base + ['model' => null, 'total_tokens' => $record->total_tokens,
                            'known_tokens' => $record->known_tokens] + TokenUsage::counts($usage);
                    }
                }
                foreach (array_chunk($rows, 100) as $batch) {
                    DB::table('ai_token_usage')->insert($batch);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_token_usage');
    }
};
