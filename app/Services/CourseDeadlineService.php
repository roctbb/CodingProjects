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
        else unset($this->policies[$courseId]);
    }

    private function policies(int $courseId)
    {
        return $this->policies[$courseId] ??= DeadlinePolicy::with('stages')->where('course_id', $courseId)->get()->keyBy(fn ($p) => $p->target_type.':'.$p->target_id);
    }

    public function forTask(Task $task, int $courseId): ?TaskDeadline
    {
        if ($this->available()) {
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

    public function forLesson(Lesson $lesson, int $courseId): ?DeadlineRule
    {
        if (!$this->available()) return null;
        $policy = $this->policies($courseId)->get('lesson:'.$lesson->id);
        return $policy?->mode === 'custom' ? DeadlineRule::fromPolicy($policy) : null;
    }

    public function entries(Course $course, array $with = []): \Illuminate\Support\Collection
    {
        $tasks = Task::with(array_merge(['deadlines', 'step.lesson'], $with))
            ->whereHas('step.lesson', fn ($q) => $q->where('program_id', $course->program_id))->get();
        return $tasks->flatMap(function ($task) use ($course) {
            $deadline = $this->forTask($task, $course->id);
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

    public function timezone(int $courseId): string
    {
        return $this->timezones[$courseId] ??= Schema::hasTable('planner_courses')
            ? (DB::table('planner_courses')->where('course_id', $courseId)->value('timezone') ?: config('app.timezone')) : config('app.timezone');
    }

    public function editor(Course $course, string $type, int $id, Lesson|Task|null $target = null): array
    {
        $policy = $this->available() ? $this->policies($course->id)->get($type.':'.$id) : null;
        $timezone = $policy?->timezone ?: $this->timezone($course->id);
        $rule = $policy?->mode === 'custom' ? DeadlineRule::fromPolicy($policy) : null;
        $stages = [];
        if (!$policy) {
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
        return ['mode' => $policy?->mode ?? ($stages ? 'custom' : ($type === 'task' ? 'inherit' : 'none')),
            'revision' => $policy?->revision ?? 0, 'timezone' => $timezone, 'stages' => $stages];
    }

    public function savePolicy(Course $course, string $type, int $id, array $input, ?int $actorId = null, bool $legacy = false): DeadlinePolicy
    {
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
        if ($type === 'lesson' && $data['mode'] === 'inherit') throw \Illuminate\Validation\ValidationException::withMessages(['mode' => 'Для урока выберите свои сроки или отсутствие дедлайна.']);
        $normalized = collect($data['mode'] === 'custom' ? $data['stages'] : [])->map(fn ($s) => [
            // The selected day is inclusive. Advance in local calendar time before converting to UTC (DST-safe).
            'cutoff_at' => Carbon::createFromFormat('!Y-m-d', $s['deadline_date'], $data['timezone'])->addDay()->utc()->format('Y-m-d H:i:s'),
            'retained_percent' => (float) $s['retained_percent'],
        ])->sortBy('cutoff_at')->values();
        if ($normalized->unique('cutoff_at')->count() !== $normalized->count()) throw \Illuminate\Validation\ValidationException::withMessages(['stages' => 'Выберите разные даты сроков.']);
        return DB::transaction(function () use ($course, $type, $id, $data, $actorId, $legacy, $normalized) {
            // The course lock also serializes concurrent first writes of a policy.
            Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            $policy = DeadlinePolicy::where('course_id', $course->id)->where('target_type', $type)->where('target_id', $id)->lockForUpdate()->first();
            if (($policy?->revision ?? 0) !== (int) $data['revision']) throw new ConflictHttpException('Сроки изменены в другом окне. Обновите страницу.');
            $policy ??= new DeadlinePolicy(['course_id' => $course->id, 'target_type' => $type, 'target_id' => $id]);
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
                DeadlinePolicy::where('course_id', $course->id)->where('target_type', 'task')->whereIn('target_id', $taskIds)->update(['mode' => 'inherit', 'revision' => DB::raw('revision + 1')]);
                TaskDeadline::where('course_id', $course->id)->whereIn('task_id', $taskIds)->delete();
            }
            if ($type === 'task') TaskDeadline::where('course_id', $course->id)->where('task_id', $id)->delete();
            $this->forget($course->id);
            $policy->load('stages');
            app(DeadlineNotifications::class)->policyChanged($policy, $created);
            return $policy;
        });
    }

    public function set(Course $course, Task $task, ?string $date, mixed $penalty = null): void
    {
        abort_unless($task->step && $task->step->lesson && (int) $task->step->lesson->program_id === (int) $course->program_id, 404);
        Validator::make(['date' => $date, 'penalty' => $penalty], ['date' => 'nullable|date_format:Y-m-d', 'penalty' => 'nullable|numeric|min:0|max:1'])->validate();
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
