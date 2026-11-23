<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Solution extends Model
{
    protected $table = 'solutions';

    protected $fillable = [
        'text', 'step_id', 'submitted', 'user_id',
        'geekpaste_code_id', 'geekpaste_ai_warning', 'geekpaste_ai_confidence',
        'geekpaste_ai_reasons', 'geekpaste_llm_probability',
        'geekpaste_similarity_checked', 'geekpaste_similarity_warning',
        'geekpaste_similarity_critical', 'geekpaste_similarity_max_percent',
        'geekpaste_similarity_matches_count', 'geekpaste_integrity_synced_at',
        'geekpaste_integrity_dismissed_at', 'geekpaste_integrity_dismissed_by',
    ];

    protected $appends = ['mark'];
    protected $hidden = ['material_snapshot'];

    protected $casts = [
        'evaluated_mark' => 'integer',
        'deadline_rule_snapshot' => 'array',
        'class_bonus_snapshot' => 'array',
        'submitted' => 'datetime',
        'checked' => 'datetime',
        'deadline_penalty_paid_at' => 'datetime',
        'deadline_penalty_waived_at' => 'datetime',
        'xp_booster_used_at' => 'datetime',
        'recheck_requested' => 'boolean',
        'review_skipped' => 'boolean',
        'geekpaste_ai_warning' => 'boolean',
        'geekpaste_similarity_checked' => 'boolean',
        'geekpaste_similarity_warning' => 'boolean',
        'geekpaste_similarity_critical' => 'boolean',
        'geekpaste_integrity_synced_at' => 'datetime',
        'geekpaste_integrity_dismissed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(fn (self $solution) => $solution->freezeDeadline());
        static::saving(fn (self $solution) => app(\App\Services\ClassroomBonus::class)->freeze($solution));
        static::saved(fn (self $solution) => app(\App\Services\ClassroomBonus::class)->award($solution));
        static::saved(fn (self $solution) => $solution->dismissSupersededRechecks());
    }

    public function save(array $options = [])
    {
        // Observers may issue a classroom reward. Its failure must also roll back the grade.
        return $this->getConnection()->transaction(fn () => parent::save($options));
    }

    public function evaluatedMark(): int
    {
        return (int) ($this->evaluated_mark ?? $this->raw_mark ?? $this->mark ?? 0);
    }

    public function setEvaluatedMark($mark): void
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('solutions', 'evaluated_mark')) {
            $this->evaluated_mark = $mark === null ? null : max(0, (int) $mark);
        }
    }

    public function scopeWithEvaluation($query)
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('solutions', 'evaluated_mark')) $query->addSelect('evaluated_mark');
        return $query;
    }

    public function scopePositiveEvaluation($query)
    {
        return \Illuminate\Support\Facades\Schema::hasColumn('solutions', 'evaluated_mark')
            ? $query->whereRaw('COALESCE(solutions.evaluated_mark, solutions.raw_mark, solutions.mark) > 0')
            : $query->where('solutions.mark', '>', 0);
    }

    public function scopeFullEvaluation($query, int $maximum)
    {
        if ($maximum <= 0) return $query->whereRaw('1 = 0');
        $fields = ['solutions.mark'];
        foreach (['raw_mark', 'evaluated_mark'] as $field) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('solutions', $field)) array_unshift($fields, 'solutions.'.$field);
        }
        $score = count($fields) > 1 ? 'COALESCE('.implode(',', $fields).')' : $fields[0];
        return $query->whereNotNull('solutions.submitted')->whereNotNull('solutions.mark')->whereRaw($score.' >= ?', [$maximum]);
    }

    private function dismissSupersededRechecks(): void
    {
        if (!$this->submitted || $this->mark === null || !$this->course_id || !$this->task_id || !$this->user_id
            || !\Illuminate\Support\Facades\Schema::hasColumn('solutions', 'recheck_requested')) return;
        $maximum = (int) $this->task?->max_mark;
        if ($maximum <= 0 || $this->evaluatedMark() < $maximum) return;

        // Preserve grades, original request comments and ungraded answers needed for classroom bonuses.
        self::where('course_id', $this->course_id)->where('task_id', $this->task_id)->where('user_id', $this->user_id)
            ->where('recheck_requested', true)
            ->where(fn ($older) => $older->where('submitted', '<', $this->submitted)
                ->orWhere(fn ($same) => $same->where('submitted', $this->submitted)->where('id', '<', $this->id)))
            ->update(['recheck_requested' => false]);
    }

    public function freezeDeadline($deadline = null): void
    {
        if (!$this->course_id || !$this->task_id || !$this->user_id || $this->submitted === null || $this->deadline_rule_snapshot !== null ||
            !\Illuminate\Support\Facades\Schema::hasColumn('solutions', 'deadline_rule_snapshot')) return;
        $deadline ??= $this->task?->getDeadline($this->course_id, $this->user_id, $this->submittedAt());
        $this->deadline_rule_snapshot = $deadline
            ? ($deadline instanceof \App\Support\DeadlineRule ? $deadline : \App\Support\DeadlineRule::fromLegacy($deadline))->snapshot()
            : ['none' => true];
    }

    public function deadline()
    {
        if ($this->deadline_rule_snapshot !== null) {
            return !empty($this->deadline_rule_snapshot['none']) ? null
                : \App\Support\DeadlineRule::fromSnapshot($this->deadline_rule_snapshot)->at($this->submittedAt());
        }
        if (!$this->task || !$this->course_id) {
            return null;
        }

        $deadline = $this->task->getDeadline($this->course_id, $this->user_id, $this->submittedAt());
        return $deadline instanceof \App\Support\DeadlineRule ? $deadline->at($this->submittedAt()) : $deadline;
    }

    public function deadlineCutoff($deadline = null)
    {
        $deadline = $deadline ?: $this->deadline();

        if (!$deadline || !$deadline->expiration) {
            return null;
        }

        return $deadline instanceof \App\Support\DeadlineRule ? $deadline->at($this->submittedAt())->cutoffAt() : $deadline->expiration->copy()->addDay();
    }

    public function submittedAt()
    {
        return $this->submitted ?: $this->created_at ?: Carbon::now();
    }

    public function scopePendingReview($query)
    {
        return $query->where(function ($q) {
            $q->where(fn ($ordinary) => $this->ordinaryPendingReview($ordinary));
            if (\Illuminate\Support\Facades\Schema::hasColumn('solutions', 'evaluated_mark')
                && \Illuminate\Support\Facades\Schema::hasColumn('solutions', 'class_bonus_snapshot')) {
                $q->orWhere(fn ($needed) => $this->bonusPendingReview($needed));
            }
        });
    }

    private function bonusPendingReview($query)
    {
        return $query->whereNull('solutions.mark')->whereNotNull('solutions.submitted')
            ->whereExists(function ($later) {
                $later->selectRaw('1')->from('solutions as bonus_solutions')->join('tasks as bonus_tasks', 'bonus_tasks.id', '=', 'bonus_solutions.task_id')
                    ->whereColumn('bonus_solutions.course_id', 'solutions.course_id')->whereColumn('bonus_solutions.user_id', 'solutions.user_id')
                    ->whereColumn('bonus_solutions.task_id', 'solutions.task_id')->whereNotNull('bonus_solutions.mark')->where('bonus_tasks.max_mark', '>', 0)
                    ->where('bonus_solutions.class_bonus_snapshot->amount', '>', 0)
                    ->whereRaw('COALESCE(bonus_solutions.evaluated_mark, bonus_solutions.raw_mark, bonus_solutions.mark) >= bonus_tasks.max_mark')
                    ->where(function ($newer) {
                        $newer->whereColumn('bonus_solutions.submitted', '>', 'solutions.submitted')
                            ->orWhere(fn ($same) => $same->whereColumn('bonus_solutions.submitted', 'solutions.submitted')->whereColumn('bonus_solutions.id', '>', 'solutions.id'));
                    });
            })->whereNotExists(fn ($awards) => $awards->selectRaw('1')->from('classroom_bonus_awards')
                ->whereColumn('classroom_bonus_awards.course_id', 'solutions.course_id')->whereColumn('classroom_bonus_awards.task_id', 'solutions.task_id')
                ->whereColumn('classroom_bonus_awards.user_id', 'solutions.user_id'));
    }

    private function ordinaryPendingReview($query)
    {
        return $query
            ->whereNotNull('submitted')
            ->where(function ($query) {
                $query->whereNull('mark')
                    ->orWhere('recheck_requested', true);
            })
            ->where(function ($query) {
                $query->where('review_skipped', false)
                    ->orWhereNull('review_skipped');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('solutions as max_mark_solutions')
                    ->join('tasks as max_mark_tasks', 'max_mark_tasks.id', '=', 'max_mark_solutions.task_id')
                    ->whereColumn('max_mark_solutions.course_id', 'solutions.course_id')
                    ->whereColumn('max_mark_solutions.task_id', 'solutions.task_id')
                    ->whereColumn('max_mark_solutions.user_id', 'solutions.user_id')
                    ->whereNotNull('max_mark_solutions.submitted')
                    ->whereNotNull('max_mark_solutions.mark')
                    ->where('max_mark_tasks.max_mark', '>', 0);
                if (\Illuminate\Support\Facades\Schema::hasColumn('solutions', 'evaluated_mark')) {
                    $query->whereRaw('COALESCE(max_mark_solutions.evaluated_mark, max_mark_solutions.raw_mark, max_mark_solutions.mark) >= max_mark_tasks.max_mark');
                } elseif (\Illuminate\Support\Facades\Schema::hasColumn('solutions', 'raw_mark')) {
                    $query->whereRaw('COALESCE(max_mark_solutions.raw_mark, max_mark_solutions.mark) >= max_mark_tasks.max_mark');
                } else $query->whereColumn('max_mark_solutions.mark', '>=', 'max_mark_tasks.max_mark');
            })
            ->where(function ($pending) {
                $pending->where('solutions.recheck_requested', true)->orWhereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('solutions as checked_solutions')
                        ->whereColumn('checked_solutions.course_id', 'solutions.course_id')
                        ->whereColumn('checked_solutions.task_id', 'solutions.task_id')
                        ->whereColumn('checked_solutions.user_id', 'solutions.user_id')
                        ->whereNotNull('checked_solutions.submitted')
                        ->whereNotNull('checked_solutions.mark')
                        ->where(function ($query) {
                            $query->whereColumn('checked_solutions.submitted', '>', 'solutions.submitted')
                                ->orWhere(function ($query) {
                                    $query->whereColumn('checked_solutions.submitted', 'solutions.submitted')
                                        ->whereColumn('checked_solutions.id', '>', 'solutions.id');
                                });
                        });
                });
            });
    }

    public function scopeWithReviewVersion($query)
    {
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            if ($query->getQuery()->columns === null) $query->select('solutions.*');
            $query->selectRaw('solutions.xmin::text as review_row_version');
        }
        return $query;
    }

    public function reviewToken(): string
    {
        $version = $this->getRawOriginal('review_row_version');
        if ($version === null && $this->exists && $this->getConnection()->getDriverName() === 'pgsql') {
            $version = $this->getConnection()->table($this->getTable())->where('id', $this->id)
                ->selectRaw('xmin::text as row_version')->first()?->row_version;
        }
        $state = $version !== null ? ['pgsql', (string) $version] : array_map(
            fn ($field) => $this->getRawOriginal($field),
            ['submitted', 'mark', 'review_skipped', 'recheck_requested', 'recheck_comment', 'updated_at']
        );
        return hash_hmac('sha256', json_encode([$this->id, $state], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function skipPendingReview(?string $expectedReviewToken = null)
    {
        return DB::transaction(function () use ($expectedReviewToken) {
            $locked = self::whereKey($this->id)->withReviewVersion()->lockForUpdate()->firstOrFail();
            $skipped = $locked->skipLockedReview($expectedReviewToken);
            $this->refresh();
            return $skipped;
        });
    }

    private function skipLockedReview(?string $expectedReviewToken): bool
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('solutions', 'evaluated_mark')
            && \Illuminate\Support\Facades\Schema::hasColumn('solutions', 'class_bonus_snapshot')) {
            abort_if($this->bonusPendingReview(self::whereKey($this->id))->exists(), 409,
                'Сначала оцените раннюю сдачу: от неё зависит бонус за занятие.');
        }
        if (!$this->isPendingReview()) {
            return false;
        }
        abort_unless($expectedReviewToken !== null && hash_equals($this->reviewToken(), $expectedReviewToken), 409,
            'Очередь изменилась. Обновите страницу и выберите работу заново.');
        // Queue dismissal must not run grading/reward observers or change another solution.
        return (bool) DB::table($this->getTable())->where('id', $this->id)->update([
            'review_skipped' => true, 'recheck_requested' => false, 'recheck_comment' => null,
            'updated_at' => $this->freshTimestampString(),
        ]);
    }

    public function isPendingReview()
    {
        $ordinary = $this->submitted !== null
            && !$this->review_skipped
            && ($this->mark === null || $this->recheck_requested);
        if ($ordinary) return true;
        return $this->mark === null && $this->submitted !== null && $this->exists
            && \Illuminate\Support\Facades\Schema::hasColumn('solutions', 'class_bonus_snapshot')
            && \Illuminate\Support\Facades\Schema::hasColumn('solutions', 'evaluated_mark')
            && $this->bonusPendingReview(self::whereKey($this->id))->exists();
    }

    public function isSubmittedAfterDeadline($deadline = null)
    {
        $deadline ??= $this->deadline();
        if ($deadline instanceof \App\Support\DeadlineRule) {
            $rule = $deadline->at($this->submittedAt());
            return $rule->penalty < 1 && $rule->cutoffAt() && ($rule->legacyBoundary
                ? $this->submittedAt()->gt($rule->cutoffAt()) : $this->submittedAt()->gte($rule->cutoffAt()));
        }
        $cutoff = $this->deadlineCutoff($deadline);

        return $cutoff && $this->submittedAt()->gt($cutoff);
    }

    public function calculateDeadlinePenaltyDays($deadline = null)
    {
        if (!$this->isSubmittedAfterDeadline($deadline)) {
            return 0;
        }

        $secondsLate = $this->submittedAt()->diffInSeconds($this->deadlineCutoff($deadline), true);

        return max(1, (int) ceil($secondsLate / 86400));
    }

    public function applyDeadlinePenalty($rawMark, $deadline = null)
    {
        if (!$this->hasScoreModifier()) $this->setEvaluatedMark($rawMark);
        $this->freezeDeadline($deadline);
        app(\App\Services\ClassroomBonus::class)->freeze($this);
        if (($bonus = $this->class_bonus_snapshot) && !$this->hasScoreModifier()) {
            $bonus['graded_raw'] = $rawMark === null ? null : max(0, (int) $rawMark);
            $this->class_bonus_snapshot = $bonus;
        }
        if ($this->deadline_rule_snapshot !== null) $deadline = $this->deadline();
        elseif ($deadline instanceof \App\Support\DeadlineRule) $deadline = $deadline->at($this->submittedAt());
        $rawMark = $this->normalizeSubmittedMark($rawMark);
        $this->raw_mark = $rawMark;
        if ($rawMark === null) {
            $this->mark = null;
            $this->deadline_penalty_amount = 0;
            $this->deadline_penalty_days = 0;
            return;
        }

        $deadline = $deadline ?: $this->deadline();

        if ($this->isDeadlinePenaltyRemoved()) {
            $boostedRawMark = $this->applyXpBoosterToRawMark($rawMark);
            $this->mark = $boostedRawMark;

            if ($deadline && $this->isSubmittedAfterDeadline($deadline)) {
                $penalizedMark = (int) ceil($boostedRawMark * $deadline->penalty);
                $this->deadline_penalty_amount = max(0, $boostedRawMark - $penalizedMark);
                $this->deadline_penalty_days = $this->calculateDeadlinePenaltyDays($deadline);
            } else {
                $this->deadline_penalty_amount = 0;
                $this->deadline_penalty_days = 0;
            }

            return;
        }

        if (!$deadline || !$this->isSubmittedAfterDeadline($deadline)) {
            $this->mark = $this->applyXpBoosterToRawMark($rawMark);
            $this->deadline_penalty_amount = 0;
            $this->deadline_penalty_days = 0;
            return;
        }

        $boostedRawMark = $this->applyXpBoosterToRawMark($rawMark);
        $penalizedMark = $this->normalizeSubmittedMark((int) ceil($boostedRawMark * $deadline->penalty));
        $this->mark = $penalizedMark;
        $this->deadline_penalty_amount = max(0, $boostedRawMark - $penalizedMark);
        $this->deadline_penalty_days = $this->calculateDeadlinePenaltyDays($deadline);
    }

    public function normalizeSubmittedMark($mark)
    {
        if ($mark === null || $mark === '') {
            return null;
        }
        $mark = max(0, (int) $mark);

        // Visibility placeholders and missing submissions must remain zero.
        if ($mark === 0 && $this->submitted !== null && trim((string) $this->text) !== '') {
            return 1;
        }

        return $mark;
    }

    public function markWithoutXpBooster()
    {
        if ($this->raw_mark === null) {
            return max(0, (int) $this->mark - (int) $this->xp_booster_amount);
        }

        $deadline = $this->deadline();

        if (!$deadline || $this->isDeadlinePenaltyRemoved() || !$this->isSubmittedAfterDeadline($deadline)) {
            return (int) $this->raw_mark;
        }

        return $this->normalizeSubmittedMark((int) ceil($this->raw_mark * $deadline->penalty));
    }

    public function applyXpBoosterToRawMark($rawMark)
    {
        $rawMark = max(0, (int) $rawMark);

        if (!$this->xp_booster_used_at) {
            $this->xp_booster_amount = 0;
            return $rawMark;
        }

        $maxMark = $this->task ? (int) $this->task->max_mark : $rawMark;
        $boostedMark = min($maxMark, $rawMark + 5);
        $this->xp_booster_amount = max(0, $boostedMark - $rawMark);

        return $boostedMark;
    }

    public function previewMarkWithXpBooster()
    {
        if (!$this->task || $this->mark === null) {
            return (int) $this->mark;
        }

        $clone = clone $this;
        $clone->setRelation('task', $this->task);
        $clone->xp_booster_used_at = $clone->xp_booster_used_at ?: Carbon::now();
        $clone->applyDeadlinePenalty($clone->raw_mark === null ? $clone->mark : $clone->raw_mark, $clone->task->getDeadline($clone->course_id, $clone->user_id, $clone->submittedAt()));

        return (int) $clone->mark;
    }

    public function hasXpBooster()
    {
        return $this->xp_booster_used_at !== null;
    }

    public function hasScoreModifier()
    {
        return $this->isDeadlinePenaltyRemoved() || $this->hasXpBooster();
    }

    public function scoreBadgeClass($default = 'bg-body-tertiary')
    {
        return $this->hasScoreModifier() ? 'solution-score-badge--special' : $default;
    }

    public static function bestScoredIn($solutions)
    {
        return $solutions
            ->filter(function ($solution) {
                return $solution->mark !== null;
            })
            ->sort(function ($left, $right) {
                if ((int) $left->mark !== (int) $right->mark) {
                    return (int) $right->mark <=> (int) $left->mark;
                }

                return strtotime((string) $right->submitted) <=> strtotime((string) $left->submitted);
            })
            ->first();
    }

    public function xpBoosterCost($user = null)
    {
        if ($user && $user->canUseFreeXpBooster()) {
            return 0;
        }

        return app(\App\Services\SystemStoreSettings::class)->price('xp_booster');
    }

    public function canUseXpBooster($user)
    {
        return $user
            && $this->task
            && $this->task->xp_booster_enabled
            && $this->user_id == $user->id
            && $this->mark !== null
            && !$this->hasXpBooster()
            && $this->mark < $this->task->max_mark
            && $this->previewMarkWithXpBooster() > $this->mark
            && ($this->xpBoosterCost($user) === 0 || $user->balance() >= $this->xpBoosterCost($user));
    }

    public function qualifiesForTaskPriceReward()
    {
        return $this->task
            && $this->evaluatedMark() > 0
            && !$this->hasXpBooster()
            && $this->mark == $this->task->max_mark;
    }

    public function isEligibleForAiAchievement()
    {
        if (!$this->task || !$this->task->generates_ai_achievement) {
            return false;
        }

        if (!$this->submitted || $this->mark === null || (int) $this->task->max_mark <= 0) {
            return false;
        }

        return $this->evaluatedMark() > 0 && (int) $this->mark >= (int) $this->task->max_mark
            && $this->markWithoutXpBooster() >= (int) $this->task->max_mark;
    }

    public function hasGeekPasteIntegrityData(): bool
    {
        if (trim((string) $this->text) === '') {
            return false;
        }

        return $this->geekpaste_integrity_synced_at
            || $this->geekpaste_code_id
            || $this->geekpaste_ai_warning
            || $this->geekpaste_ai_confidence
            || $this->geekpaste_llm_probability !== null
            || $this->geekpaste_similarity_checked
            || $this->geekpaste_similarity_warning
            || $this->geekpaste_similarity_critical
            || $this->geekpaste_similarity_max_percent !== null
            || (int) $this->geekpaste_similarity_matches_count > 0;
    }

    public function geekPasteIntegrityRiskLevel(): string
    {
        if (!$this->hasGeekPasteIntegrityData() || $this->geekpaste_integrity_dismissed_at) {
            return 'none';
        }

        $llmProbability = $this->geekpaste_llm_probability;
        $similarityPercent = $this->geekpaste_similarity_max_percent;

        if (
            $this->geekpaste_similarity_critical
            || $this->geekpaste_ai_confidence === 'high'
            || ($llmProbability !== null && (int) $llmProbability >= 80)
            || ($similarityPercent !== null && (int) $similarityPercent >= 95)
        ) {
            return 'high';
        }

        if (
            $this->geekpaste_ai_warning
            || $this->geekpaste_similarity_warning
            || $this->geekpaste_ai_confidence === 'medium'
            || ($llmProbability !== null && (int) $llmProbability >= 60)
            || ($similarityPercent !== null && (int) $similarityPercent >= 75)
        ) {
            return 'medium';
        }

        return 'low';
    }

    public function geekPasteIntegritySuspicionKind(): ?string
    {
        if (!$this->hasGeekPasteIntegrityData() || $this->geekpaste_integrity_dismissed_at) {
            return null;
        }

        if (
            $this->geekpaste_similarity_warning
            || $this->geekpaste_similarity_critical
            || $this->geekpaste_similarity_max_percent !== null
            || (int) $this->geekpaste_similarity_matches_count > 0
        ) {
            return 'similarity';
        }

        if (
            $this->geekpaste_ai_warning
            || $this->geekpaste_ai_confidence
            || $this->geekpaste_llm_probability !== null
        ) {
            return 'ai';
        }

        return null;
    }

    public function hasDeadlinePenalty()
    {
        return $this->deadline_penalty_amount > 0;
    }

    public function hasActiveDeadlinePenalty()
    {
        return $this->hasDeadlinePenalty() && !$this->isDeadlinePenaltyRemoved();
    }

    public function isDeadlinePenaltyRemoved()
    {
        return $this->deadline_penalty_paid_at !== null || $this->deadline_penalty_waived_at !== null;
    }

    public function deadlinePenaltyCost()
    {
        return $this->deadline_penalty_days * 3;
    }

    public function canPayDeadlinePenalty($user)
    {
        return $user
            && $this->user_id == $user->id
            && $this->hasActiveDeadlinePenalty()
            && $this->deadlinePenaltyCost() > 0
            && $user->balance() >= $this->deadlinePenaltyCost();
    }

    public function getNmarkAttribute()
    {
        return $this->mark;
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function task()
    {
        return $this->belongsTo('App\Task', 'task_id', 'id');
    }

    public function course()
    {
        return $this->belongsTo('App\Course', 'course_id', 'id');
    }

    public function teacher()
    {
        return $this->belongsTo('App\User', 'teacher_id', 'id');
    }
}
