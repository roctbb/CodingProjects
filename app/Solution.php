<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Solution extends Model
{
    protected $table = 'solutions';

    protected $fillable = [
        'text', 'step_id', 'submitted', 'user_id'
    ];

    protected $appends = ['mark'];

    protected $casts = [
        'submitted' => 'datetime',
        'checked' => 'datetime',
        'deadline_penalty_paid_at' => 'datetime',
        'xp_booster_used_at' => 'datetime',
    ];

    public function deadline()
    {
        if (!$this->task || !$this->course_id) {
            return null;
        }

        return $this->task->getDeadline($this->course_id);
    }

    public function deadlineCutoff($deadline = null)
    {
        $deadline = $deadline ?: $this->deadline();

        if (!$deadline || !$deadline->expiration) {
            return null;
        }

        return $deadline->expiration->copy()->addDay();
    }

    public function submittedAt()
    {
        return $this->submitted ?: $this->created_at ?: Carbon::now();
    }

    public function scopePendingReview($query)
    {
        return $query
            ->whereNotNull('submitted')
            ->whereNull('mark')
            ->whereNotExists(function ($query) {
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
    }

    public function isSubmittedAfterDeadline($deadline = null)
    {
        $cutoff = $this->deadlineCutoff($deadline);

        return $cutoff && $this->submittedAt()->gt($cutoff);
    }

    public function calculateDeadlinePenaltyDays($deadline = null)
    {
        if (!$this->isSubmittedAfterDeadline($deadline)) {
            return 0;
        }

        $secondsLate = $this->submittedAt()->diffInSeconds($this->deadlineCutoff($deadline));

        return max(1, (int) ceil($secondsLate / 86400));
    }

    public function applyDeadlinePenalty($rawMark, $deadline = null)
    {
        $rawMark = max(0, (int) $rawMark);
        $this->raw_mark = $rawMark;

        $deadline = $deadline ?: $this->deadline();

        if ($this->deadline_penalty_paid_at) {
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
        $penalizedMark = (int) ceil($boostedRawMark * $deadline->penalty);
        $this->mark = $penalizedMark;
        $this->deadline_penalty_amount = max(0, $boostedRawMark - $penalizedMark);
        $this->deadline_penalty_days = $this->calculateDeadlinePenaltyDays($deadline);
    }

    public function markWithoutXpBooster()
    {
        if ($this->raw_mark === null) {
            return max(0, (int) $this->mark - (int) $this->xp_booster_amount);
        }

        $deadline = $this->deadline();

        if (!$deadline || $this->deadline_penalty_paid_at || !$this->isSubmittedAfterDeadline($deadline)) {
            return (int) $this->raw_mark;
        }

        return (int) ceil($this->raw_mark * $deadline->penalty);
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
        $clone->applyDeadlinePenalty($clone->raw_mark === null ? $clone->mark : $clone->raw_mark, $clone->task->getDeadline($clone->course_id));

        return (int) $clone->mark;
    }

    public function hasXpBooster()
    {
        return $this->xp_booster_used_at !== null;
    }

    public function hasScoreModifier()
    {
        return $this->deadline_penalty_paid_at || $this->hasXpBooster();
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

    public function xpBoosterCost()
    {
        return 10;
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
            && $user->balance() >= $this->xpBoosterCost();
    }

    public function qualifiesForTaskPriceReward()
    {
        return $this->task
            && !$this->hasXpBooster()
            && $this->mark == $this->task->max_mark;
    }

    public function hasDeadlinePenalty()
    {
        return $this->deadline_penalty_amount > 0;
    }

    public function hasActiveDeadlinePenalty()
    {
        return $this->hasDeadlinePenalty() && !$this->deadline_penalty_paid_at;
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
