<?php
namespace App\Support;

use App\DeadlinePolicy;
use App\TaskDeadline;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/** A read-only adapter for legacy deadline consumers and frozen submission rules. */
class DeadlineRule extends TaskDeadline
{
    public array $series = [];
    public string $ruleTimezone = 'Europe/Moscow';
    public bool $legacyBoundary = false;
    public ?int $policyId = null;
    public int $policyRevision = 0;
    public ?string $targetType = null;
    public ?int $targetId = null;
    private ?Carbon $cutoff = null;

    public static function fromPolicy(DeadlinePolicy $policy): self
    {
        return self::fromSnapshot(['timezone' => $policy->timezone, 'policy_id' => $policy->id, 'revision' => $policy->revision,
            'legacy' => $policy->legacy, 'target_type' => $policy->target_type, 'target_id' => $policy->target_id,
            'stages' => $policy->stages->map(fn ($s) => ['id' => $s->id, 'at' => $s->cutoff_at, 'percent' => $s->retained_percent])->all()]);
    }

    public static function fromLegacy(TaskDeadline $deadline): self
    {
        return self::fromSnapshot(['legacy' => true, 'stages' => [['at' => $deadline->expiration->copy()->addDay()->utc()->format('Y-m-d H:i:s'),
            'percent' => (float) $deadline->penalty * 100]]]);
    }

    public static function fromSnapshot(array $snapshot): self
    {
        $rule = new self();
        $rule->series = $snapshot['stages'] ?? [];
        $rule->ruleTimezone = $snapshot['timezone'] ?? 'Europe/Moscow';
        $rule->legacyBoundary = $snapshot['legacy'] ?? false;
        $rule->policyId = $snapshot['policy_id'] ?? null; $rule->policyRevision = $snapshot['revision'] ?? 0;
        $rule->targetType = $snapshot['target_type'] ?? null; $rule->targetId = $snapshot['target_id'] ?? null;
        $display = collect($rule->series)->first(fn ($s) => Carbon::parse($s['at'], 'UTC')->gt(now())) ?? end($rule->series);
        if ($display) {
            $rule->cutoff = Carbon::parse($display['at'], 'UTC');
            $rule->expiration = $rule->deadlineAt();
            $rule->penalty = $display['percent'] / 100;
        }
        return $rule;
    }

    public function snapshot(): array
    {
        return ['timezone' => $this->ruleTimezone, 'policy_id' => $this->policyId, 'revision' => $this->policyRevision,
            'legacy' => $this->legacyBoundary, 'target_type' => $this->targetType, 'target_id' => $this->targetId, 'stages' => $this->series];
    }

    public function cutoffAt(): ?Carbon { return $this->cutoff?->copy(); }

    /** Display an inclusive day-end deadline; retain arbitrary times in existing frozen rules. */
    public function deadlineAt(?array $stage = null): ?Carbon
    {
        $at = $stage ? Carbon::parse($stage['at'], 'UTC') : $this->cutoff?->copy();
        $at?->setTimezone($this->ruleTimezone);
        return $at?->isStartOfDay() ? $at->subMinute() : $at;
    }

    public function displayStage(array $stage): self
    {
        $rule = clone $this;
        $rule->cutoff = Carbon::parse($stage['at'], 'UTC');
        $rule->expiration = $rule->deadlineAt();
        $rule->penalty = $stage['percent'] / 100;
        $rule->retained_percent = $stage['percent'];
        $rule->id = $stage['id'] ?? null;
        return $rule;
    }

    public function at(CarbonInterface $submitted): self
    {
        $rule = clone $this; $applied = null; $lateSince = null;
        foreach ($this->series as $stage) {
            $at = Carbon::parse($stage['at'], 'UTC');
            if ($this->legacyBoundary ? $submitted->gt($at) : $submitted->gte($at)) {
                $applied = $stage;
                if (!$lateSince && $stage['percent'] < 100) $lateSince = $at;
            }
        }
        $rule->penalty = $applied ? $applied['percent'] / 100 : 1;
        $rule->cutoff = $lateSince ?? ($applied ? Carbon::parse($applied['at'], 'UTC') : $this->cutoff);
        return $rule;
    }
}
