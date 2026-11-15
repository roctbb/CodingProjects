<?php

namespace App\Services\CodeChecking;

use App\Solution;
use App\User;

/** The saved Solution owns XP and purchases; checker points remain separate. */
final class SolutionAward
{
    public function view(Solution $solution, User $viewer): array
    {
        $own = (int) $solution->user_id === (int) $viewer->id;
        $teacher = !$own && app(Access::class)->teacher($viewer, (int) $solution->course_id);
        $task = $solution->task;
        $active = $solution->hasActiveDeadlinePenalty();
        $base = url('/insider/courses/'.$solution->course_id.'/tasks/'.$solution->task_id.'/solution/'.$solution->id);
        $balance = $own ? (int) $viewer->balance() : null;
        $raw = $solution->mark === null ? null : $solution->evaluatedMark();
        $booster = (int) ($solution->xp_booster_amount ?? 0);
        $value = [
            'evaluated_points' => $raw,
            'deadline_penalty' => $active ? (int) $solution->deadline_penalty_amount : 0,
            'xp_booster' => $booster,
            'penalty_percent' => $active && $solution->deadline() ? (int) round((1 - $solution->deadline()->penalty) * 100) : null,
            'late_days' => (int) ($solution->deadline_penalty_days ?? 0),
            'penalty_removed' => $solution->deadline_penalty_waived_at ? 'teacher' : ($solution->deadline_penalty_paid_at ? 'paid' : null),
            'booster_used' => $solution->hasXpBooster(),
            'reward' => (int) ($task->price ?? 0),
            'reward_eligible' => $solution->qualifiesForTaskPriceReward(),
            'balance' => $balance,
            'actions' => [],
        ];
        if ($active && $solution->mark !== null && ($own || $teacher)) {
            $cost = $teacher ? 0 : $solution->deadlinePenaltyCost();
            $value['actions']['penalty'] = [
                'url' => $base.($teacher ? '/waive-deadline-penalty' : '/deadline-penalty'),
                'cost' => $cost, 'available' => $teacher || $balance >= $cost,
                'teacher' => $teacher, 'preview_xp' => (int) $solution->mark + (int) $solution->deadline_penalty_amount,
            ];
        }
        if ($own && $task->xp_booster_enabled && $solution->mark !== null && !$solution->hasXpBooster()) {
            $preview = $solution->previewMarkWithXpBooster();
            if ($preview > $solution->mark) {
                $cost = $solution->xpBoosterCost($viewer);
                $value['actions']['booster'] = [
                    'url' => $base.'/xp-booster', 'cost' => $cost, 'available' => $cost === 0 || $balance >= $cost,
                    'preview_xp' => $preview, 'added_points' => min(5, max(0, (int) $task->max_mark - (int) $solution->raw_mark)),
                ];
            }
        }
        return $value;
    }
}
