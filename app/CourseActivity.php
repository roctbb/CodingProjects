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
    const TYPE_PET_ACTION = 'pet_action';
    const TYPE_MARKET_PURCHASE = 'market_purchase';
    const TYPE_MARKET_AUCTION_WON = 'market_auction_won';
    const TYPE_MARKET_DIGITAL_PURCHASE = 'market_digital_purchase';
    const TYPE_RANDOM_COIN_DROP = 'random_coin_drop';
    const TYPE_LESSON_COMPLETED = 'lesson_completed';
    const TYPE_CHAPTER_COMPLETED = 'chapter_completed';
    const TYPE_COURSE_DAILY_SUMMARY = 'course_daily_summary';
    const TYPE_TASK_STRUGGLE = 'task_struggle';
    const TYPE_FIRST_DAILY_ACTION = 'first_daily_action';
    const TYPE_LEARNING_STREAK = 'learning_streak';
    const TYPE_FIRST_SOLUTION = 'first_solution';
    const TYPE_XP_MILESTONE = 'xp_milestone';
    const TYPE_AUCTION_LEADING_BID = 'auction_leading_bid';
    const TYPE_AUCTION_FINISHED = 'auction_finished';
    const TYPE_MARKET_ORDER_SHIPPED = 'market_order_shipped';
    const TYPE_TASK_CREATED = 'task_created';
    const TYPE_LESSON_CREATED = 'lesson_created';
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
        $activity = static::recordForSolution(static::TYPE_SOLUTION_SUBMITTED, $solution);

        if ($activity) {
            static::recordFirstDailyAction($solution);
            static::recordFirstSolution($solution);
        }

        return $activity;
    }

    public static function recordSolutionChecked(Solution $solution)
    {
        return static::recordForSolution(static::TYPE_SOLUTION_CHECKED, $solution, [
            'mark' => (int) $solution->mark,
            'max_mark' => $solution->task ? (int) $solution->task->max_mark : null,
        ]);
    }

    public static function recordXpBoosterUsed(Solution $solution, int $cost, int $amount, ?string $petName = null, ?string $petKey = null)
    {
        $payload = [
            'cost' => $cost,
            'amount' => $amount,
        ];

        if ($petName) {
            $payload['pet_name'] = $petName;
        }

        if ($petKey) {
            $payload['pet_key'] = $petKey;
        }

        return static::recordForSolution(static::TYPE_XP_BOOSTER_USED, $solution, $payload);
    }

    public static function recordDeadlinePenaltyPaid(Solution $solution, int $cost)
    {
        return static::recordForSolution(static::TYPE_DEADLINE_PENALTY_PAID, $solution, [
            'cost' => $cost,
        ]);
    }

    public static function recordGeekPasteAttemptBought(Task $task, Course $course, User $user, int $cost, ?string $petName = null, ?string $petKey = null)
    {
        $task->loadMissing('step.lesson');
        $step = $task->step;
        $lesson = $step ? $step->lesson : null;
        $payload = static::basePayload($course, $lesson, $task) + [
            'cost' => $cost,
        ];

        if ($petName) {
            $payload['pet_name'] = $petName;
        }

        if ($petKey) {
            $payload['pet_key'] = $petKey;
        }

        return static::recordActivity([
            'course_id' => $course->id,
            'lesson_id' => $lesson ? $lesson->id : null,
            'step_id' => $step ? $step->id : null,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => static::TYPE_GEEKPASTE_ATTEMPT_BOUGHT,
            'payload' => $payload,
        ]);
    }

    public static function recordEarlyAccessBought(Course $course, Lesson $lesson, User $user, int $cost, ?string $petName = null, ?string $petKey = null)
    {
        $payload = static::basePayload($course, $lesson) + [
            'cost' => $cost,
        ];

        if ($petName) {
            $payload['pet_name'] = $petName;
        }

        if ($petKey) {
            $payload['pet_key'] = $petKey;
        }

        return static::recordActivity([
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'type' => static::TYPE_EARLY_ACCESS_BOUGHT,
            'payload' => $payload,
        ]);
    }

    public static function recordPetActionForActiveCourse(User $user, string $action, array $payload = [])
    {
        if (!$user->exists) {
            return null;
        }

        $course = static::activePulseCourseForUser($user);

        return static::recordActivity([
            'course_id' => $course ? $course->id : null,
            'user_id' => $user->id,
            'type' => static::TYPE_PET_ACTION,
            'payload' => static::basePayload($course) + $payload + [
                'pet_action' => $action,
            ],
        ]);
    }

    public static function recordMarketPurchaseForActiveCourse(User $user, MarketGood $good, int $price, string $source = 'purchase', ?MarketDeal $deal = null)
    {
        $course = static::activePulseCourseForUser($user);

        $isAuction = $source === 'auction';

        return static::recordActivity([
            'course_id' => $course ? $course->id : null,
            'user_id' => $user->id,
            'type' => $isAuction ? static::TYPE_MARKET_AUCTION_WON : static::TYPE_MARKET_PURCHASE,
            'payload' => static::basePayload($course) + [
                'good_id' => $good->id,
                'good_name' => $good->name,
                'price' => $price,
                'source' => $source,
                'deal_id' => $deal ? $deal->id : null,
            ],
        ]);
    }

    public static function recordDigitalPurchaseForActiveCourse(User $user, array $item)
    {
        $course = static::activePulseCourseForUser($user);

        return static::recordActivity([
            'course_id' => $course ? $course->id : null,
            'user_id' => $user->id,
            'type' => static::TYPE_MARKET_DIGITAL_PURCHASE,
            'payload' => static::basePayload($course) + [
                'item_key' => $item['key'] ?? null,
                'item_name' => $item['name'] ?? 'цифровой товар',
                'price' => (int) ($item['cost'] ?? 0),
                'item_type' => $item['type'] ?? null,
            ],
        ]);
    }

    public static function recordRandomCoinDrop(User $user, int $amount, string $source = 'leprechaun')
    {
        return static::recordActivity([
            'course_id' => null,
            'user_id' => $user->id,
            'type' => static::TYPE_RANDOM_COIN_DROP,
            'payload' => [
                'amount' => $amount,
                'source' => $source,
            ],
        ]);
    }

    public static function recordLearningStreak(User $user, int $days, Carbon $date)
    {
        if ($days < 3) {
            return null;
        }

        if (static::where('user_id', $user->id)
            ->where('type', static::TYPE_LEARNING_STREAK)
            ->whereDate('created_at', $date->toDateString())
            ->exists()) {
            return null;
        }

        $course = static::activePulseCourseForUser($user);

        return static::recordActivity([
            'course_id' => $course ? $course->id : null,
            'user_id' => $user->id,
            'type' => static::TYPE_LEARNING_STREAK,
            'payload' => static::basePayload($course) + [
                'days' => $days,
                'date' => $date->toDateString(),
            ],
        ]);
    }

    public static function recordXpMilestones(User $user)
    {
        $score = (int) $user->score();
        $milestones = [1000, 5000, 10000, 25000, 50000];
        $course = static::activePulseCourseForUser($user);
        $recorded = collect();

        foreach ($milestones as $milestone) {
            if ($score < $milestone) {
                continue;
            }

            $exists = static::where('user_id', $user->id)
                ->where('type', static::TYPE_XP_MILESTONE)
                ->get(['payload'])
                ->contains(function ($activity) use ($milestone) {
                    return (int) ($activity->payload['milestone'] ?? 0) === $milestone;
                });

            if ($exists) {
                continue;
            }

            $recorded->push(static::recordActivity([
                'course_id' => $course ? $course->id : null,
                'user_id' => $user->id,
                'type' => static::TYPE_XP_MILESTONE,
                'payload' => static::basePayload($course) + [
                    'milestone' => $milestone,
                    'score' => $score,
                ],
            ]));
        }

        return $recorded->filter();
    }

    public static function recordAuctionLeadingBidForActiveCourse(User $user, MarketGood $good, int $amount)
    {
        $course = static::activePulseCourseForUser($user);

        return static::recordActivity([
            'course_id' => $course ? $course->id : null,
            'user_id' => $user->id,
            'type' => static::TYPE_AUCTION_LEADING_BID,
            'payload' => static::basePayload($course) + [
                'good_id' => $good->id,
                'good_name' => $good->name,
                'amount' => $amount,
            ],
        ]);
    }

    public static function recordAuctionFinished(MarketGood $good, int $winnerCount)
    {
        return static::recordActivity([
            'course_id' => null,
            'type' => static::TYPE_AUCTION_FINISHED,
            'payload' => [
                'good_id' => $good->id,
                'good_name' => $good->name,
                'winner_count' => $winnerCount,
            ],
        ]);
    }

    public static function recordMarketOrderShipped(MarketDeal $deal, ?User $shipper = null)
    {
        $deal->loadMissing('user', 'good');
        $course = $deal->user ? static::activePulseCourseForUser($deal->user) : null;

        return static::recordActivity([
            'course_id' => $course ? $course->id : null,
            'user_id' => $deal->user_id,
            'type' => static::TYPE_MARKET_ORDER_SHIPPED,
            'payload' => static::basePayload($course) + [
                'deal_id' => $deal->id,
                'good_id' => $deal->good_id,
                'good_name' => $deal->good ? $deal->good->name : 'товар',
                'price' => $deal->displayPrice(),
                'shipper_id' => $shipper ? $shipper->id : null,
            ],
        ]);
    }

    public static function recordTaskCreated(Course $course, Task $task, User $teacher)
    {
        if ($task->is_hidden) {
            return null;
        }

        $task->loadMissing('step.lesson');
        $step = $task->step;
        $lesson = $step ? $step->lesson : null;

        return static::recordActivity([
            'course_id' => $course->id,
            'lesson_id' => $lesson ? $lesson->id : null,
            'step_id' => $step ? $step->id : null,
            'task_id' => $task->id,
            'user_id' => $teacher->id,
            'type' => static::TYPE_TASK_CREATED,
            'payload' => static::basePayload($course, $lesson, $task),
        ]);
    }

    public static function recordLessonCreated(Course $course, Lesson $lesson, User $teacher)
    {
        return static::recordActivity([
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'user_id' => $teacher->id,
            'type' => static::TYPE_LESSON_CREATED,
            'payload' => static::basePayload($course, $lesson),
        ]);
    }

    public static function recordProgressMilestones(Course $course, User $student, ?Lesson $lesson)
    {
        if (!$lesson || !$lesson->exists) {
            return collect();
        }

        $recorded = collect();
        $lesson->loadMissing('chapter');
        $lessonStats = LessonStudentStats::where('course_id', $course->id)
            ->where('lesson_id', $lesson->id)
            ->where('student_id', $student->id)
            ->first(['percent']);

        if ($lessonStats && (float) $lessonStats->percent >= 100 && !static::hasMilestone($course->id, $student->id, static::TYPE_LESSON_COMPLETED, $lesson->id)) {
            $recorded->push(static::recordActivity([
                'course_id' => $course->id,
                'lesson_id' => $lesson->id,
                'user_id' => $student->id,
                'type' => static::TYPE_LESSON_COMPLETED,
                'payload' => static::basePayload($course, $lesson),
            ]));
        }

        $chapter = $lesson->chapter;
        if ($chapter && static::chapterCompletedByStudent($course, $chapter, $student) && !static::hasChapterMilestone($course->id, $student->id, $chapter->id)) {
            $recorded->push(static::recordActivity([
                'course_id' => $course->id,
                'user_id' => $student->id,
                'type' => static::TYPE_CHAPTER_COMPLETED,
                'payload' => static::basePayload($course) + [
                    'chapter_id' => $chapter->id,
                    'chapter_name' => $chapter->name,
                ],
            ]));
        }

        return $recorded->filter();
    }

    public static function recordCourseDailySummary(Course $course, Carbon $date, int $solutionsCount, int $checkedCount, int $xpEarned)
    {
        if ($solutionsCount <= 0 && $checkedCount <= 0 && $xpEarned <= 0) {
            return null;
        }

        if (static::where('course_id', $course->id)
            ->where('type', static::TYPE_COURSE_DAILY_SUMMARY)
            ->whereDate('created_at', Carbon::now()->toDateString())
            ->exists()) {
            return null;
        }

        return static::recordActivity([
            'course_id' => $course->id,
            'type' => static::TYPE_COURSE_DAILY_SUMMARY,
            'payload' => static::basePayload($course) + [
                'date' => $date->toDateString(),
                'solutions_count' => $solutionsCount,
                'checked_count' => $checkedCount,
                'xp_earned' => $xpEarned,
            ],
        ]);
    }

    public static function recordTaskStruggle(Course $course, Task $task, int $count, string $kind = 'low_marks')
    {
        if ($count <= 0) {
            return null;
        }

        $task->loadMissing('step.lesson');
        $step = $task->step;
        $lesson = $step ? $step->lesson : null;

        if (static::where('course_id', $course->id)
            ->where('task_id', $task->id)
            ->where('type', static::TYPE_TASK_STRUGGLE)
            ->where('created_at', '>=', Carbon::now()->subHours(18))
            ->exists()) {
            return null;
        }

        return static::recordActivity([
            'course_id' => $course->id,
            'lesson_id' => $lesson ? $lesson->id : null,
            'step_id' => $step ? $step->id : null,
            'task_id' => $task->id,
            'type' => static::TYPE_TASK_STRUGGLE,
            'payload' => static::basePayload($course, $lesson, $task) + [
                'count' => $count,
                'kind' => $kind,
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
                'svg_icon' => $achievement->payload['svg_icon'] ?? null,
                'trophy_image' => $achievement->payload['trophy_image'] ?? null,
            ],
        ]);
    }

    public static function pulseForCourses($courseIds, ?Carbon $now = null)
    {
        $courseIds = collect($courseIds)->filter()->values();
        $now = $now ?: Carbon::now();

        $activities = static::query()
            ->select(['type', 'created_at'])
            ->where(function ($query) use ($courseIds) {
                if ($courseIds->isNotEmpty()) {
                    $query->whereIn('course_id', $courseIds)
                        ->orWhere(function ($query) {
                            $query->whereNull('course_id')
                                ->whereIn('type', static::globalPulseTypes());
                        });
                } else {
                    $query->whereNull('course_id')
                        ->whereIn('type', static::globalPulseTypes());
                }
            })
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

        if (in_array($type, [static::TYPE_MARKET_AUCTION_WON, static::TYPE_MARKET_DIGITAL_PURCHASE, static::TYPE_AUCTION_LEADING_BID, static::TYPE_AUCTION_FINISHED], true)) {
            $secondaryWave *= 1.2;
            $longTail *= 1.2;
        }

        if (in_array($type, [static::TYPE_LESSON_COMPLETED, static::TYPE_CHAPTER_COMPLETED, static::TYPE_COURSE_DAILY_SUMMARY, static::TYPE_LEARNING_STREAK, static::TYPE_XP_MILESTONE], true)) {
            $secondaryWave *= 1.3;
            $longTail *= 1.25;
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
            case static::TYPE_CHAPTER_COMPLETED:
                return 3.2;
            case static::TYPE_LESSON_COMPLETED:
                return 2.7;
            case static::TYPE_LEARNING_STREAK:
            case static::TYPE_XP_MILESTONE:
                return 2.6;
            case static::TYPE_PET_ACTION:
                return 2.2;
            case static::TYPE_COURSE_DAILY_SUMMARY:
                return 2.5;
            case static::TYPE_TASK_STRUGGLE:
                return 2.4;
            case static::TYPE_MARKET_AUCTION_WON:
                return 2.4;
            case static::TYPE_AUCTION_FINISHED:
                return 2.3;
            case static::TYPE_AUCTION_LEADING_BID:
            case static::TYPE_MARKET_ORDER_SHIPPED:
            case static::TYPE_TASK_CREATED:
            case static::TYPE_LESSON_CREATED:
            case static::TYPE_FIRST_DAILY_ACTION:
            case static::TYPE_FIRST_SOLUTION:
                return 2.1;
            case static::TYPE_MARKET_PURCHASE:
            case static::TYPE_MARKET_DIGITAL_PURCHASE:
            case static::TYPE_RANDOM_COIN_DROP:
                return 2.0;
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
        return !in_array($this->type, [
            static::TYPE_LESSON_OPENED,
            static::TYPE_COURSE_DAILY_SUMMARY,
            static::TYPE_TASK_STRUGGLE,
            static::TYPE_AUCTION_FINISHED,
        ], true);
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
                if (!empty($payload['pet_name'])) {
                    return 'усилил(а) решение с помощью питомца «' . $payload['pet_name'] . '»';
                }

                return 'усилил(а) решение бустером';
            case static::TYPE_DEADLINE_PENALTY_PAID:
                return 'снял(а) штраф за дедлайн';
            case static::TYPE_EARLY_ACCESS_BOUGHT:
                if (!empty($payload['pet_name']) && (int) ($payload['cost'] ?? 0) <= 0) {
                    return 'открыл(а) урок «' . $lessonName . '» раньше с помощью питомца «' . $payload['pet_name'] . '»';
                }

                return 'открыл(а) урок «' . $lessonName . '» раньше';
            case static::TYPE_LESSON_OPENED:
                return 'Открылся урок «' . $lessonName . '»';
            case static::TYPE_GEEKPASTE_ATTEMPT_BOUGHT:
                if (!empty($payload['pet_name']) && (int) ($payload['cost'] ?? 0) <= 0) {
                    return 'получил(а) бесплатную попытку GeekPaste от питомца «' . $payload['pet_name'] . '»';
                }

                return 'взял(а) ещё попытку GeekPaste';
            case static::TYPE_TASK_AI_SUMMARY:
                return 'опубликовал(а) пересказ «' . $taskName . '»';
            case static::TYPE_AI_ACHIEVEMENT_EARNED:
                return 'получил(а) достижение «' . ($payload['achievement_title'] ?? 'Сильное решение') . '»';
            case static::TYPE_PET_ACTION:
                return static::petActionText($payload);
            case static::TYPE_MARKET_PURCHASE:
                return 'купил(а) «' . ($payload['good_name'] ?? 'товар') . '»';
            case static::TYPE_MARKET_AUCTION_WON:
                return 'выиграл(а) аукцион «' . ($payload['good_name'] ?? 'товар') . '»';
            case static::TYPE_MARKET_DIGITAL_PURCHASE:
                return 'купил(а) цифровой товар «' . ($payload['item_name'] ?? 'товар') . '»';
            case static::TYPE_RANDOM_COIN_DROP:
                return 'получил(а) ' . max(1, (int) ($payload['amount'] ?? 3)) . ' GC от лепрекона';
            case static::TYPE_LESSON_COMPLETED:
                return 'завершил(а) урок «' . $lessonName . '»';
            case static::TYPE_CHAPTER_COMPLETED:
                return 'завершил(а) главу «' . ($payload['chapter_name'] ?? 'глава') . '»';
            case static::TYPE_COURSE_DAILY_SUMMARY:
                return 'Итоги дня: ' . (int) ($payload['solutions_count'] ?? 0) . ' решений, ' . (int) ($payload['checked_count'] ?? 0) . ' проверок';
            case static::TYPE_TASK_STRUGGLE:
                return 'сложное место в задаче «' . $taskName . '»';
            case static::TYPE_FIRST_DAILY_ACTION:
                return 'начал(а) заниматься сегодня';
            case static::TYPE_LEARNING_STREAK:
                return 'занимается ' . max(3, (int) ($payload['days'] ?? 3)) . ' дня подряд';
            case static::TYPE_FIRST_SOLUTION:
                return 'сдал(а) первое решение';
            case static::TYPE_XP_MILESTONE:
                return 'добрал(а)ся до ' . (int) ($payload['milestone'] ?? 0) . ' XP';
            case static::TYPE_AUCTION_LEADING_BID:
                return 'лидирует в аукционе «' . ($payload['good_name'] ?? 'товар') . '»';
            case static::TYPE_AUCTION_FINISHED:
                return 'Аукцион «' . ($payload['good_name'] ?? 'товар') . '» завершён';
            case static::TYPE_MARKET_ORDER_SHIPPED:
                return 'получил(а) товар «' . ($payload['good_name'] ?? 'товар') . '»';
            case static::TYPE_TASK_CREATED:
                return 'добавил(а) задачу «' . $taskName . '»';
            case static::TYPE_LESSON_CREATED:
                return 'добавил(а) урок «' . $lessonName . '»';
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

        if ($this->type === static::TYPE_PET_ACTION && !empty($payload['amount'])) {
            $parts[] = '+' . (int) $payload['amount'] . ' GC';
        }

        if (in_array($this->type, [static::TYPE_MARKET_PURCHASE, static::TYPE_MARKET_AUCTION_WON, static::TYPE_MARKET_DIGITAL_PURCHASE], true) && !empty($payload['price'])) {
            $parts[] = '-' . (int) $payload['price'] . ' GC';
        }

        if ($this->type === static::TYPE_RANDOM_COIN_DROP && !empty($payload['amount'])) {
            $parts[] = '+' . (int) $payload['amount'] . ' GC';
        }

        if ($this->type === static::TYPE_COURSE_DAILY_SUMMARY && !empty($payload['xp_earned'])) {
            $parts[] = '+' . (int) $payload['xp_earned'] . ' XP';
        }

        if ($this->type === static::TYPE_TASK_STRUGGLE && !empty($payload['count'])) {
            $parts[] = (int) $payload['count'] . ' попытки ниже максимума';
        }

        if (in_array($this->type, [static::TYPE_AUCTION_LEADING_BID, static::TYPE_AUCTION_FINISHED], true) && !empty($payload['amount'])) {
            $parts[] = (int) $payload['amount'] . ' GC';
        }

        if ($this->type === static::TYPE_AUCTION_FINISHED && !empty($payload['winner_count'])) {
            $parts[] = (int) $payload['winner_count'] . ' победителей';
        }

        if ($this->type === static::TYPE_XP_MILESTONE && !empty($payload['score'])) {
            $parts[] = (int) $payload['score'] . ' XP всего';
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
            case static::TYPE_PET_ACTION:
                return 'fas fa-paw';
            case static::TYPE_TASK_AI_SUMMARY:
                return 'fas fa-newspaper';
            case static::TYPE_AI_ACHIEVEMENT_EARNED:
                return Achievement::iconOptions()[$payload['icon_key'] ?? 'sparkles'] ?? 'fas fa-magic';
            case static::TYPE_MARKET_PURCHASE:
                return 'fas fa-shopping-bag';
            case static::TYPE_MARKET_AUCTION_WON:
                return 'fas fa-gavel';
            case static::TYPE_MARKET_DIGITAL_PURCHASE:
                return 'fas fa-store';
            case static::TYPE_RANDOM_COIN_DROP:
                return 'fas fa-rainbow';
            case static::TYPE_LESSON_COMPLETED:
                return 'fas fa-check-circle';
            case static::TYPE_CHAPTER_COMPLETED:
                return 'fas fa-flag-checkered';
            case static::TYPE_COURSE_DAILY_SUMMARY:
                return 'fas fa-chart-line';
            case static::TYPE_TASK_STRUGGLE:
                return 'fas fa-exclamation-circle';
            case static::TYPE_FIRST_DAILY_ACTION:
                return 'fas fa-sun';
            case static::TYPE_LEARNING_STREAK:
                return 'fas fa-fire';
            case static::TYPE_FIRST_SOLUTION:
                return 'fas fa-seedling';
            case static::TYPE_XP_MILESTONE:
                return 'fas fa-mountain';
            case static::TYPE_AUCTION_LEADING_BID:
                return 'fas fa-gavel';
            case static::TYPE_AUCTION_FINISHED:
                return 'fas fa-trophy';
            case static::TYPE_MARKET_ORDER_SHIPPED:
                return 'fas fa-box-open';
            case static::TYPE_TASK_CREATED:
                return 'fas fa-plus-circle';
            case static::TYPE_LESSON_CREATED:
                return 'fas fa-book-medical';
            default:
                return 'fas fa-magic';
        }
    }

    public function svgIcon(): ?string
    {
        $payload = $this->payload ?: [];

        if ($this->type !== static::TYPE_AI_ACHIEVEMENT_EARNED) {
            return null;
        }

        return Achievement::sanitizeSvgIcon($payload['svg_icon'] ?? null)
            ?: Achievement::svgForVisualKey($payload['visual_key'] ?? null);
    }

    public function toneClass()
    {
        switch ($this->type) {
            case static::TYPE_XP_BOOSTER_USED:
            case static::TYPE_GEEKPASTE_ATTEMPT_BOUGHT:
            case static::TYPE_PET_ACTION:
                return 'is-boost';
            case static::TYPE_SOLUTION_CHECKED:
                return 'is-score';
            case static::TYPE_EARLY_ACCESS_BOUGHT:
            case static::TYPE_LESSON_OPENED:
                return 'is-open';
            case static::TYPE_TASK_AI_SUMMARY:
                return 'is-summary';
            case static::TYPE_AI_ACHIEVEMENT_EARNED:
            case static::TYPE_LESSON_COMPLETED:
            case static::TYPE_CHAPTER_COMPLETED:
            case static::TYPE_LEARNING_STREAK:
            case static::TYPE_FIRST_SOLUTION:
            case static::TYPE_XP_MILESTONE:
                return 'is-achievement';
            case static::TYPE_COURSE_DAILY_SUMMARY:
            case static::TYPE_TASK_CREATED:
            case static::TYPE_LESSON_CREATED:
                return 'is-summary';
            case static::TYPE_TASK_STRUGGLE:
            case static::TYPE_AUCTION_FINISHED:
                return 'is-open';
            case static::TYPE_MARKET_PURCHASE:
            case static::TYPE_MARKET_AUCTION_WON:
            case static::TYPE_MARKET_DIGITAL_PURCHASE:
            case static::TYPE_RANDOM_COIN_DROP:
            case static::TYPE_AUCTION_LEADING_BID:
            case static::TYPE_MARKET_ORDER_SHIPPED:
            case static::TYPE_FIRST_DAILY_ACTION:
                return 'is-boost';
            default:
                return 'is-submit';
        }
    }

    public function url()
    {
        if (in_array($this->type, [static::TYPE_MARKET_PURCHASE, static::TYPE_MARKET_AUCTION_WON, static::TYPE_MARKET_DIGITAL_PURCHASE, static::TYPE_AUCTION_LEADING_BID, static::TYPE_AUCTION_FINISHED, static::TYPE_MARKET_ORDER_SHIPPED], true)) {
            return url('/insider/market');
        }

        if ($this->type === static::TYPE_PET_ACTION && !$this->course_id && $this->user_id) {
            return url('/insider/profile/' . $this->user_id . '#learning-avatar');
        }

        if ($this->type === static::TYPE_RANDOM_COIN_DROP && $this->user_id) {
            return url('/insider/profile/' . $this->user_id . '#gc-history');
        }

        if ($this->step_id && $this->task_id) {
            if ($this->type === static::TYPE_AI_ACHIEVEMENT_EARNED && $this->user_id) {
                return url('/insider/profile/' . $this->user_id . '#achievements');
            }

            return url('/insider/courses/' . $this->course_id . '/steps/' . $this->step_id . '#task' . $this->task_id);
        }

        return url('/insider/courses/' . $this->course_id);
    }

    private static function petActionText(array $payload): string
    {
        $petName = trim((string) ($payload['pet_name'] ?? 'питомец'));
        $quotedPetName = $petName !== '' && $petName !== 'питомец' ? ' «' . $petName . '»' : '';
        $amount = (int) ($payload['amount'] ?? 0);

        switch ($payload['pet_action'] ?? null) {
            case 'daily_coin_gift':
                return 'получил(а) ' . max(1, $amount ?: 3) . ' GC от питомца' . $quotedPetName;
            case 'daily_big_coin_gift':
                return 'получил(а) ' . max(1, $amount ?: 7) . ' GC от питомца' . $quotedPetName;
            case 'free_xp_booster_gift':
                return 'получил(а) бесплатный XP-бустер от питомца' . $quotedPetName;
            default:
                return 'получил(а) помощь от питомца' . $quotedPetName;
        }
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

    private static function recordFirstDailyAction(Solution $solution)
    {
        $solution->loadMissing('task.step.lesson', 'course');
        $submittedAt = $solution->submittedAt();

        if (static::where('user_id', $solution->user_id)
            ->where('type', static::TYPE_FIRST_DAILY_ACTION)
            ->whereDate('created_at', $submittedAt->toDateString())
            ->exists()) {
            return null;
        }

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
            'type' => static::TYPE_FIRST_DAILY_ACTION,
            'payload' => static::basePayload($course, $lesson, $task),
        ]);
    }

    private static function recordFirstSolution(Solution $solution)
    {
        $submittedCount = Solution::where('user_id', $solution->user_id)
            ->whereNotNull('submitted')
            ->count();

        if ($submittedCount !== 1) {
            return null;
        }

        return static::recordForSolution(static::TYPE_FIRST_SOLUTION, $solution);
    }

    private static function hasMilestone(int $courseId, int $studentId, string $type, ?int $lessonId = null): bool
    {
        return static::where('course_id', $courseId)
            ->where('user_id', $studentId)
            ->where('type', $type)
            ->when($lessonId !== null, function ($query) use ($lessonId) {
                $query->where('lesson_id', $lessonId);
            })
            ->exists();
    }

    private static function hasChapterMilestone(int $courseId, int $studentId, int $chapterId): bool
    {
        return static::where('course_id', $courseId)
            ->where('user_id', $studentId)
            ->where('type', static::TYPE_CHAPTER_COMPLETED)
            ->get(['payload'])
            ->contains(function ($activity) use ($chapterId) {
                return (int) ($activity->payload['chapter_id'] ?? 0) === $chapterId;
            });
    }

    private static function chapterCompletedByStudent(Course $course, ProgramChapter $chapter, User $student): bool
    {
        $lessonIds = Lesson::where('chapter_id', $chapter->id)
            ->with('info')
            ->get()
            ->filter(function ($lesson) use ($course) {
                return $lesson->isStarted($course);
            })
            ->pluck('id')
            ->values();

        if ($lessonIds->isEmpty()) {
            return false;
        }

        $stats = LessonStudentStats::where('course_id', $course->id)
            ->where('student_id', $student->id)
            ->whereIn('lesson_id', $lessonIds)
            ->get(['lesson_id', 'percent'])
            ->keyBy('lesson_id');

        if ($stats->count() < $lessonIds->count()) {
            return false;
        }

        return $stats->min('percent') >= 100;
    }

    private static function basePayload(?Course $course, ?Lesson $lesson = null, ?Task $task = null)
    {
        return [
            'course_name' => $course ? $course->name : null,
            'lesson_name' => $lesson ? $lesson->name : null,
            'task_name' => $task ? $task->name : null,
        ];
    }

    private static function activePulseCourseForUser(User $user)
    {
        $course = $user->courses()
            ->where('state', 'started')
            ->orderByDesc('courses.id')
            ->first(['courses.id', 'courses.name']);

        if ($course) {
            return $course;
        }

        if ($user->role === 'teacher' || $user->role === 'admin') {
            $course = $user->managed_courses()
                ->where('state', 'started')
                ->orderByDesc('courses.id')
                ->first(['courses.id', 'courses.name']);
        }

        if ($course) {
            return $course;
        }

        if ($user->role === 'admin') {
            return Course::where('state', 'started')
                ->orderByDesc('id')
                ->first(['id', 'name']);
        }

        return null;
    }

    public static function globalPulseTypes(): array
    {
        return [
            static::TYPE_PET_ACTION,
            static::TYPE_LEARNING_STREAK,
            static::TYPE_XP_MILESTONE,
            static::TYPE_MARKET_PURCHASE,
            static::TYPE_MARKET_AUCTION_WON,
            static::TYPE_MARKET_DIGITAL_PURCHASE,
            static::TYPE_RANDOM_COIN_DROP,
            static::TYPE_AUCTION_LEADING_BID,
            static::TYPE_AUCTION_FINISHED,
            static::TYPE_MARKET_ORDER_SHIPPED,
        ];
    }
}
