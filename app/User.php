<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $score = null;
    protected $rank = null;
    protected $cached_data = [];

    protected $fillable = [
        'name', 'email', 'password', 'role', 'school', 'grade_year', 'birthday',
        'hobbies', 'interests', 'git', 'telegram', 'telegram_chat_id', 'telegram_link_token', 'telegram_link_token_expires_at', 'custom_title', 'custom_title_expires_at', 'avatar_frame', 'avatar_frame_expires_at', 'comments', 'letter', 'email_verified_at', 'last_login_at',
        'last_login_ip'
    ];
    protected $casts = [
        'birthday' => 'datetime',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'telegram_link_token_expires_at' => 'datetime',
        'custom_title_expires_at' => 'datetime',
        'avatar_frame_expires_at' => 'datetime',
    ];

    protected $prerequisite_cache = [];

    public function imageUrl(): string
    {
        if ($this->image && Storage::exists($this->image)) {
            return url('/media/' . $this->image);
        }

        return url('images/user.jpg');
    }

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function managed_courses()
    {
        return $this->belongsToMany('App\Course', 'course_teachers', 'user_id', 'course_id');
    }

    public function solutions()
    {
        return $this->hasMany('App\Solution', 'user_id', 'id');
    }

    public function courses()
    {
        return $this->belongsToMany('App\Course', 'course_students', 'user_id', 'course_id');
    }

    public function completedCourses()
    {
        return $this->hasMany('App\CompletedCourse', 'user_id', 'id');
    }

    public function submissions()
    {
        return $this->hasMany('App\Solution', 'user_id', 'id');
    }

    public function manual_rank()
    {
        return $this->hasOne('App\Rank', 'id', 'rank_id');
    }

    public function grade()
    {
        $current_year = Carbon::now()->year;
        $date = Carbon::now();
        if ($date->lt(Carbon::createFromDate($current_year, 6, 1))) {
                return $current_year - $this->grade_year;
        } else {
            return $current_year - $this->grade_year + 1;
        }
    }

    public function setGrade($grade)
    {
        $current_year = Carbon::now()->year;
        $date = Carbon::now();
        if ($date->lt(Carbon::createFromDate($current_year, 6, 1))) {
            $this->grade_year = $current_year - $grade;
        } else {
            $this->grade_year = $current_year - $grade + 1;
        }
    }

    public function rescore()
    {
        $this->score = null;
        $this->rank = null;
        Cache::forget("user:{$this->id}:score");
        Cache::forget("user:{$this->id}:rank");
        Cache::forget("user:{$this->id}:stickers");
        Cache::forget("user:{$this->id}:sticker_descriptions");
    }

    public function score()
    {
        if ($this->score != null)
            return $this->score;
        
        $cacheKey = "user:{$this->id}:score";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $this->score = $cached;
        }

        if ($this->rank_id != null) {
            $this->score = $this->manual_rank->to - 1;
            Cache::put($cacheKey, $this->score, 3600);
            return $this->score;
        }

        $this->score = (int) Solution::where('user_id', $this->id)
            ->whereNotNull('mark')
            ->selectRaw('MAX(mark) as best_mark')
            ->groupBy('task_id')
            ->pluck('best_mark')
            ->sum();

        // Calculate scores from completed courses
        $markScores = [
            'S' => 2000, 'A+' => 1500, 'A' => 1200, 'A-' => 1000,
            'B+' => 800, 'B' => 600, 'B-' => 400,
            'C+' => 300, 'C' => 200, 'C-' => 100,
            'D+' => 50, 'D' => 50, 'D-' => 50
        ];

        $completedCourseMarks = $this->completedCourses()
            ->selectRaw('mark, COUNT(*) as courses_count')
            ->groupBy('mark')
            ->pluck('courses_count', 'mark');

        foreach ($completedCourseMarks as $mark => $coursesCount) {
            $this->score += ($markScores[$mark] ?? 600) * $coursesCount;
        }

        Cache::put($cacheKey, $this->score, 3600);
        return $this->score;
    }

    public function setComputedScore($score)
    {
        $this->score = (int) $score;
        return $this;
    }

    public function setComputedRank($rank)
    {
        $this->rank = $rank;
        return $this;
    }

    public function rank()
    {
        if ($this->rank != null)
            return $this->rank;
            
        if ($this->manual_rank != null) {
            return $this->rank = $this->manual_rank;
        }
        
        $cacheKey = "user:{$this->id}:rank";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $this->rank = $cached;
        }
        
        $score = $this->score();
        $this->rank = Rank::where('from', '<=', $score)->where('to', '>', $score)->first();

        if (!$this->rank) {
            \Log::info("Not found rank for user " . $this->id . " score " . $score);
            $this->rank = Rank::first();
        }

        Cache::put($cacheKey, $this->rank, 3600);
        return $this->rank;
    }

    public function rankPosition($rank = null)
    {
        $rank = $rank ?: $this->rank();

        if (!$rank) {
            return 0;
        }

        return (int) Rank::where('from', '<=', $rank->from)->count();
    }

    public function awardRankPromotionIfNeeded($oldRank)
    {
        $newRank = $this->rank();

        if (!$oldRank || !$newRank || $oldRank->id == $newRank->id) {
            return false;
        }

        \Illuminate\Support\Facades\Notification::send($this, (new \App\Notifications\NewRank())->delay(Carbon::now()->addSeconds(1)));

        if ($newRank->from <= $oldRank->from) {
            return true;
        }

        $coins = 5 * $this->rankPosition($newRank);
        CoinTransaction::registerOnce(
            $this->id,
            $coins,
            'Rank bonus #' . $newRank->id,
            '🌟 Бонус за новое звание: +' . $coins . ' GC',
            'success',
            'fas fa-arrow-up'
        );

        return true;
    }

    public function transactions()
    {
        return $this->hasMany('App\CoinTransaction', 'user_id', 'id');
    }

    public function balance()
    {
        return $this->transactions()->sum('price');
    }

    public function customTitleCost()
    {
        return 30;
    }

    public function customTitleDurationDays()
    {
        return 14;
    }

    public function hasActiveCustomTitle()
    {
        return $this->custom_title
            && $this->custom_title_expires_at
            && $this->custom_title_expires_at->isFuture();
    }

    public function activeCustomTitle()
    {
        return $this->hasActiveCustomTitle() ? $this->custom_title : null;
    }

    public static function avatarFrames()
    {
        return [
            'neon' => [
                'name' => 'Неон',
                'description' => 'Холодное сияние для ночного кодинга.',
                'cost' => 30,
                'days' => 30,
                'icon' => 'fas fa-bolt',
            ],
            'pixel' => [
                'name' => 'Пиксель',
                'description' => 'Ретро-рамка в духе 8-битных игр.',
                'cost' => 35,
                'days' => 30,
                'icon' => 'fas fa-th-large',
            ],
            'terminal' => [
                'name' => 'Терминал',
                'description' => 'Темная рамка с зеленым контуром.',
                'cost' => 45,
                'days' => 30,
                'icon' => 'fas fa-terminal',
            ],
            'gold' => [
                'name' => 'Золото',
                'description' => 'Спокойный премиальный контур.',
                'cost' => 90,
                'days' => 30,
                'icon' => 'fas fa-crown',
            ],
            'rainbow' => [
                'name' => 'Радуга',
                'description' => 'Анимированная рамка с мягким движением.',
                'cost' => 120,
                'days' => 30,
                'icon' => 'fas fa-rainbow',
            ],
        ];
    }

    public function hasActiveAvatarFrame()
    {
        return $this->avatar_frame
            && $this->avatar_frame_expires_at
            && $this->avatar_frame_expires_at->isFuture()
            && array_key_exists($this->avatar_frame, self::avatarFrames());
    }

    public function activeAvatarFrame()
    {
        return $this->hasActiveAvatarFrame() ? $this->avatar_frame : null;
    }

    public function activeAvatarFrameConfig()
    {
        $frame = $this->activeAvatarFrame();

        return $frame ? self::avatarFrames()[$frame] : null;
    }

    public function avatarFrameCost($frame)
    {
        return self::avatarFrames()[$frame]['cost'] ?? null;
    }

    public function avatarFrameDurationDays($frame)
    {
        return self::avatarFrames()[$frame]['days'] ?? 30;
    }

    public function getHtmlTransactions()
    {
        $html = "<strong>История начислений GC</strong><ul>";

        foreach ($this->transactions as $transaction) {
            $html .= '<li><strong>' . $transaction->price . '</strong> - ' . $transaction->comment . '</li>';
        }
        $html .= "</ul>";

        return $html;
    }

    public function goods()
    {
        return $this->belongsToMany('App\MarketGood', 'market_deals', 'user_id', 'good_id');
    }

    public function orders()
    {
        return $this->hasMany('App\MarketDeal', 'user_id', 'id');
    }

    public function isBirthday()
    {
        if (!$this->birthday) return false;
        return $this->birthday->format('d.m') == Carbon::now()->format('d.m');
    }

    /**
     * Get user's stickers with caching.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getStickers()
    {
        return Cache::remember("user:{$this->id}:stickers", 60, function () {
            $lessonIds = LessonStudentStats::where('student_id', $this->id)
                ->where('percent', '>', 90)
                ->pluck('lesson_id')
                ->unique();

            if ($lessonIds->isEmpty()) {
                return collect([]);
            }

            return Lesson::whereIn('id', $lessonIds)
                ->whereNotNull('sticker')
                ->pluck('sticker')
                ->filter()
                ->unique()
                ->values();
        });
    }

    /**
     * Get sticker descriptions with caching.
     *
     * @return array
     */
    public function getStickerDescriptions()
    {
        return Cache::remember("user:{$this->id}:sticker_descriptions", 60, function () {
            $lessonIds = LessonStudentStats::where('student_id', $this->id)
                ->where('percent', '>', 90)
                ->pluck('lesson_id')
                ->unique();

            if ($lessonIds->isEmpty()) {
                return [];
            }

            return Lesson::whereIn('id', $lessonIds)
                ->whereNotNull('sticker')
                ->get(['name', 'sticker'])
                ->filter(function ($lesson) {
                    return $lesson->sticker;
                })
                ->mapWithKeys(function ($lesson) {
                    return [$lesson->sticker => $lesson->name];
                })
                ->all();
        });
    }

}
