<?php

namespace App\Services;

use App\Achievement;
use App\CoinTransaction;
use App\Solution;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Called inside the removal transaction, under the student's wallet lock. */
final class SolutionRewardRemoval
{
    public function remove(User $user, Task $task, int $courseId, array $solutionIds): void
    {
        if (!$solutionIds) return;
        $achievements = Schema::hasTable('achievements') ? Achievement::where('user_id', $user->id)->where('course_id', $courseId)
            ->where('task_id', $task->id)->whereIn('solution_id', $solutionIds)->lockForUpdate()->get() : collect();
        $bonuses = Schema::hasTable('classroom_bonus_awards') ? DB::table('classroom_bonus_awards')->where('user_id', $user->id)
            ->where('course_id', $courseId)->where('task_id', $task->id)->whereIn('solution_id', $solutionIds)->lockForUpdate()->get() : collect();
        $credits = CoinTransaction::where('user_id', $user->id)->where('price', '>', 0)->whereIn('solution_id', $solutionIds)->unreversed()->get()->keyBy('id');
        // Older classroom awards already carry an exact receipt, before solution_id was added to coins.
        foreach ($bonuses as $bonus) {
            $credit = CoinTransaction::where('user_id', $user->id)->where('price', $bonus->amount)
                ->where('comment', 'Classroom task #'.$task->id.' / course #'.$courseId)->unreversed()->find($bonus->coin_transaction_id);
            if ($credit && ($credit->solution_id === null || in_array((int)$credit->solution_id, $solutionIds, true))) $credits->put($credit->id, $credit);
        }
        foreach ($achievements as $achievement) {
            // Historic manual awards were paid once per user/task. Match their creation, never a newer award.
            if ((int)($achievement->payload['coin_reward'] ?? 0) <= 0) continue;
            $matches = CoinTransaction::where('user_id', $user->id)->whereNull('solution_id')->where('price', $achievement->payload['coin_reward'])
                ->where('comment', 'Achievement Task #'.$task->id.' User #'.$user->id)->unreversed()
                ->whereBetween('created_at', [$achievement->created_at->copy()->startOfSecond(), $achievement->created_at->copy()->addMinute()])->get();
            foreach ($matches as $credit) $credits->put($credit->id, $credit);
        }
        foreach ($this->legacyTaskCredits($user, $task, $solutionIds) as $credit) $credits->put($credit->id, $credit);
        foreach ($credits as $credit) {
            // Preserve the original receipt; the unique reversal reference prevents a second debit.
            CoinTransaction::register($user->id, -(int)$credit->price, 'Отзыв награды за удалённое решение. Начисление #'.$credit->id,
                'Награда за удалённое преподавателем решение отозвана: −'.$credit->price.' GC.', 'warning', 'fas fa-rotate-left',
                solutionId: $credit->solution_id ?? $solutionIds[0], reversalOfId: $credit->id);
        }
        if (Schema::hasTable('reward_notices')) {
            DB::table('reward_notices')->where('user_id', $user->id)->where('kind', 'coins')->whereIn('source_key', $credits->keys()->map(fn ($id) => (string)$id))->delete();
            DB::table('reward_notices')->where('user_id', $user->id)->where('kind', 'achievement')->whereIn('source_key', $achievements->pluck('id')->map(fn ($id) => (string)$id))->delete();
        }
        if (Schema::hasTable('course_activities')) DB::table('course_activities')->where('user_id', $user->id)->where('course_id', $courseId)
            ->where('type', \App\CourseActivity::TYPE_AI_ACHIEVEMENT_EARNED)
            ->whereIn('payload->achievement_id', $achievements->pluck('id')->map(fn ($id) => (string)$id))->delete();
        foreach ($achievements as $achievement) {
            $path = $achievement->payload['trophy_image'] ?? null;
            if (is_string($path) && preg_match('~^achievement-trophies/achievement-'.$achievement->id.'-[a-f0-9-]+\.png$~D', $path))
                DB::afterCommit(fn () => rescue(fn () => Storage::delete($path)));
            $achievement->delete();
        }
        if ($bonuses->isNotEmpty()) DB::table('classroom_bonus_awards')->whereIn('id', $bonuses->pluck('id'))->delete();
    }

    private function legacyTaskCredits(User $user, Task $task, array $removed)
    {
        $credits = CoinTransaction::where('user_id', $user->id)->where('comment', 'Task #'.$task->id)->where('price', '>', 0)
            ->whereNull('solution_id')->unreversed()->get();
        if ($credits->isEmpty()) return $credits;
        $solutions = Solution::where('user_id', $user->id)->where('task_id', $task->id)->whereNotNull('submitted')->get();
        return $credits->filter(function ($credit) use ($solutions, $removed, $task) {
            $at = Carbon::parse($credit->created_at);
            // The audit event retains the original award-time grade even after later rechecks changed the solution.
            if (Schema::hasTable('course_activities')) {
                $events = \App\CourseActivity::where('user_id', $credit->user_id)->where('task_id', $task->id)
                    ->where('type', \App\CourseActivity::TYPE_SOLUTION_CHECKED)->whereIn('solution_id', $solutions->pluck('id'))
                    ->whereBetween('created_at', [$at->copy()->subSeconds(2), $at->copy()->addSeconds(2)])->get()
                    ->filter(fn ($event) => ($event->payload['max_mark'] ?? 0) > 0 && ($event->payload['mark'] ?? 0) >= $event->payload['max_mark'])
                    ->pluck('solution_id')->unique();
                if ($events->count() === 1) return in_array((int)$events->first(), $removed, true);
            }
            // Original task receipts did not identify a submission. An exact check/quiz timestamp can recover that link.
            $matching = $solutions->filter(fn ($solution) => ($solution->checked ?? $solution->submitted)->diffInSeconds($at, true) <= 2
                && $solution->mark !== null && (int)$solution->mark >= (int)$task->max_mark && !$solution->hasXpBooster());
            if ($matching->count() === 1) return in_array($matching->first()->id, $removed, true);
            // Without attribution, an old task-level reward loses its basis only when no qualifying attempt remains.
            return $solutions->contains(fn ($solution) => in_array($solution->id, $removed, true) && $solution->qualifiesForTaskPriceReward())
                && !$solutions->contains(fn ($solution) => !in_array($solution->id, $removed, true) && $solution->qualifiesForTaskPriceReward());
        });
    }
}
