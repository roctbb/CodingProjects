<?php

namespace App\Services\Ai;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TokenUsage
{
    public const PURPOSES = AiSettings::COMMANDS + [
        'student_tutor' => 'Школьный помощник',
        'code_check' => 'ИИ-проверка решений',
        'code_recheck' => 'ИИ-перепроверка решений',
        'text_generation' => 'Генерация текста',
        'image_generation' => 'Генерация изображений',
    ];

    public function record(string $purpose, ?string $model, ?int $userId, mixed $usage): void
    {
        try {
            if (Schema::hasTable('ai_token_usage')) {
                DB::table('ai_token_usage')->insert([
                    'purpose' => $purpose,
                    'model' => $model,
                    'user_id' => $userId,
                    'created_at' => now(),
                ] + self::counts($usage));
            }
        } catch (QueryException) {
            // An accounting failure must not make the caller retry a paid generation.
            Log::warning('Could not record AI token usage', ['purpose' => $purpose]);
        }
    }

    public static function counts(mixed $usage): array
    {
        $usage = is_array($usage) ? $usage : [];
        $count = static fn ($value): ?int => is_int($value) && $value >= 0 && $value <= intdiv(PHP_INT_MAX, 2) ? $value : null;
        $input = $count($usage['input_tokens'] ?? null) ?? $count($usage['prompt_tokens'] ?? null);
        $output = $count($usage['output_tokens'] ?? null) ?? $count($usage['completion_tokens'] ?? null);
        $total = $count($usage['total_tokens'] ?? null) ?? ($input !== null && $output !== null ? $input + $output : null);

        return [
            'input_tokens' => $input,
            'output_tokens' => $output,
            'total_tokens' => $total,
            'known_tokens' => $total ?? $input ?? $output,
        ];
    }
}
