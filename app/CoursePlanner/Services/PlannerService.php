<?php

namespace App\CoursePlanner\Services;

use App\Course;
use App\CoursePlanner\Models\CalendarException;
use App\CoursePlanner\Models\CalendarSlot;
use App\CoursePlanner\Models\PlannerCourse;
use App\CoursePlanner\Models\PlannerDeadline;
use App\CoursePlanner\Models\PlannerSession;
use App\CoursePlanner\Models\ResourceLessonLink;
use App\CoursePlanner\Models\ResourceRef;
use App\CoursePlanner\Models\SessionRecord;
use App\CoursePlanner\Models\SessionResource;
use App\CoursePlanner\Models\SessionText;
use App\CoursePlanner\Providers\ProviderRegistry;
use App\Lesson;
use App\Services\CourseDeadlineService;
use App\Task;
use App\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlannerService
{
    public function __construct(private CalendarService $calendar, private PlannerHistory $history, private ProviderRegistry $providers)
    {
    }

    public function planner(Course $course): PlannerCourse
    {
        return PlannerCourse::firstOrCreate(['course_id' => $course->id], ['timezone' => config('course_planner.timezone', 'Europe/Moscow')]);
    }

    public function command(Course $course, User $actor, string $operation, array $input): array
    {
        Validator::make($input, ['operation_id' => 'required|string|min:8|max:80', 'revision' => 'required|integer|min:0', 'unified' => 'sometimes|boolean'])->validate();
        return $this->history->execute($this->planner($course), $actor, $operation, $input, function ($planner) use ($course, $actor, $operation, $input) {
            return match ($operation) {
                'configure' => $this->calendar->rebuild($planner, $input),
                'providers' => $this->providers->configure($planner, $input),
                'add_exception' => $this->calendar->exception($planner, $input),
                'remove_exception' => $this->removeException($planner, $input),
                'add_slot' => $this->calendar->manualSlot($planner, $input),
                'add_session' => $this->addSession($planner, $input),
                'move_session' => $this->moveSession($planner, $input),
                'update_session' => $this->updateSession($planner, $input),
                'session_bonus' => $this->sessionBonus($planner, $input),
                'attach' => $this->attach($planner, $course, $actor, $input),
                'attach_many' => $this->attachMany($planner, $course, $actor, $input),
                'comment' => $this->comment($planner, $input),
                'refresh_entry' => $this->refreshEntry($planner, $course, $actor, $input),
                'split_lesson' => $this->splitLesson($planner, $course, $actor, $input),
                'relations' => $this->relations($planner, $course, $input),
                'add_text' => $this->addText($planner, $input),
                'update_entry' => $this->updateEntry($planner, $input),
                'delete_entry' => $this->deleteEntry($planner, $input),
                'move_entry' => $this->moveEntry($planner, $input),
                'set_deadline' => $this->deadline($planner, $course, $input),
                'record' => $this->record($planner, $input),
                'undo' => $this->history->undo($planner, (int) ($input['change_id'] ?? 0)),
                default => abort(404),
            };
        });
    }

    private function session(PlannerCourse $planner, mixed $id): PlannerSession
    {
        return PlannerSession::where('planner_course_id', $planner->id)->whereKey($id)->firstOrFail();
    }

    private function layer(array $input): string
    {
        return Validator::make($input, ['layer' => 'required|in:plan,fact'])->validate()['layer'];
    }

    private function addSession(PlannerCourse $planner, array $input): array
    {
        $data = Validator::make($input, ['title' => 'required|string|max:255', 'slot_id' => 'nullable|integer'])->validate();
        $slot = empty($data['slot_id']) ? null : CalendarSlot::where('planner_course_id', $planner->id)->where('state', 'available')->whereKey($data['slot_id'])->firstOrFail();
        if ($slot && PlannerSession::where('calendar_slot_id', $slot->id)->exists()) {
            throw ValidationException::withMessages(['slot_id' => 'На этой дате уже есть занятие.']);
        }
        $session = PlannerSession::create(['title' => $data['title'], 'calendar_slot_id' => $slot?->id,
            'planner_course_id' => $planner->id, 'backlog_position' => PlannerSession::where('planner_course_id', $planner->id)->max('backlog_position') + 100]);
        return ['session_id' => $session->id];
    }

    private function moveSession(PlannerCourse $planner, array $input): array
    {
        $session = $this->session($planner, $input['session_id'] ?? null);
        if (SessionRecord::where('session_id', $session->id)->exists()) {
            throw ValidationException::withMessages(['session_id' => 'У занятия есть журнал. Исправьте фактическую дату в журнале.']);
        }
        $slot = empty($input['slot_id']) ? null : CalendarSlot::where('planner_course_id', $planner->id)->whereKey($input['slot_id'])->where('state', 'available')->firstOrFail();
        if ($slot && PlannerSession::where('calendar_slot_id', $slot->id)->where('id', '!=', $session->id)->exists()) {
            $other = PlannerSession::where('calendar_slot_id', $slot->id)->first();
            if (SessionResource::where('session_id', $other->id)->exists() || SessionText::where('session_id', $other->id)->exists()
                || SessionRecord::where('session_id', $other->id)->exists() || $other->pinned || $other->title !== 'Занятие' || filled($other->plan_note) || filled($other->comment)) {
                throw ValidationException::withMessages(['slot_id' => 'Выберите свободное место или сначала перенесите его содержимое.']);
            }
            $other->delete();
        }
        $session->update(['calendar_slot_id' => $slot?->id]);
        return ['session_id' => $session->id];
    }

    private function updateSession(PlannerCourse $planner, array $input): array
    {
        $session = $this->session($planner, $input['session_id'] ?? null);
        $data = Validator::make($input, ['title' => 'sometimes|required|string|max:255', 'plan_note' => 'nullable|string|max:10000', 'pinned' => 'sometimes|boolean'])->validate();
        $session->update($data);
        return ['session_id' => $session->id];
    }

    private function attach(PlannerCourse $planner, Course $course, User $actor, array $input): array
    {
        $session = $this->session($planner, $input['session_id'] ?? null);
        $layer = $this->layer($input);
        $data = Validator::make($input, ['provider_key' => 'required|string|max:80', 'resource_type' => 'required|string|max:80', 'external_id' => 'required|string|max:190'])->validate();
        [$ref, $card] = $this->providers->resolve($planner, $course, $actor, $data['provider_key'], $data['resource_type'], $data['external_id']);
        $unified = !empty($input['unified']);
        $this->checkLessonOverlap($session, $unified ? null : $layer, $card);
        $link = $unified ? SessionResource::where('session_id', $session->id)->where('resource_ref_id', $ref->id)->orderBy('layer', 'desc')->first() : null;
        $link ??= $this->createLink($session, $layer, $ref, $card);
        if ($link->wasRecentlyCreated && $data['provider_key'] === 'codingprojects' && !empty($card['public_metadata']['lesson_id'])) {
            ResourceLessonLink::firstOrCreate(['session_resource_id' => $link->id, 'lesson_id' => $card['public_metadata']['lesson_id']], ['relation' => 'study', 'origin' => 'native_parent']);
        } elseif ($link->wasRecentlyCreated && isset($input['lesson_ids'])) {
            $this->setRelations($link, $course, $input);
        }
        if ($link->wasRecentlyCreated) {
            $this->invalidateFact($session, $layer);
        }
        return ['link_id' => $link->id];
    }

    private function attachMany(PlannerCourse $planner, Course $course, User $actor, array $input): array
    {
        $items = Validator::make($input, ['items' => 'required|array|min:1|max:50', 'items.*' => 'required|array:provider_key,resource_type,external_id',
            'items.*.provider_key' => 'required|string|max:80', 'items.*.resource_type' => 'required|string|max:80', 'items.*.external_id' => 'required|string|max:190'])->validate()['items'];
        $ids = [];
        foreach ($items as $item) {
            $ids[] = $this->attach($planner, $course, $actor, $item + ['session_id' => $input['session_id'] ?? null, 'layer' => $input['layer'] ?? 'plan', 'unified' => $input['unified'] ?? false])['link_id'];
        }
        return ['link_ids' => array_values(array_unique($ids))];
    }

    private function comment(PlannerCourse $planner, array $input): array
    {
        $session = $this->session($planner, $input['session_id'] ?? null);
        $slot = $session->calendar_slot_id ? CalendarSlot::find($session->calendar_slot_id) : null;
        $record = SessionRecord::where('session_id', $session->id)->first();
        if (!$this->calendar->hasEnded($planner, $slot, $record)) {
            throw ValidationException::withMessages(['session_id' => 'Комментарий о прошедшем занятии можно добавить после его окончания.']);
        }
        $data = Validator::make($input, ['comment' => 'present|nullable|string|max:10000'])->validate();
        $session->update(['comment' => filled($data['comment']) ? trim($data['comment']) : null]);
        return ['session_id' => $session->id];
    }

    private function createLink(PlannerSession $session, string $layer, ResourceRef $ref, array $card): SessionResource
    {
        return SessionResource::firstOrCreate(['session_id' => $session->id, 'layer' => $layer, 'resource_ref_id' => $ref->id],
            ['sort_index' => $this->nextIndex($session->id, $layer), 'resource_snapshot' => $card,
                'fact_state' => $layer === 'fact' ? 'unconfirmed' : null]);
    }

    private function refreshEntry(PlannerCourse $planner, Course $course, User $actor, array $input): array
    {
        $link = $this->entry($planner, ['kind' => 'resource', 'entry_id' => $input['entry_id'] ?? null]);
        if ($link->layer !== 'plan') {
            throw ValidationException::withMessages(['entry_id' => 'Обновление из источника доступно только для плана.']);
        }
        $old = $link->resource_snapshot;
        [, $card] = $this->providers->resolve($planner, $course, $actor, $old['provider_key'], $old['type'], $old['external_id']);
        $this->checkLessonOverlap($this->session($planner, $link->session_id), 'plan', $card, $link->id);
        $link->update(['resource_snapshot' => $card]);
        return ['link_id' => $link->id];
    }

    private function checkLessonOverlap(PlannerSession $session, ?string $layer, array $card, ?int $ignoreId = null): void
    {
        if (($card['provider_key'] ?? '') !== 'codingprojects' || !in_array($card['type'], ['lesson', 'step'], true)) {
            return;
        }
        $childIds = array_column($card['children_refs'] ?? [], 'external_id');
        foreach (SessionResource::where('session_id', $session->id)->when($layer, fn ($q) => $q->where('layer', $layer))->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->get() as $existing) {
            $snap = $existing->resource_snapshot;
            if (($snap['provider_key'] ?? '') !== 'codingprojects') {
                continue;
            }
            $stepInWhole = $card['type'] === 'step' && ($snap['type'] ?? '') === 'lesson'
                && in_array($card['external_id'], array_column($snap['children_refs'] ?? [], 'external_id'), true);
            $wholeContainsStep = $card['type'] === 'lesson' && ($snap['type'] ?? '') === 'step'
                && in_array($snap['external_id'], $childIds, true);
            if ($stepInWhole || $wholeContainsStep) {
                throw ValidationException::withMessages(['external_id' => 'На занятии пересекаются урок целиком и его ступени. Сначала разделите урок.']);
            }
        }
    }

    private function splitLesson(PlannerCourse $planner, Course $course, User $actor, array $input): array
    {
        $link = $this->entry($planner, ['kind' => 'resource', 'entry_id' => $input['link_id'] ?? null]);
        $snapshot = $link->resource_snapshot;
        if (($snapshot['provider_key'] ?? '') !== 'codingprojects' || ($snapshot['type'] ?? '') !== 'lesson') {
            throw ValidationException::withMessages(['link_id' => 'Выберите урок целиком.']);
        }
        $data = Validator::make($input, ['selected_ids' => 'required|array|min:1', 'selected_ids.*' => 'required|string|max:190'])->validate();
        $children = $snapshot['children_refs'] ?? [];
        if (array_diff($data['selected_ids'], array_column($children, 'external_id'))) {
            throw ValidationException::withMessages(['selected_ids' => 'Ступень отсутствует в сохранённом составе урока.']);
        }
        $source = $this->session($planner, $link->session_id);
        $target = $this->session($planner, $input['target_session_id'] ?? null);
        $layer = $link->layer;
        $link->delete();
        foreach ($children as $child) {
            $destination = in_array($child['external_id'], $data['selected_ids'], true) ? $target : $source;
            $this->checkLessonOverlap($destination, $layer, $child);
            $provider = DB::table('planner_providers')->where('key', 'codingprojects')->value('id');
            $ref = ResourceRef::firstOrCreate(['provider_id' => $provider, 'resource_type' => 'step', 'external_id' => $child['external_id']],
                ['title' => $child['title'], 'open_url' => $child['open_url'], 'source_revision' => $child['source_revision'], 'public_metadata' => $child['public_metadata']]);
            $new = $this->createLink($destination, $layer, $ref, $child);
            ResourceLessonLink::firstOrCreate(['session_resource_id' => $new->id, 'lesson_id' => $child['public_metadata']['lesson_id']], ['relation' => 'study', 'origin' => 'native_parent']);
        }
        $this->invalidateFact($source, $layer);
        $this->invalidateFact($target, $layer);
        return ['session_id' => $target->id];
    }

    private function relations(PlannerCourse $planner, Course $course, array $input): array
    {
        $link = $this->entry($planner, ['kind' => 'resource', 'entry_id' => $input['link_id'] ?? null]);
        $this->setRelations($link, $course, $input);
        $this->invalidateFact($this->session($planner, $link->session_id), $link->layer);
        return ['link_id' => $link->id];
    }

    private function setRelations(SessionResource $link, Course $course, array $input): void
    {
        $data = Validator::make($input, ['lesson_ids' => 'required|array|max:100', 'lesson_ids.*' => 'required|integer|distinct', 'relation' => 'required|in:study,related'])->validate();
        if (Lesson::where('program_id', $course->program_id)->whereIn('id', $data['lesson_ids'])->count() !== count($data['lesson_ids'])) {
            throw ValidationException::withMessages(['lesson_ids' => 'Урок должен принадлежать программе курса.']);
        }
        ResourceLessonLink::where('session_resource_id', $link->id)->delete();
        foreach ($data['lesson_ids'] as $id) {
            ResourceLessonLink::create(['session_resource_id' => $link->id, 'lesson_id' => $id, 'relation' => $data['relation'], 'origin' => 'manual']);
        }
    }

    private function addText(PlannerCourse $planner, array $input): array
    {
        $session = $this->session($planner, $input['session_id'] ?? null);
        $layer = $this->layer($input);
        $data = Validator::make($input, ['text' => 'required|string|max:10000'])->validate();
        $text = SessionText::create($data + ['session_id' => $session->id, 'layer' => $layer, 'sort_index' => $this->nextIndex($session->id, $layer), 'fact_state' => $layer === 'fact' ? 'unconfirmed' : null]);
        $this->invalidateFact($session, $layer);
        return ['text_id' => $text->id];
    }

    private function entry(PlannerCourse $planner, array $input): SessionResource|SessionText
    {
        Validator::make($input, ['kind' => 'required|in:resource,text', 'entry_id' => 'required|integer|min:1'])->validate();
        $model = $input['kind'] === 'resource' ? SessionResource::class : SessionText::class;
        return $model::whereIn('session_id', PlannerSession::where('planner_course_id', $planner->id)->select('id'))->whereKey($input['entry_id'])->firstOrFail();
    }

    private function sessionBonus(PlannerCourse $planner, array $input): array
    {
        $data = Validator::make($input, ['session_id' => 'required|integer|min:1', 'class_bonus_gc' => 'required|integer|min:0|max:10000'])->validate();
        $session = $this->session($planner, $data['session_id']);
        $entries = SessionResource::where('session_id', $session->id)->where('layer', 'plan')->get()
            ->filter(fn ($entry) => ($entry->resource_snapshot['provider_key'] ?? '') === 'codingprojects'
                && in_array($entry->resource_snapshot['type'] ?? '', ['task', 'step', 'lesson'], true));
        if ($entries->isEmpty()) throw ValidationException::withMessages(['session_id' => 'Сначала добавьте в занятие урок, ступень или задачи курса.']);
        foreach ($entries as $entry) $entry->update(['class_bonus_gc' => $data['class_bonus_gc']]);
        return ['updated_entries' => $entries->count()];
    }

    private function updateEntry(PlannerCourse $planner, array $input): array
    {
        $entry = $this->entry($planner, $input);
        $rules = ['fact_state' => 'sometimes|in:done,partial,not_done,unconfirmed'];
        $rules['class_bonus_gc'] = 'sometimes|integer|min:0|max:10000';
        $rules[$input['kind'] === 'text' ? 'text' : 'note'] = 'sometimes|nullable|string|max:10000';
        $data = Validator::make($input, $rules)->validate();
        if (array_key_exists('class_bonus_gc', $data)) {
            abort_unless($entry instanceof SessionResource && $entry->layer === 'plan' &&
                ($entry->resource_snapshot['provider_key'] ?? '') === 'codingprojects' &&
                in_array($entry->resource_snapshot['type'] ?? '', ['task','step','lesson'], true), 422, 'Бонус доступен для задач CodingProjects.');
        }
        if ($entry->layer !== 'fact') {
            unset($data['fact_state']);
        }
        if ($entry instanceof SessionText && array_key_exists('text', $data) && !filled($data['text'])) {
            throw ValidationException::withMessages(['text' => 'Запись не должна быть пустой.']);
        }
        $entry->update($data);
        $this->invalidateFact($this->session($planner, $entry->session_id), $entry->layer);
        return [];
    }

    private function deleteEntry(PlannerCourse $planner, array $input): array
    {
        $entry = $this->entry($planner, $input);
        $this->invalidateFact($this->session($planner, $entry->session_id), $entry->layer);
        if ($entry instanceof SessionResource && !empty($input['unified'])) {
            SessionResource::where('session_id', $entry->session_id)->where('resource_ref_id', $entry->resource_ref_id)->delete();
        } else {
            $entry->delete();
        }
        return [];
    }

    private function moveEntry(PlannerCourse $planner, array $input): array
    {
        $entry = $this->entry($planner, $input);
        $source = $this->session($planner, $entry->session_id);
        $target = $this->session($planner, $input['target_session_id'] ?? null);
        $unified = !empty($input['unified']);
        if ($entry instanceof SessionResource && $source->id !== $target->id && SessionResource::where('session_id', $target->id)->when(!$unified, fn ($q) => $q->where('layer', $entry->layer))
            ->where('resource_ref_id', $entry->resource_ref_id)->where('id', '!=', $entry->id)->exists()) {
            throw ValidationException::withMessages(['target_session_id' => 'Этот материал уже есть на занятии.']);
        }
        if ($entry instanceof SessionResource) {
            $this->checkLessonOverlap($target, $unified ? null : $entry->layer, $entry->resource_snapshot, $entry->id);
        }
        if ($entry instanceof SessionResource && $unified && $source->id !== $target->id) {
            SessionResource::where('session_id', $source->id)->where('resource_ref_id', $entry->resource_ref_id)->where('id', '!=', $entry->id)->update(['session_id' => $target->id]);
        }
        $entry->update(['session_id' => $target->id, 'sort_index' => $this->nextIndex($target->id, $unified ? null : $entry->layer)]);
        $entries = $this->orderedEntries($target->id, $unified ? null : $entry->layer)->reject(fn ($e) => $e['kind'] === $input['kind'] && $e['id'] === $entry->id)->values()->all();
        $position = count($entries);
        if (!empty($input['before'])) {
            foreach ($entries as $i => $candidate) {
                if ($candidate['kind'] === ($input['before']['kind'] ?? '') && $candidate['id'] === (int) ($input['before']['id'] ?? 0)) {
                    $position = $i;
                    break;
                }
            }
        }
        array_splice($entries, $position, 0, [['kind' => $input['kind'], 'id' => $entry->id]]);
        foreach ($entries as $i => $item) {
            $model = $item['kind'] === 'resource' ? SessionResource::class : SessionText::class;
            $query = $model::whereKey($item['id']);
            if ($unified && $item['kind'] === 'resource') {
                $refId = SessionResource::whereKey($item['id'])->value('resource_ref_id');
                $query = SessionResource::where('session_id', $target->id)->where('resource_ref_id', $refId);
            }
            $query->update(['sort_index' => ($i + 1) * 100]);
        }
        $this->invalidateFact($source, $entry->layer);
        $this->invalidateFact($target, $entry->layer);
        return ['session_id' => $target->id];
    }

    private function deadline(PlannerCourse $planner, Course $course, array $input): array
    {
        $data = Validator::make($input, ['resource_ref_id' => 'nullable|integer', 'due_on' => 'nullable|date_format:Y-m-d',
            'penalty' => 'nullable|numeric|min:0|max:1', 'title' => 'nullable|string|max:500', 'lesson_id' => 'nullable|integer',
            'deadline_id' => 'nullable|integer', 'task_id' => 'nullable|integer'])->validate();
        if (!empty($data['task_id'])) {
            $this->nativeDeadline($course, (int) $data['task_id'], $input, $data);
            return [];
        }
        if (!empty($data['resource_ref_id'])) {
            $ref = ResourceRef::whereKey($data['resource_ref_id'])->where(function ($query) use ($planner) {
                $query->whereIn('id', SessionResource::whereIn('session_id', PlannerSession::where('planner_course_id', $planner->id)->select('id'))->select('resource_ref_id'))
                    ->orWhereIn('id', PlannerDeadline::where('planner_course_id', $planner->id)->select('resource_ref_id'));
            })->firstOrFail();
            $providerKey = DB::table('planner_providers')->where('id', $ref->provider_id)->value('key');
            if ($providerKey === 'codingprojects') {
                abort_unless($ref->resource_type === 'task', 422);
                $this->nativeDeadline($course, (int) $ref->external_id, $input, $data);
                return [];
            }
            if (empty($data['due_on'])) {
                PlannerDeadline::where('planner_course_id', $planner->id)->where('resource_ref_id', $ref->id)->delete();
            } else {
                PlannerDeadline::updateOrCreate(['planner_course_id' => $planner->id, 'resource_ref_id' => $ref->id],
                    ['title' => $ref->title, 'due_on' => $data['due_on'], 'lesson_id' => $this->lessonId($course, $data['lesson_id'] ?? null)]);
            }
        } else {
            $id = $data['deadline_id'] ?? null;
            $deadline = $id ? PlannerDeadline::where('planner_course_id', $planner->id)->whereNull('resource_ref_id')->whereKey($id)->firstOrFail() : new PlannerDeadline(['planner_course_id' => $planner->id]);
            if (empty($data['due_on'])) {
                $deadline->delete();
            } else {
                if (!filled($data['title'] ?? null)) {
                    throw ValidationException::withMessages(['title' => 'Укажите название работы.']);
                }
                $deadline->fill(['title' => $data['title'], 'due_on' => $data['due_on'], 'lesson_id' => $this->lessonId($course, $data['lesson_id'] ?? null)])->save();
            }
        }
        return [];
    }

    private function lessonId(Course $course, mixed $id): ?int
    {
        return $id ? Lesson::where('program_id', $course->program_id)->whereKey($id)->firstOrFail()->id : null;
    }

    private function nativeDeadline(Course $course, int $taskId, array $input, array $legacy): void
    {
        $service = app(CourseDeadlineService::class);
        if (array_key_exists('mode', $input)) {
            $service->savePolicy($course, 'task', $taskId, [
                'revision' => $input['policy_revision'] ?? null, 'mode' => $input['mode'],
                'timezone' => $input['timezone'] ?? $service->timezone($course->id), 'stages' => $input['stages'] ?? [],
            ], auth()->id());
        } else {
            $service->set($course, Task::findOrFail($taskId), $legacy['due_on'] ?? null, $legacy['penalty'] ?? null);
        }
    }

    private function record(PlannerCourse $planner, array $input): array
    {
        $session = $this->session($planner, $input['session_id'] ?? null);
        $data = Validator::make($input, ['held_on' => 'required|date_format:Y-m-d', 'duration_minutes' => 'nullable|integer|min:1|max:1440',
            'note' => 'nullable|string|max:10000', 'status' => 'required|in:draft,confirmed', 'from_plan' => 'sometimes|boolean'])->validate();
        if ($data['status'] === 'confirmed' && $data['held_on'] > CarbonImmutable::now($planner->timezone)->toDateString()) {
            throw ValidationException::withMessages(['held_on' => 'Будущее занятие нельзя отметить проведённым.']);
        }
        $record = SessionRecord::firstOrNew(['session_id' => $session->id]);
        if (!$record->exists) {
            $record->plan_snapshot = ['session' => $session->toArray(), 'slot' => $session->calendar_slot_id ? CalendarSlot::find($session->calendar_slot_id)?->toArray() : null,
                'resources' => SessionResource::where('session_id', $session->id)->where('layer', 'plan')->get()->toArray(),
                'texts' => SessionText::where('session_id', $session->id)->where('layer', 'plan')->get()->toArray()];
        }
        if ($data['from_plan'] ?? false) {
            SessionResource::where('session_id', $session->id)->where('layer', 'fact')->delete();
            SessionText::where('session_id', $session->id)->where('layer', 'fact')->delete();
            foreach (SessionResource::where('session_id', $session->id)->where('layer', 'plan')->get() as $plan) {
                $copy = $plan->replicate();
                $copy->layer = 'fact';
                $copy->fact_state = $data['status'] === 'confirmed' ? 'done' : 'unconfirmed';
                $snap = $copy->resource_snapshot;
                $snap['deadline_snapshot'] = $this->deadlineSnapshot($planner, $plan);
                $snap['deadline_policy_snapshot'] = $this->deadlineChainSnapshot($planner, $plan);
                if ($data['status'] === 'confirmed') {
                    $snap['deadline_frozen_at'] = now()->toIso8601String();
                }
                $copy->resource_snapshot = $snap;
                $copy->save();
                foreach (ResourceLessonLink::where('session_resource_id', $plan->id)->get() as $relation) {
                    $relation->replicate()->fill(['session_resource_id' => $copy->id])->save();
                }
            }
            foreach (SessionText::where('session_id', $session->id)->where('layer', 'plan')->get() as $plan) {
                $plan->replicate()->fill(['layer' => 'fact', 'fact_state' => $data['status'] === 'confirmed' ? 'done' : 'unconfirmed'])->save();
            }
        }
        // Freeze on first confirmation; subsequent journal corrections preserve this value.
        foreach (SessionResource::where('session_id', $session->id)->where('layer', 'fact')->get() as $fact) {
            $snapshot = $fact->resource_snapshot;
            if (!array_key_exists('deadline_snapshot', $snapshot) || ($data['status'] === 'confirmed' && empty($snapshot['deadline_frozen_at']))) {
                $snapshot['deadline_snapshot'] = $this->deadlineSnapshot($planner, $fact);
                $snapshot['deadline_policy_snapshot'] = $this->deadlineChainSnapshot($planner, $fact);
                if ($data['status'] === 'confirmed') {
                    $snapshot['deadline_frozen_at'] = now()->toIso8601String();
                }
                $fact->update(['resource_snapshot' => $snapshot]);
            }
        }
        $record->fill(collect($data)->except('from_plan')->all());
        $record->timezone = $record->timezone ?: $planner->timezone;
        $record->confirmed_at = $data['status'] === 'confirmed' ? now() : null;
        $record->save();
        return ['record_id' => $record->id];
    }

    private function deadlineSnapshot(PlannerCourse $planner, SessionResource $link): ?string
    {
        $snap = $link->resource_snapshot;
        if (($snap['provider_key'] ?? '') === 'codingprojects' && ($snap['type'] ?? '') === 'task') {
            return Task::find($snap['external_id'])?->getDeadline($planner->course_id)?->expiration?->format('Y-m-d');
        }
        return PlannerDeadline::where('planner_course_id', $planner->id)->where('resource_ref_id', $link->resource_ref_id)->value('due_on');
    }

    private function deadlineChainSnapshot(PlannerCourse $planner, SessionResource $link): ?array
    {
        $snap = $link->resource_snapshot;
        if (($snap['provider_key'] ?? '') !== 'codingprojects' || ($snap['type'] ?? '') !== 'task') return null;
        $rule = Task::find($snap['external_id'])?->getDeadline($planner->course_id);
        return $rule ? ($rule instanceof \App\Support\DeadlineRule ? $rule : \App\Support\DeadlineRule::fromLegacy($rule))->snapshot() : null;
    }

    private function invalidateFact(PlannerSession $session, string $layer): void
    {
        if ($layer === 'fact') {
            SessionRecord::where('session_id', $session->id)->update(['status' => 'draft', 'confirmed_at' => null]);
        }
    }

    private function nextIndex(int $sessionId, ?string $layer): int
    {
        return max((int) SessionResource::where('session_id', $sessionId)->when($layer, fn ($q) => $q->where('layer', $layer))->max('sort_index'),
            (int) SessionText::where('session_id', $sessionId)->when($layer, fn ($q) => $q->where('layer', $layer))->max('sort_index')) + 100;
    }

    private function orderedEntries(int $sessionId, ?string $layer): \Illuminate\Support\Collection
    {
        $entries = SessionResource::where('session_id', $sessionId)->when($layer, fn ($q) => $q->where('layer', $layer))->get()
            ->map(fn ($e) => ['kind' => 'resource', 'id' => $e->id, 'resource_ref_id' => $e->resource_ref_id, 'layer' => $e->layer, 'sort_index' => $e->sort_index])
            ->concat(SessionText::where('session_id', $sessionId)->when($layer, fn ($q) => $q->where('layer', $layer))->get()
                ->map(fn ($e) => ['kind' => 'text', 'id' => $e->id, 'layer' => $e->layer, 'sort_index' => $e->sort_index]));
        if ($layer === null) {
            $entries = $entries->sortBy(fn ($entry) => $entry['layer'] === 'plan' ? 0 : 1)
                ->unique(fn ($entry) => $entry['kind'] === 'resource' ? 'resource:'.$entry['resource_ref_id'] : 'text:'.$entry['id']);
        }
        return $entries->sortBy('sort_index')->values();
    }

    private function removeException(PlannerCourse $planner, array $input): array
    {
        CalendarException::where('planner_course_id', $planner->id)->whereKey($input['exception_id'] ?? null)->firstOrFail()->delete();
        return $this->calendar->refresh($planner);
    }
}
