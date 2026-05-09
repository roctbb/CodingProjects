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
            default:
                return 'fas fa-sparkles';
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
            default:
                return 'is-submit';
        }
    }

    public function url()
    {
        if ($this->step_id && $this->task_id) {
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
