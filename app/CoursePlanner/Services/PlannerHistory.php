<?php

namespace App\CoursePlanner\Services;

use App\CoursePlanner\Models\PlannerChange;
use App\CoursePlanner\Models\PlannerCourse;
use App\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PlannerHistory
{
    public function execute(PlannerCourse $planner, User $actor, string $operation, array $input, callable $callback): array
    {
        return DB::transaction(function () use ($planner, $actor, $operation, $input, $callback) {
            $locked = PlannerCourse::whereKey($planner->id)->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', json_encode([$operation, $this->canonical(collect($input)->except(['operation_id', 'revision'])->all())]));
            $previous = PlannerChange::where('planner_course_id', $locked->id)->where('operation_id', $input['operation_id'])->first();
            if ($previous) {
                if ($previous->request_hash !== $hash) {
                    throw new ConflictHttpException('Этот идентификатор операции уже использован для другого изменения.');
                }
                return ['revision' => $previous->revision_after, 'result' => $previous->result, 'replayed' => true];
            }
            if ((int) $input['revision'] !== $locked->revision) {
                throw new ConflictHttpException('План изменён в другом окне. Обновите данные и повторите действие.');
            }
            $before = $this->snapshot($locked);
            $result = $callback($locked) ?? [];
            $after = $this->snapshot($locked);
            $delta = [];
            foreach ($before as $table => $rows) {
                foreach (array_unique(array_merge(array_keys($rows), array_keys($after[$table]))) as $id) {
                    $a = $rows[$id] ?? null;
                    $b = $after[$table][$id] ?? null;
                    if ($a !== $b) {
                        $delta[] = ['table' => $table, 'id' => $id, 'before' => $a, 'after' => $b];
                    }
                }
            }
            $locked->refresh();
            $locked->increment('revision');
            PlannerChange::create(['planner_course_id' => $locked->id, 'actor_id' => $actor->id,
                'operation_id' => $input['operation_id'], 'operation_type' => $operation, 'request_hash' => $hash,
                'revision_after' => $locked->revision, 'delta' => $delta, 'result' => $result,
                'undoable' => !in_array($operation, ['record', 'undo'], true)]);
            return ['revision' => $locked->revision, 'result' => $result];
        });
    }

    public function undo(PlannerCourse $planner, int $changeId): array
    {
        $change = PlannerChange::where('planner_course_id', $planner->id)->whereKey($changeId)->firstOrFail();
        if (!$change->undoable || (int) $change->revision_after !== $planner->revision) {
            throw new ConflictHttpException('Можно отменить только последнее изменение. Подтверждённый журнал исправляется отдельной записью.');
        }
        $delta = $change->delta;
        // Pre-subgroup operations belong to the shared audience. Keep their undo valid after migration.
        foreach ($delta as &$row) {
            if (!in_array($row['table'], ['planner_courses', 'deadline_policies'], true)
                || !\Illuminate\Support\Facades\Schema::hasColumn($row['table'], 'audience_id')) continue;
            foreach (['before', 'after'] as $side) if ($row[$side] !== null && !array_key_exists('audience_id', $row[$side])) $row[$side]['audience_id'] = 0;
        }
        unset($row);
        $current = $this->snapshot($planner);
        foreach ($delta as $row) {
            if (($current[$row['table']][$row['id']] ?? null) !== $row['after']) {
                throw new ConflictHttpException('Затронутые данные изменились после этой операции. Отмена не будет перезаписывать их.');
            }
        }
        $order = array_keys($this->snapshot($planner));
        // Restore missing parents first. Created leaf rows are removed before restoring their unique keys.
        $leaves = ['planner_resource_lesson_links', 'planner_session_resources', 'planner_session_texts', 'planner_session_records', 'planner_deadlines', 'task_deadlines', 'deadline_stages'];
        foreach (array_reverse($order) as $table) {
            if (!in_array($table, $leaves, true)) {
                continue;
            }
            foreach ($delta as $row) {
                if ($row['table'] === $table && $row['before'] === null) {
                    DB::table($table)->where('id', $row['id'])->delete();
                }
            }
        }
        foreach ($order as $table) {
            foreach ($delta as $row) {
                if ($row['table'] === $table && $row['before'] !== null && $row['after'] === null) {
                    DB::table($table)->insert($row['before']);
                }
            }
        }
        // Free unique calendar places before reversing session placements.
        $sessionIds = collect($delta)->where('table', 'planner_sessions')->filter(fn ($r) => $r['before'] && $r['after'])->pluck('id');
        if ($sessionIds->isNotEmpty()) {
            DB::table('planner_sessions')->whereIn('id', $sessionIds)->update(['calendar_slot_id' => null]);
        }
        foreach ($order as $table) {
            foreach ($delta as $row) {
                if ($row['table'] === $table && $row['before'] !== null && $row['after'] !== null) {
                    DB::table($table)->where('id', $row['id'])->update(collect($row['before'])->except('id')->all());
                }
            }
        }
        foreach (array_reverse($order) as $table) {
            if (in_array($table, $leaves, true)) {
                continue;
            }
            foreach ($delta as $row) {
                if ($row['table'] === $table && $row['before'] === null) {
                    DB::table($table)->where('id', $row['id'])->delete();
                }
            }
        }
        foreach (collect($delta)->where('table', 'deadline_policies') as $row) {
            if ($row['before']) DB::table('deadline_policies')->where('id', $row['id'])->update([
                'revision' => max($row['before']['revision'], $row['after']['revision'] ?? 0) + 1, 'updated_at' => now(),
            ]);
        }
        app(\App\Services\CourseDeadlineService::class)->forget($planner->course_id);
        return ['undone_operation_id' => $change->operation_id];
    }

    private function snapshot(PlannerCourse $planner): array
    {
        $sessionIds = DB::table('planner_sessions')->where('planner_course_id', $planner->id)->pluck('id');
        $linkIds = DB::table('planner_session_resources')->whereIn('session_id', $sessionIds)->pluck('id');
        $queries = ['planner_courses' => DB::table('planner_courses')->where('id', $planner->id)];
        foreach (['planner_course_providers', 'planner_schedule_rules', 'planner_calendar_exceptions', 'planner_calendar_slots', 'planner_sessions'] as $table) {
            $queries[$table] = DB::table($table)->where('planner_course_id', $planner->id);
        }
        foreach (['planner_session_records', 'planner_session_resources', 'planner_session_texts'] as $table) {
            $queries[$table] = DB::table($table)->whereIn('session_id', $sessionIds);
        }
        $queries['planner_resource_lesson_links'] = DB::table('planner_resource_lesson_links')->whereIn('session_resource_id', $linkIds);
        $queries['planner_deadlines'] = DB::table('planner_deadlines')->where('planner_course_id', $planner->id);
        $queries['task_deadlines'] = DB::table('task_deadlines')->where('course_id', $planner->course_id);
        if (\Illuminate\Support\Facades\Schema::hasTable('deadline_policies')) {
            $queries['deadline_policies'] = DB::table('deadline_policies')->where('course_id', $planner->course_id);
            if (\Illuminate\Support\Facades\Schema::hasColumn('deadline_policies', 'audience_id')) $queries['deadline_policies']->where('audience_id', $planner->audience_id ?? 0);
            $queries['deadline_stages'] = DB::table('deadline_stages')->whereIn('policy_id', (clone $queries['deadline_policies'])->select('id'));
        }
        if ($planner->audience_id ?? 0) $queries['task_deadlines']->whereRaw('1 = 0');
        $result = [];
        foreach ($queries as $table => $query) {
            $result[$table] = [];
            foreach ($query->orderBy('id')->get() as $record) {
                $row = (array) $record;
                if ($table === 'planner_courses') {
                    unset($row['revision'], $row['updated_at']);
                }
                $result[$table][$row['id']] = $row;
            }
        }
        return $result;
    }

    private function canonical(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value);
        }
        return array_map(fn ($v) => $this->canonical($v), $value);
    }
}
