<?php

namespace App\Services;

use App\Course;
use App\Task;
use App\TaskDeadline;
use App\DeadlinePolicy;
use App\Lesson;
use App\Support\DeadlineRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CourseDeadlineService
{
    private array $policies = [];
    private ?bool $available = null;
    private array $timezones = [];

    public function available(): bool
    {
        return $this->available ??= Schema::hasTable('deadline_policies');
    }

    public function forget(?int $courseId = null): void
    {
        if ($courseId === null) $this->policies = [];
        else foreach (array_keys($this->policies) as $key) if (str_starts_with($key, $courseId.':')) unset($this->policies[$key]);
    }

    public function audience(int $courseId, ?int $userId = null, ?\Carbon\CarbonInterface $at = null): int
    {
        if (!app(CourseSubgroups::class)->available()) return 0;
        if ($userId !== null) return app(CourseSubgroups::class)->groupFor($courseId, $userId, $at) ?? 0;
        if (!auth()->user()) return 0;
        return \App\Support\CourseScope::for(Course::findOrFail($courseId))->audienceId();
    }

    private function policies(int $courseId, int $audience = 0)
    {
        return $this->policies[$courseId.':'.$audience] ??= DeadlinePolicy::with('stages')->where('course_id', $courseId)
            ->when(Schema::hasColumn('deadline_policies', 'audience_id'), fn ($q) => $q->where('audience_id', $audience))
            ->get()->keyBy(fn ($p) => $p->target_type.':'.$p->target_id);
    }

    public function forTask(Task $task, int $courseId, ?int $audience = null): ?TaskDeadline
    {
        if ($this->available()) {
            $audience ??= $this->audience($courseId);
            if ($audience) {
                $scoped = $this->policies($courseId, $audience);
                $own = $scoped->get('task:'.$task->id);
                if ($own && $own->mode !== 'inherit') return $own->mode === 'custom' ? DeadlineRule::fromPolicy($own) : null;
                $lesson = $task->step?->lesson;
                $parent = !$task->is_star && $lesson ? $scoped->get('lesson:'.$lesson->id) : null;
                if ($parent && $parent->mode !== 'inherit') return $parent->mode === 'custom' ? DeadlineRule::fromPolicy($parent) : null;
            }
            $policies = $this->policies($courseId);
            $own = $policies->get('task:'.$task->id);
            if ($own && $own->mode !== 'inherit') return $own->mode === 'custom' ? DeadlineRule::fromPolicy($own) : null;
            $lesson = $task->step?->lesson;
            if (!$task->is_star && $lesson) {
                $parent = $policies->get('lesson:'.$lesson->id);
                if ($parent) return $parent->mode === 'custom' ? DeadlineRule::fromPolicy($parent) : null;
            }
            // An explicit inherit overrides the compatibility row too.
            if ($own) return null;
        }
        return $task->relationLoaded('deadlines') ? $task->deadlines->firstWhere('course_id', $courseId)
            : $task->deadlines()->where('course_id', $courseId)->first();
    }

    public function forLesson(Lesson $lesson, int $courseId, ?int $audience = null): ?DeadlineRule
    {
        if (!$this->available()) return null;
        $audience ??= $this->audience($courseId);
        if ($audience) {
            $scoped = $this->policies($courseId, $audience)->get('lesson:'.$lesson->id);
            if ($scoped && $scoped->mode !== 'inherit') return $scoped->mode === 'custom' ? DeadlineRule::fromPolicy($scoped) : null;
        }
        $policy = $this->policies($courseId)->get('lesson:'.$lesson->id);
        return $policy?->mode === 'custom' ? DeadlineRule::fromPolicy($policy) : null;
    }

    public function entries(Course $course, array $with = [], ?int $audience = null): \Illuminate\Support\Collection
    {
        $tasks = Task::with(array_merge(['deadlines', 'step.lesson'], $with))
            ->whereHas('step.lesson', fn ($q) => $q->where('program_id', $course->program_id))->get();
        return $tasks->flatMap(function ($task) use ($course, $audience) {
            $deadline = $this->forTask($task, $course->id, $audience);
            if (!$deadline) return [];
            $rule = $deadline instanceof DeadlineRule ? $deadline : DeadlineRule::fromLegacy($deadline);
            return collect($rule->series)->map(function ($stage) use ($rule, $task, $course) {
                $entry = $rule->displayStage($stage);
                $entry->course_id = $course->id; $entry->task_id = $task->id;
                $entry->setRelation('task', $task); $entry->setRelation('course', $course);
                return $entry;
            });
        })->sortBy(fn ($d) => $d->cutoffAt()->timestamp)->values();
    }

    public function timezone(int $courseId, int $audience = 0): string
    {
        return $this->timezones[$courseId.':'.$audience] ??= Schema::hasTable('planner_courses')
            ? (DB::table('planner_courses')->where('course_id', $courseId)
                ->when(Schema::hasColumn('planner_courses', 'audience_id'), fn ($q) => $q->where('audience_id', $audience))->value('timezone')
                ?: ($audience ? $this->timezone($courseId) : config('app.timezone'))) : config('app.timezone');
    }

    public function editor(Course $course, string $type, int $id, Lesson|Task|null $target = null, ?int $audience = null): array
    {
        $audience ??= $this->audience($course->id);
        $policy = $this->available() ? $this->policies($course->id, $audience)->get($type.':'.$id) : null;
        $timezone = $policy?->timezone ?: $this->timezone($course->id, $audience);
        $rule = $policy?->mode === 'custom' ? DeadlineRule::fromPolicy($policy) : null;
        $stages = [];
        if (!$policy && !$audience) {
            $legacy = $type === 'task' ? TaskDeadline::where('course_id', $course->id)->where('task_id', $id)->first()
                : ($target instanceof Lesson && $target->id === $id ? $target : Lesson::find($id))?->sharedDeadline($course->id);
            if ($legacy?->expiration) $rule = $legacy instanceof DeadlineRule ? $legacy : DeadlineRule::fromLegacy($legacy);
        }
        if ($rule) {
            $timezone = $rule->ruleTimezone;
            $stages = collect($rule->series)->map(fn ($s) => [
                'deadline_date' => $rule->deadlineAt($s)->format('Y-m-d'), 'retained_percent' => $s['percent'],
            ])->all();
        }
        return ['mode' => $policy?->mode ?? ($audience ? 'inherit' : ($stages ? 'custom' : ($type === 'task' ? 'inherit' : 'none'))),
            'revision' => $policy?->revision ?? 0, 'timezone' => $timezone, 'stages' => $stages, 'audience_id' => $audience];
    }

    public function savePolicy(Course $course, string $type, int $id, array $input, ?int $actorId = null, bool $legacy = false, ?int $audience = null): DeadlinePolicy
    {
        $audience ??= $this->audience($course->id);
        if ($audience) $course->subgroups()->findOrFail($audience);
        abort_unless($this->available(), 503, 'Сначала примените миграции дедлайнов.');
        abort_unless(in_array($type, ['lesson', 'task'], true), 404);
        $target = $type === 'lesson' ? Lesson::findOrFail($id) : Task::with('step.lesson')->findOrFail($id);
        abort_unless((int) ($type === 'lesson' ? $target->program_id : $target->step?->lesson?->program_id) === (int) $course->program_id, 404);
        $data = Validator::make($input, [
            'revision' => 'required|integer|min:0', 'mode' => 'required|in:custom,none,inherit',
            'timezone' => 'required|timezone', 'stages' => 'present|array',
            'stages.*.deadline_date' => 'required|date_format:Y-m-d',
            'stages.*.retained_percent' => 'required|numeric|min:0|max:100', 'apply_to_tasks' => 'sometimes|boolean',
        ])->validate();
        if ($data['mode'] === 'custom' && !$data['stages']) throw \Illuminate\Validation\ValidationException::withMessages(['stages' => 'Добавьте хотя бы один срок.']);
        if (!$audience && $type === 'lesson' && $data['mode'] === 'inherit') throw \Illuminate\Validation\ValidationException::withMessages(['mode' => 'Для урока выберите свои сроки или отсутствие дедлайна.']);
        $normalized = collect($data['mode'] === 'custom' ? $data['stages'] : [])->map(fn ($s) => [
            // The selected day is inclusive. Advance in local calendar time before converting to UTC (DST-safe).
            'cutoff_at' => Carbon::createFromFormat('!Y-m-d', $s['deadline_date'], $data['timezone'])->addDay()->utc()->format('Y-m-d H:i:s'),
            'retained_percent' => (float) $s['retained_percent'],
        ])->sortBy('cutoff_at')->values();
        if ($normalized->unique('cutoff_at')->count() !== $normalized->count()) throw \Illuminate\Validation\ValidationException::withMessages(['stages' => 'Выберите разные даты сроков.']);
        return DB::transaction(function () use ($course, $type, $id, $data, $actorId, $legacy, $normalized, $audience) {
            // The course lock also serializes concurrent first writes of a policy.
            Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            $hasAudience = Schema::hasColumn('deadline_policies', 'audience_id');
            $policy = DeadlinePolicy::where('course_id', $course->id)->where('target_type', $type)->where('target_id', $id)
                ->when($hasAudience, fn ($q) => $q->where('audience_id', $audience))->lockForUpdate()->first();
            if (($policy?->revision ?? 0) !== (int) $data['revision']) throw new ConflictHttpException('Сроки изменены в другом окне. Обновите страницу.');
            $policy ??= new DeadlinePolicy(['course_id' => $course->id, 'target_type' => $type, 'target_id' => $id] + ($hasAudience ? ['audience_id' => $audience] : []));
            $policyAudience = (int) ($policy->audience_id ?? 0);
            $taskIds = $type === 'lesson' && !empty($data['apply_to_tasks'])
                ? Task::whereHas('step', fn ($q) => $q->where('lesson_id', $id))->where('is_star', false)->pluck('id') : collect();
            $hasOverrides = $taskIds->isNotEmpty() && (DeadlinePolicy::where('course_id', $course->id)
                ->where('target_type', 'task')->whereIn('target_id', $taskIds)->where('mode', '!=', 'inherit')
                ->when(Schema::hasColumn('deadline_policies', 'audience_id'), fn ($q) => $q->where('audience_id', $policyAudience))->exists()
                || (!$policyAudience && TaskDeadline::where('course_id', $course->id)->whereIn('task_id', $taskIds)->exists()));
            if ($policy->exists && $policy->mode === $data['mode'] && $policy->timezone === $data['timezone'] && $policy->legacy === $legacy
                && $policy->stages->map(fn ($s) => ['cutoff_at' => $s->cutoff_at, 'retained_percent' => (float) $s->retained_percent])->all() === $normalized->all()
                && !$hasOverrides) return $policy;
            $created = $policy->mode !== 'custom';
            $policy->fill(['mode' => $data['mode'], 'timezone' => $data['timezone'], 'revision' => ($policy->revision ?? 0) + 1, 'updated_by' => $actorId, 'legacy' => $legacy])->save();
            $policy->stages()->delete();
            foreach ($normalized as $position => $stage) $policy->stages()->create($stage + ['position' => $position]);
            if ($type === 'lesson' && !empty($data['apply_to_tasks'])) {
                DeadlinePolicy::where('course_id', $course->id)->where('target_type', 'task')->whereIn('target_id', $taskIds)
                    ->when($hasAudience, fn ($q) => $q->where('audience_id', $audience))->update(['mode' => 'inherit', 'revision' => DB::raw('revision + 1')]);
                if (!$audience) TaskDeadline::where('course_id', $course->id)->whereIn('task_id', $taskIds)->delete();
            }
            if ($type === 'task' && !$audience) TaskDeadline::where('course_id', $course->id)->where('task_id', $id)->delete();
            $this->forget($course->id);
            $policy->load('stages');
            app(DeadlineNotifications::class)->policyChanged($policy, $created);
            return $policy;
        });
    }

    public function set(Course $course, Task $task, ?string $date, mixed $penalty = null, ?int $audience = null): void
    {
        abort_unless($task->step && $task->step->lesson && (int) $task->step->lesson->program_id === (int) $course->program_id, 404);
        Validator::make(['date' => $date, 'penalty' => $penalty], ['date' => 'nullable|date_format:Y-m-d', 'penalty' => 'nullable|numeric|min:0|max:1'])->validate();
        if ($audience ??= $this->audience($course->id)) {
            $editor = $this->editor($course, 'task', $task->id, $task, $audience);
            $this->savePolicy($course, 'task', $task->id, ['revision' => $editor['revision'], 'mode' => $date ? 'custom' : 'none',
                'timezone' => $editor['timezone'], 'stages' => $date ? [['deadline_date' => $date, 'retained_percent' => (float) ($penalty ?? 0) * 100]] : []], auth()->id(), false, $audience);
            return;
        }
        DB::transaction(function () use ($course, $task, $date, $penalty) {
            $penalty ??= TaskDeadline::where('course_id', $course->id)->where('task_id', $task->id)->value('penalty') ?? 0;
            if ($this->available()) {
                $currentPolicy = $this->policies($course->id)->get('task:'.$task->id);
                $currentRow = TaskDeadline::where('course_id', $course->id)->where('task_id', $task->id)->first();
                $this->savePolicy($course, 'task', $task->id, ['revision' => $currentPolicy?->revision ?? 0,
                    'mode' => $date ? 'custom' : 'none', 'timezone' => $this->timezone($course->id),
                    'stages' => $date ? [['deadline_date' => $date,
                        'retained_percent' => (int) round(($penalty ?? $currentRow?->penalty ?? 0) * 100)]] : []], auth()->id());
            }
            if (!$date) {
                TaskDeadline::where('course_id', $course->id)->where('task_id', $task->id)->delete();
                return;
            }
            $current = TaskDeadline::where('course_id', $course->id)->where('task_id', $task->id)->first();
            $values = ['expiration' => $date, 'penalty' => $penalty ?? $current?->penalty ?? 0];
            ($current ?? new TaskDeadline(['course_id' => $course->id, 'task_id' => $task->id]))->fill($values)->save();
        });
    }
}
