<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class CourseActivity extends Model
{
    const TYPE_SOLUTION_SUBMITTED = 'solution_submitted';
    const TYPE_SOLUTION_CHECKED = 'solution_checked';
    const TYPE_XP_BOOSTER_USED = 'xp_booster_used';
    const TYPE_DEADLINE_PENALTY_PAID = 'deadline_penalty_paid';
    const TYPE_EARLY_ACCESS_BOUGHT = 'early_access_bought';
    const TYPE_LESSON_OPENED = 'lesson_opened';
    const TYPE_GEEKPASTE_ATTEMPT_BOUGHT = 'geekpaste_attempt_bought';
    const TYPE_TASK_AI_SUMMARY = 'task_ai_summary';
    const TYPE_AI_ACHIEVEMENT_EARNED = 'ai_achievement_earned';
    const PULSE_WINDOW_HOURS = 24;
    const PULSE_SAMPLE_MINUTES = 8;
    const PULSE_SIGNAL_LOOKBACK_HOURS = 30;
    const PULSE_RESPONSE_SCALE = 18;

    protected $table = 'course_activities';

    protected $fillable = [
        'course_id',
        'lesson_id',
        'step_id',
        'task_id',
        'solution_id',
        'user_id',
        'type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function course()
    {
        return $this->belongsTo('App\Course', 'course_id', 'id');
    }

    public function lesson()
    {
        return $this->belongsTo('App\Lesson', 'lesson_id', 'id');
    }

    public function task()
    {
        return $this->belongsTo('App\Task', 'task_id', 'id');
    }

    public function solution()
    {
        return $this->belongsTo('App\Solution', 'solution_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public static function recordActivity(array $attributes)
    {
        try {
            return static::create($attributes);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function recordSolutionSubmitted(Solution $solution)
    {
        return static::recordForSolution(static::TYPE_SOLUTION_SUBMITTED, $solution);
    }

    public static function recordSolutionChecked(Solution $solution)
    {
        return static::recordForSolution(static::TYPE_SOLUTION_CHECKED, $solution, [
            'mark' => (int) $solution->mark,
            'max_mark' => $solution->task ? (int) $solution->task->max_mark : null,
        ]);
    }

    public static function recordXpBoosterUsed(Solution $solution, int $cost, int $amount)
    {
        return static::recordForSolution(static::TYPE_XP_BOOSTER_USED, $solution, [
            'cost' => $cost,
            'amount' => $amount,
        ]);
    }

    public static function recordDeadlinePenaltyPaid(Solution $solution, int $cost)
    {
        return static::recordForSolution(static::TYPE_DEADLINE_PENALTY_PAID, $solution, [
            'cost' => $cost,
        ]);
    }

    public static function recordGeekPasteAttemptBought(Task $task, Course $course, User $user, int $cost)
    {
        $task->loadMissing('step.lesson');
        $step = $task->step;
        $lesson = $step ? $step->lesson : null;

        return static::recordActivity([
            'course_id' => $course->id,
            'lesson_id' => $lesson ? $lesson->id : null,
            'step_id' => $step ? $step->id : null,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => static::TYPE_GEEKPASTE_ATTEMPT_BOUGHT,
            'payload' => static::basePayload($course, $lesson, $task) + [
                'cost' => $cost,
            ],
        ]);
    }

    public static function recordEarlyAccessBought(Course $course, Lesson $lesson, User $user, int $cost)
    {
        return static::recordActivity([
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'type' => static::TYPE_EARLY_ACCESS_BOUGHT,
            'payload' => static::basePayload($course, $lesson) + [
                'cost' => $cost,
            ],
        ]);
    }

    public static function recordLessonOpened(Course $course, Lesson $lesson, ?User $user = null)
    {
        return static::recordActivity([
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'user_id' => $user ? $user->id : null,
            'type' => static::TYPE_LESSON_OPENED,
            'payload' => static::basePayload($course, $lesson),
        ]);
    }

    public static function recordTaskAiSummary(Course $course, Task $task, User $user, string $summary, ?string $instruction = null)
    {
        $task->loadMissing('step.lesson');
        $step = $task->step;
        $lesson = $step ? $step->lesson : null;
        $payload = static::basePayload($course, $lesson, $task) + [
            'summary' => $summary,
        ];

        if ($instruction !== null && trim($instruction) !== '') {
            $payload['instruction'] = trim($instruction);
        }

        return static::recordActivity([
            'course_id' => $course->id,
            'lesson_id' => $lesson ? $lesson->id : null,
            'step_id' => $step ? $step->id : null,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => static::TYPE_TASK_AI_SUMMARY,
            'payload' => $payload,
        ]);
    }

    public static function recordAchievementEarned(Achievement $achievement)
    {
        $achievement->loadMissing('course', 'task.step.lesson', 'user');
        $course = $achievement->course;
        $task = $achievement->task;
        $step = $task ? $task->step : null;
        $lesson = $step ? $step->lesson : null;

        if (!$course || !$task) {
            return null;
        }

        return static::recordActivity([
            'course_id' => $course->id,
            'lesson_id' => $lesson ? $lesson->id : null,
            'step_id' => $step ? $step->id : null,
            'task_id' => $task->id,
            'solution_id' => $achievement->solution_id,
            'user_id' => $achievement->user_id,
            'type' => static::TYPE_AI_ACHIEVEMENT_EARNED,
            'payload' => static::basePayload($course, $lesson, $task) + [
                'achievement_id' => $achievement->id,
                'achievement_title' => $achievement->title,
                'achievement_description' => $achievement->description,
                'icon_key' => $achievement->icon_key,
                'visual_key' => $achievement->payload['visual_key'] ?? null,
            ],
        ]);
    }

    public static function pulseForCourses($courseIds, ?Carbon $now = null)
    {
        $courseIds = collect($courseIds)->filter()->values();
        $now = $now ?: Carbon::now();

        if ($courseIds->isEmpty()) {
            return static::emptyPulse($now);
        }

        $activities = static::query()
            ->select(['type', 'created_at'])
            ->whereIn('course_id', $courseIds)
            ->where('created_at', '>=', $now->copy()->subHours(static::PULSE_SIGNAL_LOOKBACK_HOURS))
            ->orderBy('created_at')
            ->get();

        $sampleCount = (int) ((static::PULSE_WINDOW_HOURS * 60) / static::PULSE_SAMPLE_MINUTES);
        $series = collect(range($sampleCount, 0))->map(function ($stepAgo) use ($activities, $now) {
            $pointAt = $now->copy()->subMinutes($stepAgo * static::PULSE_SAMPLE_MINUTES);
            $value = static::pulseValueAt($activities, $pointAt);

            return [
                'label' => $pointAt->format('H:i'),
                'value' => $value,
            ];
        })->values();

        $current = (int) $series->last()['value'];
        $previous = static::pulseValueAt($activities, $now->copy()->subHours(6));
        $change = $current - $previous;

        return [
            'current' => $current,
            'label' => static::pulseLabel($current),
            'level' => static::pulseLevel($current),
            'change' => $change,
            'trend' => static::pulseTrend($change),
            'series' => $series->all(),
        ];
    }

    private static function pulseValueAt($activities, Carbon $pointAt)
    {
        $windowStart = $pointAt->copy()->subHours(static::PULSE_SIGNAL_LOOKBACK_HOURS);
        $rawPulse = $activities->reduce(function ($score, $activity) use ($pointAt, $windowStart) {
            if (!$activity->created_at || $activity->created_at->gt($pointAt) || $activity->created_at->lt($windowStart)) {
                return $score;
            }

            $ageMinutes = max(0, $activity->created_at->diffInMinutes($pointAt));

            return $score + static::pulseWeight($activity->type) * static::pulseImpulse($ageMinutes, $activity->type);
        }, 0);

        return min(100, (int) round(100 * (1 - exp(-$rawPulse / static::PULSE_RESPONSE_SCALE))));
    }

    private static function pulseImpulse($ageMinutes, $type)
    {
        $ageMinutes = max(0, (float) $ageMinutes);
        $sharpPeak = exp(-0.5 * pow(($ageMinutes - 6) / 3.2, 2)) * 1.62;
        $shoulder = exp(-0.5 * pow(($ageMinutes - 13) / 5.4, 2)) * 0.35;
        $secondaryWave = exp(-0.5 * pow(($ageMinutes - 28) / 12, 2)) * 0.34;
        $longTail = exp(-$ageMinutes / 165) * 0.11;

        if ($type === static::TYPE_TASK_AI_SUMMARY || $type === static::TYPE_AI_ACHIEVEMENT_EARNED) {
            $secondaryWave *= 1.35;
            $longTail *= 1.35;
        }

        return $sharpPeak + $shoulder + $secondaryWave + $longTail;
    }

    private static function pulseWeight($type)
    {
        switch ($type) {
            case static::TYPE_SOLUTION_SUBMITTED:
                return 4.0;
            case static::TYPE_SOLUTION_CHECKED:
                return 3.5;
            case static::TYPE_XP_BOOSTER_USED:
                return 3.0;
            case static::TYPE_TASK_AI_SUMMARY:
                return 2.5;
            case static::TYPE_AI_ACHIEVEMENT_EARNED:
                return 3.0;
            case static::TYPE_DEADLINE_PENALTY_PAID:
            case static::TYPE_EARLY_ACCESS_BOUGHT:
            case static::TYPE_GEEKPASTE_ATTEMPT_BOUGHT:
                return 2.0;
            case static::TYPE_LESSON_OPENED:
                return 1.5;
            default:
                return 1.0;
        }
    }

    private static function pulseLabel($value)
    {
        if ($value >= 75) {
            return 'кипит';
        }

        if ($value >= 50) {
            return 'живо';
        }

        if ($value >= 25) {
            return 'спокойно';
        }

        return $value > 0 ? 'тихо' : 'нет сигнала';
    }

    private static function pulseLevel($value)
    {
        if ($value >= 75) {
            return 'hot';
        }

        if ($value >= 50) {
            return 'live';
        }

        if ($value >= 25) {
            return 'steady';
        }

        return $value > 0 ? 'quiet' : 'empty';
    }

    private static function pulseTrend($change)
    {
        if ($change >= 8) {
            return 'растёт';
        }

        if ($change <= -8) {
            return 'падает';
        }

        return 'ровно';
    }

    private static function emptyPulse(Carbon $now)
    {
        $sampleCount = (int) ((static::PULSE_WINDOW_HOURS * 60) / static::PULSE_SAMPLE_MINUTES);

        return [
            'current' => 0,
            'label' => 'нет сигнала',
            'level' => 'empty',
            'change' => 0,
            'trend' => 'ровно',
            'series' => collect(range($sampleCount, 0))->map(function ($stepAgo) use ($now) {
                $pointAt = $now->copy()->subMinutes($stepAgo * static::PULSE_SAMPLE_MINUTES);

                return [
                    'label' => $pointAt->format('H:i'),
                    'value' => 0,
                ];
            })->all(),
        ];
    }

    public function title()
    {
        return $this->hasActor()
            ? $this->actorName() . ' ' . $this->actionText()
            : $this->actionText();
    }

    public function hasActor()
    {
        return $this->type !== static::TYPE_LESSON_OPENED;
    }

    public function actorName()
    {
        return optional($this->user)->name ?: 'Кто-то';
    }

    public function actionText()
    {
        $payload = $this->payload ?: [];
        $taskName = $payload['task_name'] ?? optional($this->task)->name ?? 'задачу';
        $lessonName = $payload['lesson_name'] ?? optional($this->lesson)->name ?? 'урок';

        switch ($this->type) {
            case static::TYPE_SOLUTION_SUBMITTED:
                return 'сдал(а) «' . $taskName . '»';
            case static::TYPE_SOLUTION_CHECKED:
                return 'получил(а) ' . (int) ($payload['mark'] ?? 0) . ' XP';
            case static::TYPE_XP_BOOSTER_USED:
                return 'усилил(а) решение бустером';
            case static::TYPE_DEADLINE_PENALTY_PAID:
                return 'снял(а) штраф за дедлайн';
            case static::TYPE_EARLY_ACCESS_BOUGHT:
                return 'открыл(а) урок «' . $lessonName . '» раньше';
            case static::TYPE_LESSON_OPENED:
                return 'Открылся урок «' . $lessonName . '»';
            case static::TYPE_GEEKPASTE_ATTEMPT_BOUGHT:
                return 'взял(а) ещё попытку GeekPaste';
            case static::TYPE_TASK_AI_SUMMARY:
                return 'опубликовал(а) пересказ «' . $taskName . '»';
            case static::TYPE_AI_ACHIEVEMENT_EARNED:
                return 'получил(а) достижение «' . ($payload['achievement_title'] ?? 'Сильное решение') . '»';
            default:
                return 'Новое событие в курсе';
        }
    }

    public function subtitle()
    {
        $payload = $this->payload ?: [];
        $parts = [];

        if (!empty($payload['course_name'])) {
            $parts[] = $payload['course_name'];
        }

        if (!empty($payload['lesson_name']) && $this->type !== static::TYPE_LESSON_OPENED) {
            $parts[] = $payload['lesson_name'];
        }

        if ($this->type === static::TYPE_XP_BOOSTER_USED && !empty($payload['amount'])) {
            $parts[] = '+' . (int) $payload['amount'] . ' XP';
        }

        return implode(' · ', $parts);
    }

    public function iconClass()
    {
        $payload = $this->payload ?: [];

        switch ($this->type) {
            case static::TYPE_SOLUTION_SUBMITTED:
                return 'fas fa-paper-plane';
            case static::TYPE_SOLUTION_CHECKED:
                return 'fas fa-star';
            case static::TYPE_XP_BOOSTER_USED:
                return 'fas fa-bolt';
            case static::TYPE_DEADLINE_PENALTY_PAID:
                return 'fas fa-shield-alt';
            case static::TYPE_EARLY_ACCESS_BOUGHT:
            case static::TYPE_LESSON_OPENED:
                return 'fas fa-unlock-alt';
            case static::TYPE_GEEKPASTE_ATTEMPT_BOUGHT:
                return 'fas fa-robot';
            case static::TYPE_TASK_AI_SUMMARY:
                return 'fas fa-newspaper';
            case static::TYPE_AI_ACHIEVEMENT_EARNED:
                return Achievement::iconOptions()[$payload['icon_key'] ?? 'sparkles'] ?? 'fas fa-magic';
            default:
                return 'fas fa-magic';
        }
    }

    public function toneClass()
    {
        switch ($this->type) {
            case static::TYPE_XP_BOOSTER_USED:
            case static::TYPE_GEEKPASTE_ATTEMPT_BOUGHT:
                return 'is-boost';
            case static::TYPE_SOLUTION_CHECKED:
                return 'is-score';
            case static::TYPE_EARLY_ACCESS_BOUGHT:
            case static::TYPE_LESSON_OPENED:
                return 'is-open';
            case static::TYPE_TASK_AI_SUMMARY:
                return 'is-summary';
            case static::TYPE_AI_ACHIEVEMENT_EARNED:
                return 'is-achievement';
            default:
                return 'is-submit';
        }
    }

    public function url()
    {
        if ($this->step_id && $this->task_id) {
            if ($this->type === static::TYPE_AI_ACHIEVEMENT_EARNED && $this->user_id) {
                return url('/insider/profile/' . $this->user_id . '#achievements');
            }

            return url('/insider/courses/' . $this->course_id . '/steps/' . $this->step_id . '#task' . $this->task_id);
        }

        return url('/insider/courses/' . $this->course_id);
    }

    public function timeAgo()
    {
        $createdAt = $this->created_at ?: Carbon::now();

        return $createdAt->diffForHumans();
    }

    private static function recordForSolution(string $type, Solution $solution, array $payload = [])
    {
        $solution->loadMissing('task.step.lesson', 'course');
        $task = $solution->task;
        $step = $task ? $task->step : null;
        $lesson = $step ? $step->lesson : null;
        $course = $solution->course;

        if (!$course) {
            return null;
        }

        return static::recordActivity([
            'course_id' => $course->id,
            'lesson_id' => $lesson ? $lesson->id : null,
            'step_id' => $step ? $step->id : null,
            'task_id' => $task ? $task->id : null,
            'solution_id' => $solution->id,
            'user_id' => $solution->user_id,
            'type' => $type,
            'payload' => static::basePayload($course, $lesson, $task) + $payload,
        ]);
    }

    private static function basePayload(Course $course, ?Lesson $lesson = null, ?Task $task = null)
    {
        return [
            'course_name' => $course->name,
            'lesson_name' => $lesson ? $lesson->name : null,
            'task_name' => $task ? $task->name : null,
        ];
    }
}
