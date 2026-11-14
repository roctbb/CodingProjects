<?php

namespace App\CoursePlanner\Services;

use App\CoursePlanner\Models\CalendarException;
use App\CoursePlanner\Models\CalendarSlot;
use App\CoursePlanner\Models\PlannerCourse;
use App\CoursePlanner\Models\PlannerSession;
use App\CoursePlanner\Models\ScheduleRule;
use App\CoursePlanner\Models\SessionRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CalendarService
{
    public function hasEnded(PlannerCourse $planner, ?CalendarSlot $slot, ?SessionRecord $record = null): bool
    {
        $day = $record?->getRawOriginal('held_on') ?: $slot?->getRawOriginal('local_date');
        if (!$day) return false;
        $now = CarbonImmutable::now($planner->timezone);
        if (substr($day, 0, 10) < $now->toDateString()) return true;
        if (substr($day, 0, 10) > $now->toDateString()) return false;
        if ($record?->status === 'confirmed') return true;
        if (!$slot?->start_time) return false;
        return CarbonImmutable::parse(substr($day, 0, 10).' '.$slot->start_time, $planner->timezone)
            ->addMinutes($record?->duration_minutes ?: $slot->duration_minutes)->lessThanOrEqualTo($now);
    }

    public function settings(array $input): array
    {
        $data = Validator::make($input, [
            'starts_on' => 'required|date_format:Y-m-d',
            'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on',
            'timezone' => 'required|timezone',
            'default_duration_minutes' => 'required|integer|min:5|max:480',
            'rules' => 'required|array|min:1|max:42',
            'rules.*.rule_key' => 'nullable|string|max:64|regex:/^[a-zA-Z0-9_-]+$/',
            'rules.*.weekday' => 'required|integer|min:1|max:7',
            'rules.*.start_time' => 'nullable|date_format:H:i',
            'rules.*.duration_minutes' => 'required|integer|min:5|max:480',
            'rules.*.valid_from' => 'nullable|date_format:Y-m-d',
            'rules.*.valid_until' => 'nullable|date_format:Y-m-d',
        ])->validate();
        if (CarbonImmutable::parse($data['starts_on'])->diffInDays($data['ends_on']) > 1096) {
            throw ValidationException::withMessages(['ends_on' => 'Период не должен превышать три года.']);
        }
        $keys = [];
        foreach ($data['rules'] as $i => &$rule) {
            $rule = collect($rule)->only(['rule_key', 'weekday', 'start_time', 'duration_minutes', 'valid_from', 'valid_until'])->all();
            $rule['weekday'] = (int) $rule['weekday'];
            $rule['duration_minutes'] = (int) $rule['duration_minutes'];
            $rule['rule_key'] = $rule['rule_key'] ?? (string) Str::uuid();
            $rule['position'] = $i;
            $rule['start_time'] = $rule['start_time'] ?? null;
            $rule['valid_from'] = $rule['valid_from'] ?? null;
            $rule['valid_until'] = $rule['valid_until'] ?? null;
            if (isset($keys[$rule['rule_key']])) {
                throw ValidationException::withMessages(['rules' => 'Ключи недельных позиций должны различаться.']);
            }
            $keys[$rule['rule_key']] = true;
            $from = max($rule['valid_from'] ?? $data['starts_on'], $data['starts_on']);
            $until = min($rule['valid_until'] ?? $data['ends_on'], $data['ends_on']);
            if ($from > $until || ($rule['start_time'] && $this->minutes($rule['start_time']) + $rule['duration_minutes'] > 1440)) {
                throw ValidationException::withMessages(['rules' => 'Проверьте период и время недельной позиции.']);
            }
        }
        unset($rule);
        foreach ($data['rules'] as $i => $a) {
            foreach (array_slice($data['rules'], $i + 1) as $b) {
                if ($a['weekday'] !== $b['weekday'] || !$a['start_time'] || !$b['start_time']) {
                    continue;
                }
                $overlapDates = max($a['valid_from'] ?? $data['starts_on'], $b['valid_from'] ?? $data['starts_on'])
                    <= min($a['valid_until'] ?? $data['ends_on'], $b['valid_until'] ?? $data['ends_on']);
                if ($overlapDates && $this->minutes($a['start_time']) < $this->minutes($b['start_time']) + $b['duration_minutes']
                    && $this->minutes($b['start_time']) < $this->minutes($a['start_time']) + $a['duration_minutes']) {
                    throw ValidationException::withMessages(['rules' => 'Недельные позиции пересекаются по времени.']);
                }
            }
        }
        return $data;
    }

    public function preview(PlannerCourse $planner, array $input): array
    {
        $data = $this->settings($input);
        $candidates = $this->candidates($planner, $data);
        $current = CalendarSlot::where('planner_course_id', $planner->id)->where('origin', 'rule')->get()->keyBy('occurrence_key');
        $affected = [];
        $added = 0;
        foreach ($candidates as $key => $slot) {
            if (!$current->has($key) && $slot['state'] === 'available') {
                $added++;
            }
        }
        $sessions = PlannerSession::where('planner_course_id', $planner->id)->whereNotNull('calendar_slot_id')->get()->keyBy('calendar_slot_id');
        foreach ($current as $key => $slot) {
            if ((!isset($candidates[$key]) || $candidates[$key]['state'] !== 'available') && $sessions->has($slot->id)) {
                $session = $sessions[$slot->id];
                $affected[] = ['id' => $session->id, 'title' => $session->title, 'date' => $slot->local_date,
                    'preserved' => $session->pinned || filled($session->comment) || SessionRecord::where('session_id', $session->id)->exists()];
            }
        }
        return ['settings' => $data, 'available' => count(array_filter($candidates, fn ($s) => $s['state'] === 'available')),
            'excluded' => count(array_filter($candidates, fn ($s) => $s['state'] === 'excluded')),
            'added' => $added, 'affected_sessions' => $affected];
    }

    public function rebuild(PlannerCourse $planner, array $input): array
    {
        $data = $this->settings($input);
        $planner->fill(collect($data)->except('rules')->all())->save();
        ScheduleRule::where('planner_course_id', $planner->id)->update(['active' => false]);
        foreach ($data['rules'] as $rule) {
            ScheduleRule::updateOrCreate(['planner_course_id' => $planner->id, 'rule_key' => $rule['rule_key']], $rule + ['active' => true]);
        }
        $candidates = $this->candidates($planner, $data);
        $slots = CalendarSlot::where('planner_course_id', $planner->id)->get()->keyBy('occurrence_key');
        $moved = [];
        foreach ($candidates as $key => $slotData) {
            $slot = $slots->get($key) ?? new CalendarSlot(['planner_course_id' => $planner->id, 'occurrence_key' => $key]);
            $slot->fill($slotData);
            if ($slot->isDirty()) {
                $slot->save();
            }
            $this->placeOrDetach($planner, $slot, $moved);
        }
        foreach ($slots as $key => $slot) {
            if ($slot->origin === 'rule' && !isset($candidates[$key])) {
                $slot->update(['state' => 'retired', 'reason' => 'Позиция больше не входит в расписание']);
                $this->placeOrDetach($planner, $slot, $moved);
            }
            if ($slot->origin === 'manual') {
                $exception = $this->exceptionFor($planner, $slot->local_date, null);
                $slot->update(['state' => $exception ? 'excluded' : 'available', 'reason' => $exception?->reason]);
                $this->placeOrDetach($planner, $slot, $moved);
            }
        }
        return ['backlog_session_ids' => $moved];
    }

    public function currentSettings(PlannerCourse $planner): array
    {
        return ['starts_on' => $planner->starts_on, 'ends_on' => $planner->ends_on, 'timezone' => $planner->timezone,
            'default_duration_minutes' => $planner->default_duration_minutes,
            'rules' => ScheduleRule::where('planner_course_id', $planner->id)->where('active', true)->orderBy('position')->get()
                ->map(fn ($r) => $r->only(['rule_key', 'weekday', 'start_time', 'duration_minutes', 'valid_from', 'valid_until']))->all()];
    }

    public function exception(PlannerCourse $planner, array $input): array
    {
        $data = Validator::make($input, ['starts_on' => 'required|date_format:Y-m-d',
            'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on', 'rule_key' => 'nullable|string|max:64',
            'reason' => 'required|string|max:500'])->validate();
        if (!empty($data['rule_key']) && !ScheduleRule::where('planner_course_id', $planner->id)->where('rule_key', $data['rule_key'])->exists()) {
            throw ValidationException::withMessages(['rule_key' => 'Недельная позиция не найдена.']);
        }
        $exception = CalendarException::create($data + ['planner_course_id' => $planner->id]);
        return ['exception_id' => $exception->id] + $this->rebuild($planner, $this->currentSettings($planner));
    }

    public function manualSlot(PlannerCourse $planner, array $input): array
    {
        $data = Validator::make($input, ['date' => 'required|date_format:Y-m-d', 'start_time' => 'nullable|date_format:H:i',
            'duration_minutes' => 'required|integer|min:5|max:480', 'title' => 'nullable|string|max:255'])->validate();
        $start = $data['start_time'] ?? null;
        if ($start) {
            $minutes = $this->minutes($start);
            if ($minutes + $data['duration_minutes'] > 1440) {
                throw ValidationException::withMessages(['start_time' => 'Занятие выходит за пределы дня.']);
            }
            foreach (CalendarSlot::where('planner_course_id', $planner->id)->where('local_date', $data['date'])->where('state', 'available')->get() as $existing) {
                if ($existing->start_time && $minutes < $this->minutes($existing->start_time) + $existing->duration_minutes
                    && $this->minutes($existing->start_time) < $minutes + $data['duration_minutes']) {
                    throw ValidationException::withMessages(['start_time' => 'На это время уже есть занятие.']);
                }
            }
        }
        $slot = CalendarSlot::create(['planner_course_id' => $planner->id, 'local_date' => $data['date'], 'start_time' => $start,
            'duration_minutes' => $data['duration_minutes'], 'position' => 1000, 'origin' => 'manual',
            'occurrence_key' => 'manual:'.Str::uuid(), 'state' => 'available']);
        $session = PlannerSession::create(['planner_course_id' => $planner->id, 'calendar_slot_id' => $slot->id,
            'title' => $data['title'] ?? 'Дополнительное занятие']);
        return ['session_id' => $session->id];
    }

    private function candidates(PlannerCourse $planner, array $data): array
    {
        $result = [];
        $exceptions = CalendarException::where('planner_course_id', $planner->id)->get();
        for ($day = CarbonImmutable::parse($data['starts_on'], $data['timezone']); $day->toDateString() <= $data['ends_on']; $day = $day->addDay()) {
            $date = $day->toDateString();
            foreach ($data['rules'] as $rule) {
                if ((int) $rule['weekday'] !== $day->isoWeekday() || $date < ($rule['valid_from'] ?? $data['starts_on'])
                    || $date > ($rule['valid_until'] ?? $data['ends_on'])) {
                    continue;
                }
                $exception = $exceptions->first(fn ($e) => $e->starts_on <= $date && $e->ends_on >= $date && (!$e->rule_key || $e->rule_key === $rule['rule_key']));
                $result[$rule['rule_key'].':'.$date] = ['local_date' => $date, 'start_time' => $rule['start_time'],
                    'duration_minutes' => $rule['duration_minutes'], 'position' => $rule['position'], 'rule_key' => $rule['rule_key'],
                    'origin' => 'rule', 'state' => $exception ? 'excluded' : 'available', 'reason' => $exception?->reason];
            }
        }
        return $result;
    }

    private function placeOrDetach(PlannerCourse $planner, CalendarSlot $slot, array &$moved): void
    {
        $session = PlannerSession::where('calendar_slot_id', $slot->id)->first();
        if ($slot->state === 'available') {
            if (!$session) {
                PlannerSession::create(['planner_course_id' => $planner->id, 'calendar_slot_id' => $slot->id]);
            }
            return;
        }
        if (!$session || $session->pinned || filled($session->comment) || SessionRecord::where('session_id', $session->id)->exists()) {
            return;
        }
        $hasContent = DB::table('planner_session_resources')->where('session_id', $session->id)->exists()
            || DB::table('planner_session_texts')->where('session_id', $session->id)->exists() || filled($session->plan_note)
            || $session->title !== 'Занятие';
        if ($hasContent) {
            $session->update(['calendar_slot_id' => null]);
            $moved[] = $session->id;
        } else {
            $session->delete();
        }
    }

    private function exceptionFor(PlannerCourse $planner, string $date, ?string $ruleKey): ?CalendarException
    {
        return CalendarException::where('planner_course_id', $planner->id)->where('starts_on', '<=', $date)->where('ends_on', '>=', $date)
            ->where(fn ($q) => $q->whereNull('rule_key')->when($ruleKey, fn ($q) => $q->orWhere('rule_key', $ruleKey)))->first();
    }

    private function minutes(string $time): int
    {
        [$h, $m] = explode(':', $time);
        return (int) $h * 60 + (int) $m;
    }
}
