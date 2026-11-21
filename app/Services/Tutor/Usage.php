<?php

namespace App\Services\Tutor;

use App\Services\Ai\AiSettings;
use Illuminate\Support\Facades\DB;

final class Usage
{
    public function begin(int $user, string $requestId): int
    {
        return DB::transaction(function () use ($user, $requestId) {
            // Serialize admission even when two tabs ask about different courses.
            DB::table('users')->where('id', $user)->lockForUpdate()->first(['id']);
            $rows = DB::table('student_tutor_usage')->where('user_id', $user);
            abort_if((clone $rows)->where('request_id', $requestId)->exists(), 409, 'Вопрос уже был отправлен.');
            $day = now()->toDateString();
            $limits = app(AiSettings::class)->all()['tutor_limits'];
            $today = (clone $rows)->where('usage_day', $day);
            abort_if((clone $today)->where('state','pending')->exists(), 409, 'Дождитесь ответа на предыдущий вопрос.');
            abort_if((clone $today)->where('state','unknown')->exists(), 429, 'Расход по предыдущему запросу уточняется.');
            abort_if($today->count() >= $limits['daily_questions'], 429, 'Дневной лимит вопросов исчерпан.');
            abort_if($today->sum('known_tokens') >= $limits['daily_tokens'], 429, 'Дневной лимит токенов исчерпан.');
            return $rows->insertGetId(['user_id'=>$user, 'request_id'=>$requestId, 'usage_day'=>$day,
                'state'=>'pending', 'created_at'=>now(), 'updated_at'=>now()]);
        });
    }

    public function reserve(int $id, int $callTokens, array $previous): void
    {
        DB::transaction(function () use ($id, $callTokens, $previous) {
            $row = DB::table('student_tutor_usage')->where('id',$id)->lockForUpdate()->first();
            abort_unless($row && $row->state === 'pending', 409);
            $month = Budget::lockMonth($row->usage_day);
            $limits = app(AiSettings::class)->all()['tutor_limits'];
            $prior = $previous['complete'] ? $previous['known_tokens'] : max($row->reserved_tokens, $row->known_tokens);
            $required = $prior + $callTokens;
            $others = DB::table('student_tutor_usage')->where('id','<>',$id);
            $daily = (clone $others)->where('usage_day',$row->usage_day);
            $monthly = $others->where('usage_day','>=',$month)->where('usage_day','<',\Carbon\Carbon::parse($month)->addMonth()->toDateString());
            $charge = 'COALESCE(SUM(CASE WHEN state = \'complete\' OR known_tokens > reserved_tokens THEN known_tokens ELSE reserved_tokens END), 0) AS charged';
            foreach ([[(clone $daily)->where('user_id',$row->user_id), 'daily_tokens'],
                [$daily, 'platform_daily_tokens'], [$monthly, 'platform_monthly_tokens']] as [$scope, $setting]) {
                abort_if($required + (int)$scope->selectRaw($charge)->first()->charged > $limits[$setting],
                    429, 'Доступного бюджета помощника недостаточно для этого вопроса.');
            }
            DB::table('student_tutor_usage')->where('id',$id)->update(['reserved_tokens'=>$required]);
        });
    }

    public function checkpoint(int $id, array $usage): void
    {
        DB::table('student_tutor_usage')->where('id',$id)->where('state','pending')->update([
            'known_tokens'=>$usage['known_tokens'], 'usage'=>json_encode($usage, JSON_THROW_ON_ERROR), 'updated_at'=>now(),
        ]);
    }

    public function finish(int $id, ?array $usage): bool
    {
        $known = $usage['known_tokens'] ?? null;
        $input = $usage['input_tokens'] ?? null;
        $output = $usage['output_tokens'] ?? null;
        $valid = is_int($known) && $known >= 0
            && ($input === null || (is_int($input) && $input >= 0))
            && ($output === null || (is_int($output) && $output >= 0))
            && ($input === null || $output === null || $known === $input + $output)
            && is_bool($usage['complete'] ?? null) && is_array($usage['calls'] ?? null)
            && array_key_exists('total_tokens', $usage) && $usage['total_tokens'] === ($usage['complete'] ? $known : null);
        $complete = $valid && $usage['complete'];
        DB::table('student_tutor_usage')->where('id',$id)->where('state','pending')->update([
            'state'=>$complete ? 'complete' : 'unknown', 'total_tokens'=>$complete ? $known : null,
            'updated_at'=>now(),
        ] + ($valid ? ['known_tokens'=>$known, 'usage'=>json_encode($usage, JSON_THROW_ON_ERROR)] : []));
        return $valid;
    }
}

