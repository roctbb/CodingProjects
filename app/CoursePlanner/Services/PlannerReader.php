<?php

namespace App\CoursePlanner\Services;

use App\Course;
use App\CoursePlanner\Models\CalendarException;
use App\CoursePlanner\Models\CalendarSlot;
use App\CoursePlanner\Models\PlannerChange;
use App\CoursePlanner\Models\PlannerCourse;
use App\CoursePlanner\Models\PlannerDeadline;
use App\CoursePlanner\Models\PlannerSession;
use App\CoursePlanner\Models\SessionRecord;
use App\CoursePlanner\Models\SessionResource;
use App\CoursePlanner\Models\SessionText;
use App\CoursePlanner\Providers\ProviderRegistry;
use App\Lesson;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PlannerReader
{
    public function __construct(private ProviderRegistry $providers, private CalendarService $calendar)
    {
    }

    public function state(Course $course, PlannerCourse $planner, string $from, string $to, string $layer): array
    {
        $providers = $this->providers->listing($planner);
        $allSessions = PlannerSession::where('planner_course_id', $planner->id)->get();
        $records = SessionRecord::whereIn('session_id', $allSessions->pluck('id'))->get()->keyBy('session_id');
        $slots = CalendarSlot::where('planner_course_id', $planner->id)->whereBetween('local_date', [$from, $to])->orderBy('local_date')->orderBy('position')->orderBy('id')->get();
        $sessions = $allSessions->filter(fn ($s) => !$s->calendar_slot_id || $slots->contains('id', $s->calendar_slot_id)
            || (in_array($layer, ['fact', 'workspace'], true) && $records->has($s->id) && $records[$s->id]->held_on >= $from && $records[$s->id]->held_on <= $to));
        $resources = SessionResource::whereIn('session_id', $sessions->pluck('id'))->get()->groupBy('session_id');
        $texts = SessionText::whereIn('session_id', $sessions->pluck('id'))->get()->groupBy('session_id');
        $relations = DB::table('planner_resource_lesson_links')->whereIn('session_resource_id', $resources->flatten()->pluck('id'))->get()->groupBy('session_resource_id');
        $deadlines = $this->deadlines($course, $planner);
        $currentDeadlines = collect($deadlines)->filter(fn ($d) => $d['resource_key'])->keyBy('resource_key');
        $sessionData = $sessions->map(function ($session) use ($resources, $texts, $records, $relations, $currentDeadlines, $planner, $slots, $layer) {
            $data = $session->toArray();
            $data['can_comment'] = $this->calendar->hasEnded($planner, $slots->firstWhere('id', $session->calendar_slot_id), $records->get($session->id));
            $data['record'] = $records->get($session->id)?->only(['id', 'held_on', 'timezone', 'duration_minutes', 'note', 'status', 'confirmed_at']);
            $data['entries'] = collect();
            foreach ($resources->get($session->id, collect()) as $link) {
                $snapshot = $link->resource_snapshot;
                $snapshot['child_count'] = count($snapshot['children_refs'] ?? []);
                unset($snapshot['children_refs']);
                $resourceKey = ($snapshot['provider_key'] ?? '').':'.($snapshot['type'] ?? '').':'.($snapshot['external_id'] ?? '');
                $data['entries']->push(['kind' => 'resource', 'id' => $link->id, 'resource_ref_id' => $link->resource_ref_id,
                    'layer' => $link->layer, 'sort_index' => $link->sort_index, 'resource' => $snapshot,
                    'lesson_links' => $relations->get($link->id, collect())->map(fn ($r) => ['lesson_id' => $r->lesson_id, 'relation' => $r->relation])->all(),
                    'fact_state' => $link->fact_state, 'note' => $link->note,
                    'deadline' => $link->layer === 'fact' ? ['due_on' => $snapshot['deadline_snapshot'] ?? null] : $currentDeadlines->get($resourceKey)]);
            }
            foreach ($texts->get($session->id, collect()) as $text) {
                $data['entries']->push(['kind' => 'text', 'id' => $text->id, 'layer' => $text->layer,
                    'sort_index' => $text->sort_index, 'text' => $text->text, 'fact_state' => $text->fact_state]);
            }
            if ($layer === 'workspace') {
                $data['entries'] = $data['entries']->sortBy(fn ($entry) => $entry['layer'] === 'plan' ? 0 : 1)
                    ->unique(fn ($entry) => $entry['kind'] === 'resource' ? 'resource:'.$entry['resource_ref_id'] : 'text:'.$entry['id']);
            }
            $data['entries'] = $data['entries']->sortBy('sort_index')->values()->all();
            return $data;
        })->values()->all();
        return ['course' => ['id' => $course->id, 'name' => $course->name], 'settings' => $this->calendar->currentSettings($planner),
            'today' => CarbonImmutable::now($planner->timezone)->toDateString(),
            'revision' => $planner->fresh()->revision, 'range' => ['from' => $from, 'to' => $to], 'slots' => $slots->toArray(),
            'sessions' => $sessionData, 'exceptions' => CalendarException::where('planner_course_id', $planner->id)->orderBy('starts_on')->get()->toArray(),
            'providers' => $providers, 'lessons' => Lesson::where('program_id', $course->program_id)->orderBy('sort_index')->orderBy('id')->get(['id', 'name', 'chapter_id'])->toArray(),
            'deadlines' => $deadlines, 'summary' => $this->summary($course, $planner, $layer),
            'history' => PlannerChange::where('planner_course_id', $planner->id)->orderByDesc('id')->limit(20)->get(['id', 'actor_id', 'operation_type', 'revision_after', 'undoable', 'created_at'])->toArray()];
    }

    public function summary(Course $course, PlannerCourse $planner, string $layer): array
    {
        $lessons = Lesson::where('program_id', $course->program_id)->orderBy('sort_index')->orderBy('id')->get(['id', 'name']);
        $sessionRows = PlannerSession::where('planner_course_id', $planner->id)->get()->keyBy('id');
        $slots = CalendarSlot::where('planner_course_id', $planner->id)->get()->keyBy('id');
        $records = SessionRecord::whereIn('session_id', $sessionRows->keys())->get()->keyBy('session_id');
        $links = SessionResource::whereIn('session_id', $sessionRows->keys())->when($layer !== 'workspace', fn ($q) => $q->where('layer', $layer))->get();
        if ($layer === 'workspace') {
            $links = $links->sortBy(fn ($link) => $link->layer === 'plan' ? 0 : 1)->unique(fn ($link) => $link->session_id.':'.$link->resource_ref_id);
        }
        if ($layer === 'fact') {
            $links = $links->filter(fn ($l) => ($records->get($l->session_id)?->status === 'confirmed') && in_array($l->fact_state, ['done', 'partial'], true));
        }
        $relations = DB::table('planner_resource_lesson_links')->whereIn('session_resource_id', $links->pluck('id'))->get()->groupBy('session_resource_id');
        $buckets = [];
        foreach ($lessons as $lesson) {
            $buckets[$lesson->id] = ['lesson_id' => $lesson->id, 'name' => $lesson->name, 'providers' => [], 'study_sessions' => [], 'deadline_count' => 0, 'task_count' => 0, 'other_deadline_count' => 0];
        }
        $buckets[0] = ['lesson_id' => null, 'name' => 'Без привязки к уроку', 'providers' => [], 'study_sessions' => [], 'deadline_count' => 0, 'task_count' => 0, 'other_deadline_count' => 0];
        $totals = [];
        $weeks = [];
        foreach ($links as $link) {
            $snapshot = $link->resource_snapshot;
            $key = $snapshot['provider_key'] ?? 'unknown';
            $state = $layer === 'fact' ? $link->fact_state : 'done';
            $this->tally($totals, $key, $link->resource_ref_id, $state);
            $date = $layer === 'fact' ? $records->get($link->session_id)?->held_on : ($slots->get($sessionRows[$link->session_id]->calendar_slot_id)?->local_date ?? ($layer === 'workspace' ? $records->get($link->session_id)?->held_on : null));
            $week = $date ? CarbonImmutable::parse($date)->startOfWeek()->toDateString() : null;
            if ($week) {
                $weeks[$week] ??= ['week' => $week, 'providers' => [], 'deadlines' => 0, 'points' => []];
                $this->tally($weeks[$week]['providers'], $key, $link->resource_ref_id, $state);
            }
            $related = $relations->get($link->id, collect());
            $targets = $related->isEmpty() ? [0] : $related->pluck('lesson_id')->unique()->all();
            foreach ($targets as $lessonId) {
                if (!isset($buckets[$lessonId])) {
                    $lessonId = 0;
                }
                $this->tally($buckets[$lessonId]['providers'], $key, $link->resource_ref_id, $state);
                if ($related->contains(fn ($r) => (int) $r->lesson_id === (int) $lessonId && $r->relation === 'study')) {
                    $buckets[$lessonId]['study_sessions'][$link->session_id] = ['session_id' => $link->session_id, 'date' => $date];
                }
            }
        }
        $tasks = DB::table('tasks as t')->join('program_steps as s', 's.id', '=', 't.step_id')->join('lessons as l', 'l.id', '=', 's.lesson_id')
            ->where('l.program_id', $course->program_id)->where('t.is_hidden', false)->where('t.is_star', false)->get(['t.id', 'l.id as lesson_id']);
        $eligibleTasks = $tasks->keyBy('id');
        foreach ($tasks as $task) {
            $buckets[$task->lesson_id]['task_count']++;
        }
        foreach ($this->deadlines($course, $planner) as $deadline) {
            $lessonId = $deadline['lesson_id'] ?? 0;
            if (isset($buckets[$lessonId])) {
                if ($deadline['provider_key'] === 'codingprojects' && $eligibleTasks->has($deadline['external_id'])) {
                    $buckets[$lessonId]['deadline_count']++;
                } elseif ($deadline['provider_key'] !== 'codingprojects') {
                    $buckets[$lessonId]['other_deadline_count']++;
                }
            }
            $week = CarbonImmutable::parse($deadline['due_on'])->startOfWeek()->toDateString();
            $weeks[$week] ??= ['week' => $week, 'providers' => [], 'deadlines' => 0, 'points' => []];
            $weeks[$week]['deadlines']++;
            $points = $deadline['points'];
            if (is_array($points) && is_numeric($points['value'] ?? null) && is_string($points['unit'] ?? null)) {
                $weeks[$week]['points'][$points['unit']] = ($weeks[$week]['points'][$points['unit']] ?? 0) + (float) $points['value'];
            }
        }
        foreach ($buckets as &$bucket) {
            $bucket['providers'] = $this->counts($bucket['providers']);
            $bucket['study_sessions'] = array_values($bucket['study_sessions']);
            $bucket['session_count'] = count($bucket['study_sessions']);
        }
        unset($bucket);
        foreach ($weeks as &$week) {
            $week['providers'] = $this->counts($week['providers']);
        }
        unset($week);
        ksort($weeks);
        return ['rows' => array_values($buckets), 'totals' => $this->counts($totals), 'weeks' => array_values($weeks)];
    }

    public function deadlines(Course $course, PlannerCourse $planner): array
    {
        $result = DB::table('task_deadlines as d')->join('tasks as t', 't.id', '=', 'd.task_id')->join('program_steps as s', 's.id', '=', 't.step_id')
            ->join('lessons as l', 'l.id', '=', 's.lesson_id')->where('d.course_id', $course->id)->where('l.program_id', $course->program_id)
            ->get(['d.id', 'd.expiration', 'd.penalty', 't.id as task_id', 't.name', 't.max_mark', 's.id as step_id', 'l.id as lesson_id'])
            ->filter(fn ($d) => filled($d->expiration))->unique('task_id')->map(fn ($d) => ['key' => 'codingprojects:'.$d->id,
                'resource_key' => 'codingprojects:task:'.$d->task_id, 'provider_key' => 'codingprojects', 'external_id' => (string) $d->task_id,
                'title' => $d->name, 'due_on' => substr($d->expiration, 0, 10), 'penalty' => (float) $d->penalty,
                'lesson_id' => (int) $d->lesson_id, 'points' => ['value' => (int) $d->max_mark, 'unit' => 'codingprojects.xp'],
                'open_url' => url('/insider/courses/'.$course->id.'/steps/'.$d->step_id.'#task'.$d->task_id)])->values()->all();
        $external = PlannerDeadline::where('planner_course_id', $planner->id)->get();
        $refs = DB::table('planner_resource_refs as r')->join('planner_providers as p', 'p.id', '=', 'r.provider_id')->whereIn('r.id', $external->pluck('resource_ref_id')->filter())
            ->get(['r.*', 'p.key as provider_key'])->keyBy('id');
        foreach ($external as $deadline) {
            $ref = $refs->get($deadline->resource_ref_id);
            $metadata = $ref ? json_decode($ref->public_metadata, true) : [];
            $result[] = ['key' => 'planner:'.$deadline->id, 'id' => $deadline->id, 'resource_ref_id' => $deadline->resource_ref_id,
                'resource_key' => $ref ? $ref->provider_key.':'.$ref->resource_type.':'.$ref->external_id : null,
                'provider_key' => $ref?->provider_key, 'external_id' => $ref?->external_id, 'title' => $deadline->title,
                'due_on' => $deadline->due_on, 'lesson_id' => $deadline->lesson_id, 'points' => $metadata['points'] ?? null, 'open_url' => $ref?->open_url];
        }
        return collect($result)->sortBy('due_on')->values()->all();
    }

    public function details(PlannerCourse $planner, string $layer, int $lessonId, string $provider, int $page): array
    {
        $query = DB::table('planner_session_resources as r')->join('planner_sessions as s', 's.id', '=', 'r.session_id')
            ->join('planner_resource_refs as ref', 'ref.id', '=', 'r.resource_ref_id')->join('planner_providers as p', 'p.id', '=', 'ref.provider_id')
            ->leftJoin('planner_calendar_slots as c', 'c.id', '=', 's.calendar_slot_id')->leftJoin('planner_session_records as j', 'j.session_id', '=', 's.id')
            ->where('s.planner_course_id', $planner->id)->where('p.key', $provider);
        if ($layer === 'workspace') {
            $query->where(fn ($q) => $q->where('r.layer', 'plan')->orWhereNotExists(fn ($q) => $q->selectRaw('1')->from('planner_session_resources as canonical')
                ->whereColumn('canonical.session_id', 'r.session_id')->whereColumn('canonical.resource_ref_id', 'r.resource_ref_id')->where('canonical.layer', 'plan')));
        } else {
            $query->where('r.layer', $layer);
        }
        if ($layer === 'fact') {
            $query->where('j.status', 'confirmed')->whereIn('r.fact_state', ['done', 'partial']);
        }
        $relation = fn ($q) => $q->selectRaw('1')->from('planner_resource_lesson_links as relation')->whereColumn('relation.session_resource_id', 'r.id');
        if ($lessonId) {
            $query->whereExists(fn ($q) => $relation($q)->where('relation.lesson_id', $lessonId));
        } else {
            $query->whereNotExists($relation);
        }
        $rows = $query->select(['r.id', 'r.session_id', 'r.resource_snapshot', 'r.fact_state', 's.title as session_title', 'c.local_date', 'j.held_on'])
            ->orderBy('r.id')->offset(($page - 1) * 25)->limit(26)->get();
        return ['items' => $rows->take(25)->map(fn ($r) => ['entry_id' => $r->id, 'session_id' => $r->session_id,
            'session_title' => $r->session_title, 'date' => $layer === 'fact' ? $r->held_on : ($r->local_date ?? ($layer === 'workspace' ? $r->held_on : null)),
            'title' => json_decode($r->resource_snapshot, true)['title'], 'fact_state' => $r->fact_state])->all(),
            'page' => $page, 'has_more' => $rows->count() > 25];
    }

    private function tally(array &$bucket, string $provider, int $id, string $state): void
    {
        $bucket[$provider] ??= ['done' => [], 'partial' => [], 'uses' => 0];
        $bucket[$provider][$state][$id] = true;
        $bucket[$provider]['uses']++;
    }

    private function counts(array $bucket): array
    {
        foreach ($bucket as &$row) {
            $row = ['materials' => count($row['done']) + count(array_diff_key($row['partial'], $row['done'])),
                'done' => count($row['done']), 'partial' => count(array_diff_key($row['partial'], $row['done'])), 'uses' => $row['uses']];
        }
        return $bucket;
    }
}
