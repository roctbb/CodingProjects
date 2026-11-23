<?php
namespace App\Services;

use App\CoinTransaction;
use App\Solution;
use App\ProgramStep;
use Illuminate\Support\Collection;
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
            ->whereNotNull('mark')->get()->contains(fn ($s) => $s->evaluatedMark() >= $task->max_mark)) return [];
        return $this->scheduled($task->step, [$task->id], $courseId, $userId, $submitted)[$task->id] ?? [];
    }

    public function forStep(ProgramStep $step, Collection $tasks, int $courseId, int $userId, CarbonInterface $at): array
    {
        if ($tasks->isEmpty() || !Schema::hasTable('classroom_bonus_awards')
            || !DB::table('course_students')->where('course_id', $courseId)->where('user_id', $userId)->exists()) return [];
        $awarded = DB::table('classroom_bonus_awards')->where('course_id', $courseId)->where('user_id', $userId)
            ->whereIn('task_id', $tasks->pluck('id'))->pluck('task_id')->flip();
        $eligible = $tasks->reject(fn ($task) => $awarded->has($task->id) || $task->solutions->contains(fn ($s) =>
            $s->user_id == $userId && $s->mark !== null && $s->submitted && $s->submitted->lt($at) && $s->evaluatedMark() >= $task->max_mark));
        return $eligible->isEmpty() ? [] : $this->scheduled($step, $eligible->pluck('id')->all(), $courseId, $userId, $at);
    }

    private function scheduled(ProgramStep $step, array $taskIds, int $courseId, int $userId, CarbonInterface $submitted): array
    {
        $audience = app(CourseSubgroups::class)->groupFor($courseId, $userId, $submitted) ?? 0;
        $entries = DB::table('planner_session_resources as e')->join('planner_sessions as s', 's.id', '=', 'e.session_id')
            ->join('planner_courses as p', 'p.id', '=', 's.planner_course_id')->join('planner_calendar_slots as c', 'c.id', '=', 's.calendar_slot_id')
            ->join('planner_resource_refs as r', 'r.id', '=', 'e.resource_ref_id')->join('planner_providers as provider', 'provider.id', '=', 'r.provider_id')
            ->where('p.course_id', $courseId)->where('e.layer', 'plan')->where('e.class_bonus_gc', '>', 0)->where('c.state', 'available')
            ->when(Schema::hasColumn('planner_courses', 'audience_id'), fn ($q) => $q->whereIn('p.audience_id', array_unique([0, $audience])))
            ->whereNotNull('c.start_time')->where('provider.key', 'codingprojects')->where(function ($q) use ($taskIds, $step) {
                $q->where(fn ($q) => $q->where('r.resource_type', 'task')->whereIn('r.external_id', array_map('strval', $taskIds)))
                    ->orWhere(fn ($q) => $q->where('r.resource_type', 'step')->where('r.external_id', (string) $step->id))
                    ->orWhere(fn ($q) => $q->where('r.resource_type', 'lesson')->where('r.external_id', (string) $step->lesson_id));
            })->orderByDesc('e.class_bonus_gc')->orderBy('e.id')
            ->get(['r.resource_type','r.external_id','e.id', 'e.class_bonus_gc', 's.id as session_id', 'p.timezone', 'c.local_date', 'c.start_time', 'c.duration_minutes']);
        $bonuses = [];
        foreach ($entries as $entry) {
            $start = CarbonImmutable::parse($entry->local_date.' '.$entry->start_time, $entry->timezone);
            $end = $start->addMinutes($entry->duration_minutes);
            if ($submitted->lt($start) || $submitted->gte($end)) continue;
            $appliesTo = $entry->resource_type === 'task' ? [$entry->external_id] : $taskIds;
            foreach ($appliesTo as $id) $bonuses[$id] ??= ['session_id' => $entry->session_id, 'entry_id' => $entry->id,
                'amount' => $entry->class_bonus_gc, 'starts_at' => $start->utc()->toIso8601String(), 'ends_at' => $end->utc()->toIso8601String()];
        }
        return $bonuses;
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
        if (!$solution->course_id || !$solution->task_id || !$solution->user_id) return;
        $this->reconcile($solution->course_id, $solution->task_id, $solution->user_id);
    }

    public function reconcile(int $courseId, int $taskId, int $userId): void
    {
        if (!Schema::hasTable('classroom_bonus_awards')) return;
        DB::transaction(function () use ($courseId, $taskId, $userId) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            if (DB::table('classroom_bonus_awards')->where('course_id', $courseId)->where('task_id', $taskId)->where('user_id', $userId)->exists()) return;
            $task = Task::find($taskId);
            if (!$task || $task->max_mark <= 0 || $task->isBlocked($userId, $courseId)) return;
            $solutions = Solution::where('course_id', $courseId)->where('task_id', $taskId)->where('user_id', $userId)
                ->whereNotNull('submitted')->orderBy('submitted')->orderBy('id')->get();
            $first = $solutions->first(fn ($s) => $s->mark !== null && $s->evaluatedMark() >= $task->max_mark);
            $bonus = $first?->class_bonus_snapshot;
            if (!$first || empty($bonus['amount'])) return;
            // Do not guess whether an earlier unreviewed answer was correct. Its later review releases or rejects this reward.
            if ($solutions->contains(fn ($s) => $s->mark === null && ($s->submitted->lt($first->submitted)
                || ($s->submitted->eq($first->submitted) && $s->id < $first->id)))) return;
            if (Schema::hasTable('code_submissions') && \App\CodeSubmission::where('course_id', $courseId)->where('task_id', $taskId)
                ->where('user_id', $userId)->where('created_at', '<', $first->submitted)->whereNull('solution_id')
                ->whereHas('currentRun', fn ($q) => $q->whereNull('finished_at'))->exists()) return;
            $created = DB::table('classroom_bonus_awards')->insertOrIgnore(['course_id'=>$courseId,'user_id'=>$userId,
                'task_id'=>$taskId,'solution_id'=>$first->id,'session_id'=>$bonus['session_id'],
                'amount'=>$bonus['amount'],'created_at'=>now(),'updated_at'=>now()]);
            if (!$created) return;
            $coin = CoinTransaction::register($userId, $bonus['amount'], 'Classroom task #'.$taskId.' / course #'.$courseId,
                'Задача решена на занятии! +'.$bonus['amount'].' GC', 'success', 'fas fa-coins', solutionId: $first->id);
            DB::table('classroom_bonus_awards')->where('course_id', $courseId)->where('user_id', $userId)->where('task_id', $taskId)
                ->update(['coin_transaction_id'=>$coin->id]);
        });
    }
}
