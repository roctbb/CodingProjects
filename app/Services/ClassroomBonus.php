<?php
namespace App\Services;

use App\CoinTransaction;
use App\Solution;
use App\Task;
use App\User;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClassroomBonus
{
    public function at(Task $task, int $courseId, int $userId, CarbonInterface $submitted): array
    {
        if (!Schema::hasTable('classroom_bonus_awards') || !DB::table('course_students')->where('course_id', $courseId)->where('user_id', $userId)->exists()) return [];
        if (DB::table('classroom_bonus_awards')->where('course_id', $courseId)->where('user_id', $userId)->where('task_id', $task->id)->exists()) return [];
        if (Solution::where('course_id', $courseId)->where('user_id', $userId)->where('task_id', $task->id)->where('submitted', '<', $submitted)
            ->where(fn ($q) => $q->where('raw_mark', '>=', $task->max_mark)->orWhere('mark', '>=', $task->max_mark))->exists()) return [];
        $step = $task->step;
        $entries = DB::table('planner_session_resources as e')->join('planner_sessions as s', 's.id', '=', 'e.session_id')
            ->join('planner_courses as p', 'p.id', '=', 's.planner_course_id')->join('planner_calendar_slots as c', 'c.id', '=', 's.calendar_slot_id')
            ->join('planner_resource_refs as r', 'r.id', '=', 'e.resource_ref_id')->join('planner_providers as provider', 'provider.id', '=', 'r.provider_id')
            ->where('p.course_id', $courseId)->where('e.layer', 'plan')->where('e.class_bonus_gc', '>', 0)->where('c.state', 'available')
            ->whereNotNull('c.start_time')->where('provider.key', 'codingprojects')->where(function ($q) use ($task, $step) {
                $q->where(fn ($q) => $q->where('r.resource_type', 'task')->where('r.external_id', (string) $task->id))
                    ->orWhere(fn ($q) => $q->where('r.resource_type', 'step')->where('r.external_id', (string) $step->id))
                    ->orWhere(fn ($q) => $q->where('r.resource_type', 'lesson')->where('r.external_id', (string) $step->lesson_id));
            })->orderByDesc('e.class_bonus_gc')->orderBy('e.id')
            ->get(['e.id', 'e.class_bonus_gc', 's.id as session_id', 'p.timezone', 'c.local_date', 'c.start_time', 'c.duration_minutes']);
        foreach ($entries as $entry) {
            $start = CarbonImmutable::parse($entry->local_date.' '.$entry->start_time, $entry->timezone);
            $end = $start->addMinutes($entry->duration_minutes);
            if ($submitted->gte($start) && $submitted->lt($end)) return ['session_id' => $entry->session_id, 'entry_id' => $entry->id,
                'amount' => $entry->class_bonus_gc, 'starts_at' => $start->utc()->toIso8601String(), 'ends_at' => $end->utc()->toIso8601String()];
        }
        return [];
    }

    public function freeze(Solution $solution): void
    {
        if (!$solution->course_id || !$solution->task_id || !$solution->user_id || !$solution->submitted || $solution->class_bonus_snapshot !== null || !Schema::hasColumn('solutions', 'class_bonus_snapshot')) return;
        // Existing graded work is not a new submission and must not acquire a retrospective bonus.
        $solution->class_bonus_snapshot = !$solution->exists || $solution->isDirty('submitted')
            ? $this->at($solution->task, $solution->course_id, $solution->user_id, $solution->submittedAt()) : [];
    }

    public function award(Solution $solution): void
    {
        $bonus = $solution->class_bonus_snapshot;
        if (empty($bonus['amount']) || $solution->mark === null || !$solution->task || $solution->task->max_mark <= 0 ||
            (int) ($bonus['graded_raw'] ?? $solution->raw_mark ?? $solution->mark) < (int) $solution->task->max_mark) return;
        DB::transaction(function () use ($solution, $bonus) {
            User::whereKey($solution->user_id)->lockForUpdate()->firstOrFail();
            $earlier = Solution::where('course_id', $solution->course_id)->where('user_id', $solution->user_id)->where('task_id', $solution->task_id)
                ->where('id', '!=', $solution->id)->where('submitted', '<', $solution->submitted)
                ->where(fn ($q) => $q->where('raw_mark', '>=', $solution->task->max_mark)->orWhere('mark', '>=', $solution->task->max_mark))->exists();
            if ($earlier) return;
            $created = DB::table('classroom_bonus_awards')->insertOrIgnore(['course_id' => $solution->course_id, 'user_id' => $solution->user_id,
                'task_id' => $solution->task_id, 'solution_id' => $solution->id, 'session_id' => $bonus['session_id'],
                'amount' => $bonus['amount'], 'created_at' => now(), 'updated_at' => now()]);
            if (!$created) return;
            $coin = CoinTransaction::register($solution->user_id, $bonus['amount'], 'Classroom task #'.$solution->task_id.' / course #'.$solution->course_id,
                'Задача решена на занятии! +'.$bonus['amount'].' GC', 'success', 'fas fa-coins');
            DB::table('classroom_bonus_awards')->where('course_id', $solution->course_id)->where('user_id', $solution->user_id)->where('task_id', $solution->task_id)->update(['coin_transaction_id' => $coin->id]);
        });
    }
}
