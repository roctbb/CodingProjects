<?php

namespace App\Services;

use App\CoinTransaction;
use App\Lesson;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RewardNotices
{
    public function coins(CoinTransaction $transaction): void
    {
        if ($transaction->price <= 0 || !Schema::hasTable('reward_notices')
            || !User::whereKey($transaction->user_id)->where('role', 'student')->exists()) {
            return;
        }

        $this->record($transaction->user_id, 'coins', (string) $transaction->id, [
            'amount' => (int) $transaction->price,
            'reason' => mb_substr($transaction->displayComment(), 0, 500),
        ]);
    }

    public function stickers(array $stats): void
    {
        if (!Schema::hasTable('reward_notices')) {
            return;
        }
        $eligible = collect($stats)->filter(fn ($row) => $row['percent'] > 90);
        if ($eligible->isEmpty()) {
            return;
        }
        $students = User::whereIn('id', $eligible->pluck('student_id'))->where('role', 'student')->pluck('id')->flip();
        $lessons = Lesson::whereIn('id', $eligible->pluck('lesson_id'))->whereHas('steps.tasks')
            ->whereNotNull('sticker')->where('sticker', '<>', '')->get(['id', 'name', 'sticker'])->keyBy('id');

        foreach ($eligible as $row) {
            $lesson = $lessons->get($row['lesson_id']);
            if (!$lesson || !$students->has($row['student_id'])) {
                continue;
            }
            $this->record($row['student_id'], 'sticker', hash('sha256', $lesson->sticker), [
                'lesson' => $lesson->name, 'image' => $lesson->sticker,
                'course_id' => (int) $row['course_id'], 'lesson_id' => $lesson->id,
            ]);
            // The collection and the notification must reflect the same saved result.
            \Illuminate\Support\Facades\Cache::forget('user:'.$row['student_id'].':stickers');
            \Illuminate\Support\Facades\Cache::forget('user:'.$row['student_id'].':sticker_descriptions');
        }
    }

    private function record(int $userId, string $kind, string $source, array $payload): void
    {
        DB::table('reward_notices')->insertOrIgnore([
            'user_id' => $userId, 'kind' => $kind, 'source_key' => $source,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }

    public function claim(User $user): ?array
    {
        if ($user->role !== 'student' || !Schema::hasTable('reward_notices')) {
            return null;
        }

        return DB::transaction(function () use ($user) {
            // Serialize simultaneous tabs without relying on a browser-only marker.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $notice = DB::table('reward_notices')->where('user_id', $user->id)->whereNull('displayed_at')->orderBy('id')->lockForUpdate()->first();
            if (!$notice) {
                return null;
            }
            DB::table('reward_notices')->where('id', $notice->id)->update(['displayed_at' => now()]);

            return ['id' => (string) $notice->id, 'kind' => $notice->kind, 'payload' => json_decode($notice->payload, true, 512, JSON_THROW_ON_ERROR)];
        });
    }
}
