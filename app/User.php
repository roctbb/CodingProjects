<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        'name', 'gender', 'email', 'password', 'role', 'school', 'grade_year', 'birthday',
        'hobbies', 'interests', 'git', 'telegram', 'telegram_chat_id', 'telegram_link_token', 'telegram_link_token_expires_at', 'custom_title', 'custom_title_expires_at', 'avatar_frame', 'avatar_frame_config', 'avatar_frame_expires_at', 'comments', 'letter', 'email_verified_at', 'last_login_at',
        'learning_avatar_config', 'last_login_ip'
    ];
    protected $casts = [
        'birthday' => 'datetime',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'telegram_link_token_expires_at' => 'datetime',
        'custom_title_expires_at' => 'datetime',
        'avatar_frame_config' => 'array',
        'learning_avatar_config' => 'array',
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
        $grade = max(1, (int) $grade);
        $current_year = Carbon::now()->year;
        $date = Carbon::now();
        if ($date->lt(Carbon::createFromDate($current_year, 6, 1))) {
            $this->grade_year = $current_year - $grade;
        } else {
            $this->grade_year = $current_year - $grade + 1;
        }
    }

    public function gradeLabel(): string
    {
        $grade = (int) $this->grade();

        return $grade >= 12 ? 'Выпускник' : (string) $grade;
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

    public function customAvatarFrameCost($animated = true)
    {
        return $animated ? 150 : 100;
    }

    public function customAvatarFrameDurationDays()
    {
        return 30;
    }

    public static function customAvatarFrameDefaults()
    {
        return [
            'type' => 'linear',
            'shape' => 'circle',
            'motion' => 'spin',
            'pattern' => 'sparkles',
            'avatar_effect' => 'sheen',
            'angle' => 135,
            'width' => 6,
            'glow' => 28,
            'speed' => 100,
            'pattern_opacity' => 72,
            'effect_opacity' => 70,
            'animated' => true,
            'colors' => ['#22d3ee', '#8b5cf6', '#f97316', '#22c55e'],
        ];
    }

    public static function sanitizeCustomAvatarFrameConfig($config)
    {
        $defaults = self::customAvatarFrameDefaults();
        $config = is_array($config) ? $config : [];
        $type = in_array($config['type'] ?? null, ['linear', 'conic', 'radial'], true)
            ? $config['type']
            : $defaults['type'];
        $shape = in_array($config['shape'] ?? null, ['circle', 'squircle', 'badge', 'soft'], true)
            ? $config['shape']
            : $defaults['shape'];
        $motion = in_array($config['motion'] ?? null, ['spin', 'pulse', 'sweep', 'still'], true)
            ? $config['motion']
            : $defaults['motion'];
        $pattern = in_array($config['pattern'] ?? null, ['none', 'sparkles', 'pixels', 'stripes'], true)
            ? $config['pattern']
            : $defaults['pattern'];
        $avatarEffect = in_array($config['avatar_effect'] ?? null, ['none', 'sheen', 'scanner', 'spark'], true)
            ? $config['avatar_effect']
            : $defaults['avatar_effect'];

        $colors = collect($config['colors'] ?? [])
            ->filter(function ($color) {
                return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color);
            })
            ->map(function ($color) {
                return strtolower($color);
            })
            ->take(4)
            ->values()
            ->all();

        if (count($colors) < 2) {
            $colors = $defaults['colors'];
        }

        return [
            'type' => $type,
            'shape' => $shape,
            'motion' => $motion,
            'pattern' => $pattern,
            'avatar_effect' => $avatarEffect,
            'angle' => max(0, min(359, (int) ($config['angle'] ?? $defaults['angle']))),
            'width' => max(2, min(14, (int) ($config['width'] ?? $defaults['width']))),
            'glow' => max(0, min(52, (int) ($config['glow'] ?? $defaults['glow']))),
            'speed' => max(60, min(180, (int) ($config['speed'] ?? $defaults['speed']))),
            'pattern_opacity' => max(20, min(100, (int) ($config['pattern_opacity'] ?? $defaults['pattern_opacity']))),
            'effect_opacity' => max(20, min(100, (int) ($config['effect_opacity'] ?? $defaults['effect_opacity']))),
            'animated' => filter_var($config['animated'] ?? $defaults['animated'], FILTER_VALIDATE_BOOLEAN),
            'colors' => $colors,
        ];
    }

    public function hasActiveAvatarFrame()
    {
        if (!$this->avatar_frame || !$this->avatar_frame_expires_at || !$this->avatar_frame_expires_at->isFuture()) {
            return false;
        }

        if ($this->avatar_frame === 'custom') {
            return is_array($this->avatar_frame_config) && count($this->avatar_frame_config);
        }

        return array_key_exists($this->avatar_frame, self::avatarFrames());
    }

    public function activeAvatarFrame()
    {
        return $this->hasActiveAvatarFrame() ? $this->avatar_frame : null;
    }

    public function activeAvatarFrameConfig()
    {
        $frame = $this->activeAvatarFrame();

        if (!$frame) {
            return null;
        }

        if ($frame === 'custom') {
            return [
                'name' => 'Своя рамка',
                'description' => 'Кастомный градиент из мастерской.',
                'cost' => $this->customAvatarFrameCost(),
                'days' => $this->customAvatarFrameDurationDays(),
                'icon' => 'fas fa-palette',
                'config' => self::sanitizeCustomAvatarFrameConfig($this->avatar_frame_config),
            ];
        }

        return self::avatarFrames()[$frame];
    }

    public function avatarFrameStyle()
    {
        if ($this->activeAvatarFrame() !== 'custom') {
            return null;
        }

        $config = self::sanitizeCustomAvatarFrameConfig($this->avatar_frame_config);
        $colors = $config['colors'];
        $colorList = implode(', ', $colors);

        if ($config['type'] === 'conic') {
            $gradient = 'conic-gradient(from ' . $config['angle'] . 'deg, ' . $colorList . ', ' . $colors[0] . ')';
        } elseif ($config['type'] === 'radial') {
            $gradient = 'radial-gradient(circle, ' . $colorList . ')';
        } else {
            $gradient = 'linear-gradient(' . $config['angle'] . 'deg, ' . $colorList . ')';
        }

        $shadowColor = $colors[min(1, count($colors) - 1)];
        $animationDuration = function ($seconds) use ($config) {
            return round($seconds * 100 / $config['speed'], 2) . 's';
        };
        $frameAnimations = [
            'spin' => 'profile-avatar-rainbow-spin ' . $animationDuration(5.6) . ' linear infinite',
            'pulse' => 'profile-avatar-frame-pulse ' . $animationDuration(2.8) . ' ease-in-out infinite',
            'sweep' => 'profile-avatar-frame-sweep ' . $animationDuration(3.4) . ' linear infinite',
            'still' => 'none',
        ];
        $shapeStyles = [
            'circle' => ['999px', '999px'],
            'squircle' => ['1.35rem', '1.1rem'],
            'badge' => ['1.6rem 0.8rem', '1.25rem 0.62rem'],
            'soft' => ['1rem', '0.8rem'],
        ];
        $patternStyles = [
            'none' => 'none',
            'sparkles' => 'radial-gradient(circle at 50% 6%, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0.16rem, transparent 0.19rem), radial-gradient(circle at 94% 34%, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0.13rem, transparent 0.17rem), radial-gradient(circle at 76% 94%, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0.15rem, transparent 0.18rem), radial-gradient(circle at 9% 66%, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0.12rem, transparent 0.16rem), radial-gradient(circle at 13% 18%, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0.11rem, transparent 0.15rem)',
            'pixels' => 'linear-gradient(90deg, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 50%, transparent 0) 0 0 / 0.55rem 0.55rem, linear-gradient(rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 50%, transparent 0) 0 0 / 0.55rem 0.55rem',
            'stripes' => 'repeating-linear-gradient(135deg, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0 0.16rem, transparent 0.16rem 0.48rem)',
        ];
        $avatarEffectStyles = [
            'none' => ['transparent', 'none'],
            'sheen' => ['linear-gradient(115deg, transparent 18%, rgba(255, 255, 255, 0.62) 45%, transparent 72%)', 'profile-avatar-sheen ' . $animationDuration(3.6) . ' ease-in-out infinite'],
            'scanner' => ['linear-gradient(180deg, transparent 28%, rgba(34, 211, 238, 0.52) 50%, transparent 72%)', 'profile-avatar-scanner ' . $animationDuration(2.8) . ' ease-in-out infinite'],
            'spark' => ['radial-gradient(circle at 25% 35%, rgba(255, 255, 255, 0.86) 0 0.12rem, transparent 0.14rem), radial-gradient(circle at 68% 58%, rgba(255, 255, 255, 0.72) 0 0.1rem, transparent 0.12rem)', 'profile-avatar-spark ' . $animationDuration(2.4) . ' ease-in-out infinite'],
        ];
        $animation = $config['animated'] ? $frameAnimations[$config['motion']] : 'none';
        $shape = $shapeStyles[$config['shape']];
        $avatarEffect = $avatarEffectStyles[$config['avatar_effect']];
        $avatarEffectOpacity = $config['animated'] && $config['avatar_effect'] !== 'none'
            ? round($config['effect_opacity'] / 100, 2)
            : 0;

        return '--profile-avatar-frame-bg: ' . $gradient . ';'
            . '--profile-avatar-frame-width: ' . $config['width'] . 'px;'
            . '--profile-avatar-frame-radius: ' . $shape[0] . ';'
            . '--profile-avatar-frame-inner-radius: ' . $shape[1] . ';'
            . '--profile-avatar-frame-pattern: ' . $patternStyles[$config['pattern']] . ';'
            . '--profile-avatar-pattern-opacity: ' . $config['pattern_opacity'] . '%;'
            . '--profile-avatar-frame-shadow: 0 0 0 0.12rem color-mix(in srgb, ' . $colors[0] . ' 18%, transparent), 0 0.8rem 2rem color-mix(in srgb, ' . $shadowColor . ' ' . $config['glow'] . '%, transparent);'
            . '--profile-avatar-frame-animation: ' . $animation . ';'
            . '--profile-avatar-effect-bg: ' . $avatarEffect[0] . ';'
            . '--profile-avatar-effect-animation: ' . ($config['animated'] ? $avatarEffect[1] : 'none') . ';'
            . '--profile-avatar-effect-opacity: ' . $avatarEffectOpacity . ';';
    }

    public function avatarFrameCost($frame)
    {
        if ($frame === 'custom') {
            return $this->customAvatarFrameCost();
        }

        return self::avatarFrames()[$frame]['cost'] ?? null;
    }

    public function avatarFrameDurationDays($frame)
    {
        if ($frame === 'custom') {
            return $this->customAvatarFrameDurationDays();
        }

        return self::avatarFrames()[$frame]['days'] ?? 30;
    }

    public static function learningAvatarManifests()
    {
        return [
            'room-system' => [
                'name' => 'Комната программиста',
                'path' => 'images/avatar-layers/room-system/config.json',
            ],
        ];
    }

    public static function learningAvatarItemCatalog()
    {
        return [
            'basic_laptop' => [
                'name' => 'Ноутбук',
                'description' => 'Стартовый рабочий стол.',
                'icon' => 'fas fa-laptop-code',
                'cost' => 0,
            ],
            'premium_keyboard' => [
                'name' => 'Механическая клавиатура',
                'description' => 'Премиальный аксессуар для быстрых решений.',
                'icon' => 'fas fa-keyboard',
                'cost' => 45,
            ],
            'code_mug' => [
                'name' => 'Кружка с кодом',
                'description' => 'Небольшой уютный предмет на рабочий стол.',
                'icon' => 'fas fa-mug-hot',
                'cost' => 25,
            ],
            'desk_plant' => [
                'name' => 'Растение',
                'description' => 'Добавляет в комнату немного живого спокойствия.',
                'icon' => 'fas fa-seedling',
                'cost' => 35,
            ],
            'learning_bot_pet' => [
                'name' => 'Робопес',
                'description' => 'Маленький робот-собака. Раз в день может бесплатно сбросить лимит GeekPaste-попыток, если выбран в комнате.',
                'icon' => 'fas fa-dog',
                'cost' => 90,
            ],
            'cheremsha_pet' => [
                'name' => 'Черемша',
                'description' => 'Пушистый ушастый компаньон. С шансом 30% раз в день приносит немного GC.',
                'icon' => 'fas fa-cat',
                'cost' => 120,
            ],
            'brr_brr_patapim_pet' => [
                'name' => 'Брр Брр Потапим',
                'description' => 'Лесной мем-компаньон с корнями и большим настроением. Позволяет бесплатно открывать раньше срока любые уроки, где включен ранний доступ.',
                'icon' => 'fas fa-tree',
                'cost' => 260,
            ],
            'tralalelo_tralala_pet' => [
                'name' => 'Тралалело Тралала',
                'description' => 'Акула в кроссовках для быстрых рывков по курсу. Увеличивает награду GC за задачи на 10%, пока выбрана в комнате.',
                'icon' => 'fas fa-fish',
                'cost' => 300,
            ],
            'tung_tung_tung_sahur_pet' => [
                'name' => 'Тунг Тунг Тунг Сахур',
                'description' => 'Деревянный ночной страж с битой. Раз в день автоматически бесплатно применяет XP-бустер к подходящему решению.',
                'icon' => 'fas fa-drum',
                'cost' => 240,
            ],
        ];
    }

    public static function learningAvatarPetAbilities()
    {
        return [
            'learning_bot_pet' => [
                'daily_free_geekpaste_reset',
            ],
            'cheremsha_pet' => [
                'daily_coin_gift',
            ],
            'brr_brr_patapim_pet' => [
                'early_lesson_unlock',
            ],
            'tralalelo_tralala_pet' => [
                'task_coin_bonus_10',
            ],
            'tung_tung_tung_sahur_pet' => [
                'daily_auto_xp_booster',
            ],
        ];
    }

    public static function learningAvatarPetAbilityDescriptions()
    {
        return [
            'free_xp_booster_gift' => 'с шансом 15% раз в день дарит бесплатный XP-бустер',
            'daily_free_geekpaste_reset' => 'позволяет раз в день бесплатно сбросить лимит GeekPaste-попыток',
            'daily_coin_gift' => 'с шансом 30% раз в день приносит 3 GC',
            'daily_big_coin_gift' => 'с шансом 15% раз в день приносит 7 GC',
            'task_coin_bonus_10' => 'увеличивает награду GC за задачи на 10%',
            'early_lesson_unlock' => 'позволяет бесплатно открывать раньше срока любые уроки с ранним доступом',
            'daily_auto_xp_booster' => 'раз в день автоматически бесплатно применяет XP-бустер к подходящему решению',
        ];
    }

    public static function learningAvatarPetDailyActionChance($petKey): int
    {
        $chances = [
            'cheremsha_pet' => 30,
        ];

        return $chances[$petKey] ?? 15;
    }

    public static function learningAvatarGenders()
    {
        return [
            'boy' => 'Мальчик',
            'girl' => 'Девочка',
        ];
    }

    public function learningAvatarGenderKey(): string
    {
        if (is_string($this->gender) && array_key_exists($this->gender, self::learningAvatarGenders())) {
            return $this->gender;
        }

        $config = is_array($this->learning_avatar_config) ? $this->learning_avatar_config : [];
        $configuredGender = $config['appearance']['gender'] ?? null;

        return is_string($configuredGender) && array_key_exists($configuredGender, self::learningAvatarGenders())
            ? $configuredGender
            : 'boy';
    }

    public function genderLabel(): string
    {
        return self::learningAvatarGenders()[$this->learningAvatarGenderKey()] ?? 'Мальчик';
    }

    public static function learningAvatarRankStages()
    {
        return [
            'novice' => [
                'name' => 'Домашний старт',
                'min_score' => 0,
            ],
            'skilled' => [
                'name' => 'Уверенный ученик',
                'min_score' => 700,
            ],
            'advanced' => [
                'name' => 'Университетская лаборатория',
                'min_score' => 3500,
            ],
            'expert' => [
                'name' => 'Инженерный офис',
                'min_score' => 7500,
            ],
            'master' => [
                'name' => 'Командный центр',
                'min_score' => 16500,
            ],
        ];
    }

    public static function learningAvatarSafeStages()
    {
        return [
            'safe_empty' => [
                'name' => 'Пустой сейф',
                'min_balance' => 0,
            ],
            'safe_few' => [
                'name' => 'Пара монет',
                'min_balance' => 1,
            ],
            'safe_stack' => [
                'name' => 'Стопка монет',
                'min_balance' => 21,
            ],
            'safe_rich' => [
                'name' => 'Полный сейф',
                'min_balance' => 76,
            ],
            'safe_treasure' => [
                'name' => 'Гора монет',
                'min_balance' => 201,
            ],
        ];
    }

    public static function learningAvatarWeatherLayerNames()
    {
        $seasons = [
            'season_spring' => 'Весна',
            'season_summer' => 'Лето',
            'season_autumn' => 'Осень',
            'season_winter' => 'Зима',
        ];
        $weatherStates = [
            'weather_clear' => 'Ясно',
            'weather_cloudy' => 'Облачно',
            'weather_rain' => 'Дождь',
            'weather_snow' => 'Снег',
            'weather_storm' => 'Гроза',
        ];
        $names = [
            'snow_moscow' => 'Снег в Москве',
        ];

        foreach ($seasons as $seasonKey => $seasonName) {
            $names[$seasonKey] = $seasonName;
            foreach ($weatherStates as $weatherKey => $weatherName) {
                $names[$seasonKey . '_' . $weatherKey] = $seasonName . ' · ' . $weatherName;
            }
        }

        foreach ($weatherStates as $weatherKey => $weatherName) {
            $names[$weatherKey] = $weatherName;
        }

        return $names;
    }

    public static function learningAvatarCoursePosterNames()
    {
        return [
            'default' => 'Базовый курс',
        ];
    }

    public static function learningAvatarManifest($manifestKey = 'room-system')
    {
        static $cache = [];

        $manifests = self::learningAvatarManifests();
        $manifestKey = array_key_exists($manifestKey, $manifests) ? $manifestKey : 'room-system';

        if (!array_key_exists($manifestKey, $cache)) {
            $manifestPath = public_path($manifests[$manifestKey]['path']);
            $manifest = [];

            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
            }

            $manifest['key'] = $manifestKey;
            $manifest['asset_base_path'] = dirname($manifests[$manifestKey]['path']);
            if ($manifestKey === 'room-system') {
                $manifest = self::normalizeRoomSystemManifest($manifest);
            }
            $cache[$manifestKey] = $manifest;
        }

        return $cache[$manifestKey];
    }

    private static function normalizeRoomSystemManifest($manifest)
    {
        $items = [];

        foreach (($manifest['items'] ?? []) as $item) {
            if (!is_array($item) || empty($item['key'])) {
                continue;
            }

            $items[$item['key']] = array_merge([
                'role' => 'desk_item',
                'fit' => 'contain',
            ], $item);
        }

        foreach (($manifest['pets'] ?? []) as $pet) {
            if (!is_array($pet)) {
                continue;
            }

            $itemKey = ($pet['key'] ?? null) === 'learning_bot' ? 'learning_bot_pet' : ($pet['key'] ?? null);
            if (!$itemKey) {
                continue;
            }

            $items[$itemKey] = array_merge([
                'key' => $itemKey,
                'role' => 'pet',
                'fit' => 'contain',
            ], $pet, [
                'key' => $itemKey,
            ]);
        }

        $manifest['system'] = 'room-system';
        $manifest['items'] = $items;
        $manifest['equipped'] = [
            'desk_center' => 'basic_laptop',
        ];
        return $manifest;
    }

    public static function defaultLearningAvatarConfig()
    {
        $manifest = self::learningAvatarManifest('room-system');

        return [
            'manifest' => 'room-system',
            'owned' => self::freeLearningAvatarItems('room-system'),
            'appearance' => [
                'gender' => 'boy',
            ],
            'equipped' => $manifest['equipped'] ?? [
                'desk_center' => 'basic_laptop',
            ],
        ];
    }

    public static function freeLearningAvatarItems($manifestKey = 'room-system')
    {
        $manifest = self::learningAvatarManifest($manifestKey);
        $catalog = self::learningAvatarItemCatalog();

        return collect(array_keys($manifest['items'] ?? []))
            ->filter(function ($itemKey) use ($manifest, $catalog) {
                return empty($manifest['items'][$itemKey]['unlock'])
                    && (int) ($catalog[$itemKey]['cost'] ?? 0) <= 0;
            })
            ->values()
            ->all();
    }

    public static function sanitizeLearningAvatarConfig($config)
    {
        $default = self::defaultLearningAvatarConfig();
        $config = is_array($config) ? $config : [];
        $manifestKey = $config['manifest'] ?? $default['manifest'];
        $manifest = self::learningAvatarManifest($manifestKey);
        $manifestKey = $manifest['key'] ?? 'room-system';
        $items = $manifest['items'] ?? [];
        $ownedInput = is_array($config['owned'] ?? null) ? $config['owned'] : [];
        $baselineOwned = self::freeLearningAvatarItems($manifestKey);
        $owned = collect(array_merge($baselineOwned, $ownedInput))
            ->filter(function ($itemKey) use ($items) {
                return is_string($itemKey) && isset($items[$itemKey]);
            })
            ->unique()
            ->values()
            ->all();
        $appearanceInput = is_array($config['appearance'] ?? null) ? $config['appearance'] : [];
        $gender = array_key_exists($appearanceInput['gender'] ?? null, self::learningAvatarGenders())
            ? $appearanceInput['gender']
            : 'boy';
        $grade = (int) ($appearanceInput['grade'] ?? 0);
        $appearance = [
            'gender' => $gender,
        ];
        if ($grade >= 5 && $grade <= 12) {
            $appearance['grade'] = $grade;
        }
        $equippedInput = is_array($config['equipped'] ?? null)
            ? $config['equipped']
            : ($manifest['equipped'] ?? []);
        $equipped = [];

        foreach ($equippedInput as $slotKey => $itemKey) {
            if (!$itemKey || !is_string($itemKey) || !isset($items[$itemKey])) {
                continue;
            }

            if (!in_array($itemKey, $owned, true)) {
                continue;
            }

            if (self::learningAvatarEquippedSlotForItem($items[$itemKey]) === $slotKey) {
                $equipped[$slotKey] = $itemKey;
            }
        }

        $petBonuses = is_array($config['pet_bonuses'] ?? null) ? $config['pet_bonuses'] : [];

        return [
            'manifest' => $manifestKey,
            'owned' => $owned,
            'appearance' => $appearance,
            'equipped' => $equipped,
            'pet_bonuses' => $petBonuses,
        ];
    }

    public function learningAvatarConfig()
    {
        $config = self::sanitizeLearningAvatarConfig($this->learning_avatar_config ?: self::defaultLearningAvatarConfig());
        $config['appearance']['gender'] = $this->learningAvatarGenderKey();
        $manifest = self::learningAvatarManifest($config['manifest']);
        $config['owned'] = collect(array_merge($config['owned'], $this->learningAvatarUnlockedItems($config['manifest'])))
            ->filter(function ($itemKey) use ($manifest) {
                return isset($manifest['items'][$itemKey]);
            })
            ->unique()
            ->values()
            ->all();

        $config['appearance']['gender'] = $this->learningAvatarGenderKey();

        return self::sanitizeLearningAvatarConfig($config);
    }

    public function learningAvatarOptionsBySlot()
    {
        $config = $this->learningAvatarConfig();
        $manifest = self::learningAvatarManifest($config['manifest']);
        $catalog = self::learningAvatarItemCatalog();
        $options = [];

        foreach (($manifest['items'] ?? []) as $itemKey => $item) {
            $slot = $item['slot'] ?? null;
            if (!$slot) {
                continue;
            }

            $optionKey = ($item['role'] ?? '') === 'fx' ? 'fx' : $slot;
            $option = array_merge([
                'name' => $itemKey,
                'description' => '',
                'icon' => 'fas fa-cube',
                'cost' => 0,
            ], $catalog[$itemKey] ?? []);
            $option['owned'] = in_array($itemKey, $config['owned'] ?? [], true);
            $option['unlock'] = $item['unlock'] ?? null;

            $options[$optionKey][$itemKey] = $option;
        }

        return $options;
    }

    public function learningAvatarPurchasePlan($manifestKey, $requestedEquipped, $requestedAppearance = null)
    {
        $currentConfig = $this->learningAvatarConfig();
        $manifestKey = $manifestKey ?: ($currentConfig['manifest'] ?? 'room-system');
        $manifest = self::learningAvatarManifest($manifestKey);
        $items = $manifest['items'] ?? [];
        $catalog = self::learningAvatarItemCatalog();
        $owned = $currentConfig['owned'] ?? self::freeLearningAvatarItems($manifest['key'] ?? 'room-system');
        $requestedEquipped = is_array($requestedEquipped) ? $requestedEquipped : [];
        $itemsToBuy = [];

        foreach ($requestedEquipped as $slotKey => $itemKey) {
            if (!$itemKey || !is_string($itemKey) || !isset($items[$itemKey]) || in_array($itemKey, $owned, true)) {
                continue;
            }

            if (self::learningAvatarEquippedSlotForItem($items[$itemKey]) !== $slotKey) {
                continue;
            }

            $itemCost = (int) ($catalog[$itemKey]['cost'] ?? 0);
            if ($itemCost <= 0) {
                if (!empty($items[$itemKey]['unlock'])) {
                    continue;
                }

                $owned[] = $itemKey;
                continue;
            }

            $itemsToBuy[$itemKey] = [
                'name' => $catalog[$itemKey]['name'] ?? $itemKey,
                'cost' => $itemCost,
            ];
        }

        return [
            'manifest' => $manifest,
            'manifestKey' => $manifest['key'] ?? $manifestKey,
            'owned' => array_values(array_unique($owned)),
            'requestedEquipped' => $requestedEquipped,
            'requestedAppearance' => is_array($requestedAppearance) ? $requestedAppearance : ($currentConfig['appearance'] ?? []),
            'itemsToBuy' => $itemsToBuy,
            'totalCost' => collect($itemsToBuy)->sum('cost'),
        ];
    }

    public function learningAvatarConfigFromPurchasePlan($plan)
    {
        $appearance = $plan['requestedAppearance'] ?? [];
        if (is_string($this->gender) && array_key_exists($this->gender, self::learningAvatarGenders())) {
            $appearance['gender'] = $this->gender;
        } elseif (!is_string($appearance['gender'] ?? null) || !array_key_exists($appearance['gender'], self::learningAvatarGenders())) {
            $appearance['gender'] = $this->learningAvatarGenderKey();
        }

        return self::sanitizeLearningAvatarConfig([
            'manifest' => $plan['manifestKey'] ?? 'room-system',
            'owned' => array_merge($plan['owned'] ?? [], array_keys($plan['itemsToBuy'] ?? [])),
            'appearance' => $appearance,
            'equipped' => $plan['requestedEquipped'] ?? [],
            'pet_bonuses' => $this->learningAvatarConfig()['pet_bonuses'] ?? [],
        ]);
    }

    public function learningAvatarConfigFromOwnedSelection($manifestKey, $requestedEquipped, $requestedAppearance = null)
    {
        $currentConfig = $this->learningAvatarConfig();
        $manifestKey = $manifestKey ?: ($currentConfig['manifest'] ?? 'room-system');
        $manifest = self::learningAvatarManifest($manifestKey);
        $items = $manifest['items'] ?? [];
        $owned = $currentConfig['owned'] ?? self::freeLearningAvatarItems($manifest['key'] ?? 'room-system');
        $equipped = $currentConfig['equipped'] ?? [];
        $requestedEquipped = is_array($requestedEquipped) ? $requestedEquipped : [];
        $appearance = array_merge($currentConfig['appearance'] ?? [], is_array($requestedAppearance) ? $requestedAppearance : []);

        foreach (['desk_center', 'pet_right'] as $slotKey) {
            if (!array_key_exists($slotKey, $requestedEquipped)) {
                continue;
            }

            $itemKey = $requestedEquipped[$slotKey];
            if (!$itemKey) {
                unset($equipped[$slotKey]);
                continue;
            }

            if (
                is_string($itemKey)
                && isset($items[$itemKey])
                && in_array($itemKey, $owned, true)
                && self::learningAvatarEquippedSlotForItem($items[$itemKey]) === $slotKey
            ) {
                $equipped[$slotKey] = $itemKey;
            }
        }

        return self::sanitizeLearningAvatarConfig([
            'manifest' => $manifest['key'] ?? $manifestKey,
            'owned' => $owned,
            'appearance' => $appearance,
            'equipped' => $equipped,
            'pet_bonuses' => $currentConfig['pet_bonuses'] ?? [],
        ]);
    }

    public function learningAvatarDigitalStoreItems()
    {
        $config = $this->learningAvatarConfig();
        $manifest = self::learningAvatarManifest($config['manifest'] ?? 'room-system');
        $catalog = self::learningAvatarItemCatalog();

        $assetBasePath = trim($manifest['asset_base_path'] ?? 'images/avatar-layers/room-system', '/');

        return collect($manifest['items'] ?? [])
            ->map(function ($item, $itemKey) use ($catalog, $config, $assetBasePath) {
                $catalogItem = $catalog[$itemKey] ?? null;
                if (!$catalogItem) {
                    return null;
                }

                $role = $item['role'] ?? null;
                $slot = self::learningAvatarEquippedSlotForItem($item);
                if (!in_array($slot, ['desk_center', 'pet_right'], true)) {
                    return null;
                }

                $abilities = $role === 'pet' ? (self::learningAvatarPetAbilities()[$itemKey] ?? []) : [];

                return [
                    'key' => $itemKey,
                    'type' => 'learning_avatar_item',
                    'name' => $catalogItem['name'] ?? $itemKey,
                    'description' => $catalogItem['description'] ?? '',
                    'icon' => $catalogItem['icon'] ?? 'fas fa-cube',
                    'cost' => (int) ($catalogItem['cost'] ?? 0),
                    'slot' => $slot,
                    'role' => $role,
                    'preview_src' => !empty($item['file']) ? $assetBasePath . '/' . ltrim($item['file'], '/') : null,
                    'owned' => in_array($itemKey, $config['owned'] ?? [], true),
                    'abilities' => $abilities,
                    'ability_descriptions' => collect($abilities)
                        ->map(fn ($ability) => self::learningAvatarPetAbilityDescriptions()[$ability] ?? $ability)
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->sortBy(function ($item) {
                return ($item['slot'] === 'pet_right' ? '1' : '0') . str_pad((string) $item['cost'], 5, '0', STR_PAD_LEFT) . $item['name'];
            })
            ->values();
    }

    public function profileDigitalStoreItems()
    {
        $items = collect([
            [
                'key' => 'custom_title',
                'type' => 'custom_title',
                'name' => 'Кастомное звание',
                'description' => 'Короткая подпись рядом с именем в профиле, рейтингах и лентах. Активна ' . $this->customTitleDurationDays() . ' дней.',
                'icon' => 'fas fa-certificate',
                'cost' => $this->customTitleCost(),
                'role' => 'profile_cosmetic',
                'slot' => 'profile',
                'owned' => false,
                'active' => $this->hasActiveCustomTitle(),
                'active_until' => $this->custom_title_expires_at,
                'ability_descriptions' => [],
            ],
        ]);

        foreach (self::avatarFrames() as $frameKey => $frame) {
            $items->push([
                'key' => 'avatar_frame_' . $frameKey,
                'type' => 'avatar_frame',
                'frame_key' => $frameKey,
                'name' => 'Рамка: ' . ($frame['name'] ?? $frameKey),
                'description' => ($frame['description'] ?? '') . ' Активна ' . ($frame['days'] ?? 30) . ' дней.',
                'icon' => $frame['icon'] ?? 'fas fa-user-circle',
                'cost' => (int) ($frame['cost'] ?? 0),
                'role' => 'profile_cosmetic',
                'slot' => 'profile',
                'owned' => false,
                'active' => $this->activeAvatarFrame() === $frameKey,
                'active_until' => $this->activeAvatarFrame() === $frameKey ? $this->avatar_frame_expires_at : null,
                'ability_descriptions' => [],
            ]);
        }

        $items->push([
            'key' => 'avatar_frame_custom',
            'type' => 'custom_avatar_frame',
            'frame_key' => 'custom',
            'name' => 'Своя рамка',
            'description' => 'Кастомная рамка с выбранными цветами, формой, свечением и движением. Статичная стоит ' . $this->customAvatarFrameCost(false) . ' GC, живая - ' . $this->customAvatarFrameCost(true) . ' GC. Активна ' . $this->customAvatarFrameDurationDays() . ' дней.',
            'icon' => 'fas fa-palette',
            'cost' => $this->customAvatarFrameCost(false),
            'static_cost' => $this->customAvatarFrameCost(false),
            'animated_cost' => $this->customAvatarFrameCost(true),
            'role' => 'profile_cosmetic',
            'slot' => 'profile',
            'owned' => false,
            'active' => $this->activeAvatarFrame() === 'custom',
            'active_until' => $this->activeAvatarFrame() === 'custom' ? $this->avatar_frame_expires_at : null,
            'ability_descriptions' => [],
            'config' => self::sanitizeCustomAvatarFrameConfig($this->avatar_frame_config ?: self::customAvatarFrameDefaults()),
        ]);

        return $items;
    }

    public function digitalStoreItems()
    {
        return $this->profileDigitalStoreItems()
            ->merge($this->learningAvatarDigitalStoreItems())
            ->values();
    }

    public function learningAvatarOwnsItem($itemKey): bool
    {
        return in_array($itemKey, $this->learningAvatarConfig()['owned'] ?? [], true);
    }

    public function learningAvatarBuyItem($itemKey): array
    {
        $config = $this->learningAvatarConfig();
        $manifest = self::learningAvatarManifest($config['manifest'] ?? 'room-system');
        $items = $manifest['items'] ?? [];
        $catalog = self::learningAvatarItemCatalog();

        if (!is_string($itemKey) || !isset($items[$itemKey], $catalog[$itemKey])) {
            throw new \InvalidArgumentException('digital_item_not_found');
        }

        if (in_array($itemKey, $config['owned'] ?? [], true)) {
            throw new \RuntimeException('digital_item_already_owned');
        }

        $cost = (int) ($catalog[$itemKey]['cost'] ?? 0);
        if ($cost <= 0 || !empty($items[$itemKey]['unlock'])) {
            throw new \RuntimeException('digital_item_not_buyable');
        }

        $config['owned'][] = $itemKey;
        $this->learning_avatar_config = self::sanitizeLearningAvatarConfig($config);
        $this->save();

        return [
            'key' => $itemKey,
            'name' => $catalog[$itemKey]['name'] ?? $itemKey,
            'cost' => $cost,
        ];
    }

    public function activeLearningAvatarPetKey(): ?string
    {
        $config = $this->learningAvatarConfig();
        $petKey = $config['equipped']['pet_right'] ?? null;

        return is_string($petKey) && in_array($petKey, $config['owned'] ?? [], true) && isset(self::learningAvatarPetAbilities()[$petKey])
            ? $petKey
            : null;
    }

    public function activeLearningAvatarPetName(): ?string
    {
        $petKey = $this->activeLearningAvatarPetKey();
        if (!$petKey) {
            return null;
        }

        return self::learningAvatarItemCatalog()[$petKey]['name'] ?? $petKey;
    }

    public function hasActiveLearningAvatarPetAbility($ability): bool
    {
        $petKey = $this->activeLearningAvatarPetKey();

        return $petKey && in_array($ability, self::learningAvatarPetAbilities()[$petKey] ?? [], true);
    }

    public function learningAvatarPetBonuses(): array
    {
        $config = $this->learningAvatarConfig();

        return is_array($config['pet_bonuses'] ?? null) ? $config['pet_bonuses'] : [];
    }

    public function updateLearningAvatarPetBonuses(array $bonuses): void
    {
        $config = $this->learningAvatarConfig();
        $config['pet_bonuses'] = $bonuses;
        $this->learning_avatar_config = self::sanitizeLearningAvatarConfig($config);
        $this->save();
    }

    public function freeXpBoostersCount(): int
    {
        return max(0, (int) ($this->learningAvatarPetBonuses()['free_xp_boosters'] ?? 0));
    }

    public function grantFreeXpBooster($count = 1): void
    {
        $bonuses = $this->learningAvatarPetBonuses();
        $bonuses['free_xp_boosters'] = max(0, (int) ($bonuses['free_xp_boosters'] ?? 0)) + max(1, (int) $count);
        $this->updateLearningAvatarPetBonuses($bonuses);
    }

    public function consumeFreeXpBooster(): bool
    {
        $bonuses = $this->learningAvatarPetBonuses();
        $available = max(0, (int) ($bonuses['free_xp_boosters'] ?? 0));
        if ($available <= 0) {
            return false;
        }

        $bonuses['free_xp_boosters'] = $available - 1;
        $this->updateLearningAvatarPetBonuses($bonuses);

        return true;
    }

    public function canUseFreeXpBooster(): bool
    {
        return $this->freeXpBoostersCount() > 0;
    }

    public function canUseDailyAutoXpBoosterToday(): bool
    {
        if (!$this->hasActiveLearningAvatarPetAbility('daily_auto_xp_booster')) {
            return false;
        }

        $bonuses = $this->learningAvatarPetBonuses();
        $lastUsedAt = (string) ($bonuses['auto_xp_booster_used_on'] ?? '');

        return $lastUsedAt !== Carbon::now()->toDateString();
    }

    public function consumeDailyAutoXpBoosterToday(): bool
    {
        if (!$this->canUseDailyAutoXpBoosterToday()) {
            return false;
        }

        $bonuses = $this->learningAvatarPetBonuses();
        $bonuses['auto_xp_booster_used_on'] = Carbon::now()->toDateString();
        $this->updateLearningAvatarPetBonuses($bonuses);

        return true;
    }

    public function geekPasteExtraAttemptCost(): int
    {
        return $this->canUseFreeGeekPasteResetToday() ? 0 : \App\Services\GeekPasteClient::EXTRA_ATTEMPT_COST;
    }

    public function canUseFreeGeekPasteResetToday(): bool
    {
        if (!$this->hasActiveLearningAvatarPetAbility('daily_free_geekpaste_reset')) {
            return false;
        }

        $bonuses = $this->learningAvatarPetBonuses();
        $lastUsedAt = (string) ($bonuses['free_geekpaste_reset_used_on'] ?? '');

        return $lastUsedAt !== Carbon::now()->toDateString();
    }

    public function consumeFreeGeekPasteResetToday(): bool
    {
        if (!$this->canUseFreeGeekPasteResetToday()) {
            return false;
        }

        $bonuses = $this->learningAvatarPetBonuses();
        $bonuses['free_geekpaste_reset_used_on'] = Carbon::now()->toDateString();
        $this->updateLearningAvatarPetBonuses($bonuses);

        return true;
    }

    public function canUseFreeEarlyLessonAccess(): bool
    {
        return $this->hasActiveLearningAvatarPetAbility('early_lesson_unlock');
    }

    public function earlyLessonAccessCost(?Lesson $lesson = null): int
    {
        if ($this->canUseFreeEarlyLessonAccess()) {
            return 0;
        }

        return $lesson ? (int) $lesson->earlyAccessCost() : 10;
    }

    public function taskCoinReward($baseReward): int
    {
        $baseReward = (int) $baseReward;
        if ($baseReward <= 0) {
            return 0;
        }

        if ($this->hasActiveLearningAvatarPetAbility('task_coin_bonus_10')) {
            return max($baseReward + 1, (int) ceil($baseReward * 1.10));
        }

        return $baseReward;
    }

    public function hasLearningAvatarPetDailyRoll($date, $petKey): bool
    {
        $bonuses = $this->learningAvatarPetBonuses();

        return !empty($bonuses['daily_rolls'][$date][$petKey]);
    }

    public function markLearningAvatarPetDailyRoll($date, $petKey): void
    {
        $bonuses = $this->learningAvatarPetBonuses();
        $bonuses['daily_rolls'][$date][$petKey] = true;
        $this->updateLearningAvatarPetBonuses($bonuses);
    }

    private static function learningAvatarEquippedSlotForItem($item)
    {
        return ($item['role'] ?? '') === 'fx' ? 'fx' : ($item['slot'] ?? null);
    }

    public function learningAvatarRenderData()
    {
        $config = $this->learningAvatarConfig();
        $manifest = self::learningAvatarManifest($config['manifest']);

        return $this->learningAvatarRoomSystemRenderData($config, $manifest);
    }

    private function learningAvatarRoomSystemRenderData($config, $manifest)
    {
        $assetBasePath = $manifest['asset_base_path'] ?? 'images/avatar-layers/room-system';
        $canvas = $manifest['canvas'] ?? ['width' => 1024, 'height' => 1024];
        $rankStage = $this->learningAvatarRankStageKey();
        $room = $this->learningAvatarRoomSystemRoom($manifest);
        $roomKey = $room['key'] ?? 'room_01_home_start';
        $slots = self::learningAvatarRoomSystemLayouts()[$roomKey] ?? [];
        $slotManifest = array_merge($manifest, ['slots' => $slots]);
        $coursePosterLayer = $this->learningAvatarRoomSystemCoursePosterLayer($manifest);
        $coursePoster = $coursePosterLayer['key'] ?? 'default';
        $weatherLayer = app(\App\Services\MoscowWeatherService::class)->avatarLayerKey();
        $safeStage = $this->learningAvatarSafeStageKey();
        $grade = $this->learningAvatarRoomSystemGrade($config);
        $gender = $config['appearance']['gender'] ?? 'boy';
        $layers = [];
        $renderOrder = [
            'window_weather' => 0,
            'course_poster' => 1,
            'room' => 2,
            'safe' => 3,
            'desk_center' => 4,
            'trophies' => 5,
            'character' => 6,
            'pet_right' => 7,
        ];

        $weather = $this->learningAvatarRoomSystemEntry($manifest['weather'] ?? [], $weatherLayer)
            ?: $this->learningAvatarRoomSystemEntry($manifest['weather'] ?? [], 'season_winter_weather_clear')
            ?: (($manifest['weather'] ?? [])[0] ?? null);
        if ($weather) {
            $weather['slot'] = 'window_weather';
            $weather['fit'] = 'fill';
            $weather['_style'] = $this->learningAvatarRoomSystemWeatherStyle($weather, $slots['_weather'] ?? [], $slots['window_weather'] ?? [], $canvas);
            $layers[] = $this->learningAvatarRoomSystemLayer($weather, 'window_weather', 'window_weather', $slotManifest, $assetBasePath, $renderOrder['window_weather']);
        }

        $poster = $coursePosterLayer
            ?: $this->learningAvatarRoomSystemEntry($manifest['posters'] ?? [], 'default');
        if ($poster) {
            $poster['slot'] = 'poster_wall';
            $poster['fit'] = 'cover';
            $layers[] = $this->learningAvatarRoomSystemLayer($poster, 'course_poster', 'course_poster', $slotManifest, $assetBasePath, $renderOrder['course_poster']);
        }

        if ($room) {
            $room['_style'] = 'inset: 0; width: 100%; height: 100%; object-fit: cover;';
            $layers[] = $this->learningAvatarRoomSystemLayer($room, 'room', 'room', $slotManifest, $assetBasePath, $renderOrder['room'], true);
        }

        $safe = $this->learningAvatarRoomSystemEntry($manifest['safes'] ?? [], $safeStage);
        if ($safe) {
            $safe['slot'] = 'safe_under_desk';
            $safe['fit'] = 'contain';
            $layers[] = $this->learningAvatarRoomSystemLayer($safe, 'safe', 'safe', $slotManifest, $assetBasePath, $renderOrder['safe']);
        }

        $equipped = $config['equipped'] ?? [];
        $items = $manifest['items'] ?? [];
        foreach (['desk_center', 'pet_right'] as $equippedSlot) {
            $itemKey = $equipped[$equippedSlot] ?? null;
            if (!$itemKey || !isset($items[$itemKey])) {
                continue;
            }

            $layers[] = $this->learningAvatarRoomSystemLayer($items[$itemKey], $itemKey, $equippedSlot, $slotManifest, $assetBasePath, $renderOrder[$equippedSlot] ?? 99);
        }

        $characterKey = $this->learningAvatarRoomSystemCharacterKey($grade);
        $characterFile = $manifest['characters'][$gender][$characterKey]
            ?? $manifest['characters']['boy'][$characterKey]
            ?? $manifest['characters']['boy']['class_07']
            ?? null;
        if ($characterFile) {
            $character = [
                'file' => $characterFile,
                'slot' => 'character',
                'fit' => 'cover',
                'gravity' => 'south',
                'role' => 'character',
                '_inner_style' => self::learningAvatarRoomSystemCharacterScaleStyle($grade),
            ];
            $layers[] = $this->learningAvatarRoomSystemLayer($character, 'character', 'character', $slotManifest, $assetBasePath, $renderOrder['character']);
        }

        foreach ($this->learningAvatarRoomSystemTrophyLayers($manifest, $slotManifest, $assetBasePath, $renderOrder['trophies']) as $layer) {
            $layers[] = $layer;
        }

        usort($layers, function ($left, $right) {
            return ($left['order'] ?? 99) <=> ($right['order'] ?? 99);
        });

        return [
            'manifest' => $manifest,
            'config' => $config,
            'layers' => $layers,
            'renderOrder' => $renderOrder,
            'appearance' => [
                'gender' => $gender,
                'genderOptions' => self::learningAvatarGenders(),
                'grade' => $grade,
                'rankStage' => $roomKey,
                'rankStageName' => $room['name'] ?? (self::learningAvatarRankStages()[$rankStage]['name'] ?? $rankStage),
                'legacyRankStage' => $rankStage,
                'roomKey' => $roomKey,
                'coursePoster' => $coursePoster,
                'coursePosterName' => $poster['name'] ?? $this->learningAvatarDynamicLayerName($manifest['posters'] ?? [], $coursePoster, self::learningAvatarCoursePosterNames()),
                'weatherLayer' => $weatherLayer,
                'weatherLayerName' => $this->learningAvatarDynamicLayerName($manifest['weather'] ?? [], $weatherLayer, self::learningAvatarWeatherLayerNames()),
                'safeStage' => $safeStage,
                'safeStageName' => self::learningAvatarSafeStages()[$safeStage]['name'] ?? $safeStage,
            ],
            'characterPreviewByGender' => $this->learningAvatarRoomSystemCharacterPreviewByGender($manifest, $slotManifest, $assetBasePath, $grade),
            'characterPreviewByGradeAndGender' => $this->learningAvatarRoomSystemCharacterPreviewByGradeAndGender($manifest, $slotManifest, $assetBasePath),
            'optionsBySlot' => $this->learningAvatarOptionsBySlot(),
            'previewItemsBySlot' => $this->learningAvatarRoomSystemPreviewItemsBySlot($manifest, $slotManifest, $assetBasePath),
        ];
    }

    private function learningAvatarRoomSystemRoom($manifest)
    {
        $score = $this->score !== null ? (int) $this->score : ($this->exists ? (int) $this->score() : 0);
        $selected = ($manifest['rooms'] ?? [])[0] ?? null;

        foreach (($manifest['rooms'] ?? []) as $room) {
            if ($score >= (int) ($room['xpFrom'] ?? 0)) {
                $selected = $room;
            }
        }

        return $selected ?: [];
    }

    private function learningAvatarRoomSystemGrade($config)
    {
        $configuredGrade = (int) ($config['appearance']['grade'] ?? 0);
        if ($configuredGrade >= 5 && $configuredGrade <= 12) {
            return $configuredGrade;
        }

        $grade = (int) $this->grade();

        if ($grade > 11) {
            return 12;
        }

        return min(11, max(5, $grade ?: 7));
    }

    private function learningAvatarRoomSystemCharacterKey($grade): string
    {
        $grade = (int) $grade;

        if ($grade > 11) {
            return in_array($this->role, ['teacher', 'admin'], true) ? 'teacher' : 'student';
        }

        return 'class_' . str_pad((string) max(5, min(11, $grade)), 2, '0', STR_PAD_LEFT);
    }

    public static function learningAvatarRoomSystemCharacterScale($grade)
    {
        $grade = max(5, min(12, (int) $grade));
        $scales = [
            5 => 0.82,
            6 => 0.88,
            7 => 0.94,
            8 => 1.00,
            9 => 1.06,
            10 => 1.12,
            11 => 1.18,
            12 => 1.22,
        ];

        return $scales[$grade] ?? 1.00;
    }

    private static function learningAvatarRoomSystemCharacterScaleStyle($grade)
    {
        $scale = number_format(self::learningAvatarRoomSystemCharacterScale($grade), 2, '.', '');

        return 'transform: scale(' . $scale . '); transform-origin: center bottom;';
    }

    private static function learningAvatarRoomSystemLayouts()
    {
        static $layouts = null;
        static $cachedPath = null;

        $path = config('learning_avatar.room_system.layouts_path')
            ?: public_path('images/avatar-layers/room-system/layouts.json');
        if ($layouts !== null && $cachedPath === $path) {
            return $layouts;
        }

        $cachedPath = $path;
        $layouts = file_exists($path)
            ? (json_decode(file_get_contents($path), true) ?: [])
            : [];

        return $layouts;
    }

    private function learningAvatarRoomSystemEntry($entries, $key)
    {
        foreach ($entries as $entry) {
            if (($entry['key'] ?? null) === $key) {
                return $entry;
            }
        }

        return null;
    }

    private function learningAvatarRoomSystemWeatherStyle($weather, $placement, $slot, $canvas)
    {
        $canvasWidth = max(1, (int) ($canvas['width'] ?? 1024));
        $canvasHeight = max(1, (int) ($canvas['height'] ?? 1024));
        $scale = max(50, min(3072, (int) ($placement['scale'] ?? $weather['scale'] ?? 1024)));
        $legacyCropX = max(-2048, min(2048, (int) ($placement['cropX'] ?? $weather['cropX'] ?? 0)));
        $legacyCropY = max(-2048, min(2048, (int) ($placement['cropY'] ?? $weather['cropY'] ?? 0)));
        $x = array_key_exists('x', $placement)
            ? max(-2048, min(2048, (int) $placement['x']))
            : (int) ($slot['x'] ?? 0) - $legacyCropX;
        $y = array_key_exists('y', $placement)
            ? max(-2048, min(2048, (int) $placement['y']))
            : (int) ($slot['y'] ?? 0) - $legacyCropY;
        $width = max(50, min(3072, (int) ($placement['width'] ?? $scale)));
        $height = max(50, min(3072, (int) ($placement['height'] ?? $scale)));

        return 'left: ' . round(100 * $x / $canvasWidth, 4) . '%;'
            . 'top: ' . round(100 * $y / $canvasHeight, 4) . '%;'
            . 'width: ' . round(100 * $width / $canvasWidth, 4) . '%;'
            . 'height: ' . round(100 * $height / $canvasHeight, 4) . '%;';
    }

    private function learningAvatarRoomSystemLayer($layer, $key, $equippedSlot, $manifest, $assetBasePath, $order, $fullCanvas = false)
    {
        $payload = $this->learningAvatarLayerPayload($layer, $key, $manifest, $assetBasePath, $equippedSlot);
        $payload['order'] = $order;

        if ($fullCanvas) {
            $payload['fullCanvas'] = true;
            $payload['slot'] = $layer['slot'] ?? $equippedSlot;
            $payload['style'] = $layer['_style'] ?? $payload['style'];
        }

        return $payload;
    }

    private function learningAvatarRoomSystemCharacterPreviewByGender($manifest, $slotManifest, $assetBasePath, $grade)
    {
        $previews = [];

        foreach (array_keys(self::learningAvatarGenders()) as $gender) {
            $classKey = $this->learningAvatarRoomSystemCharacterKey($grade);
            $file = $manifest['characters'][$gender][$classKey] ?? null;
            if (!$file) {
                continue;
            }

            $previews[$gender] = $this->learningAvatarRoomSystemLayer([
                'file' => $file,
                'slot' => 'character',
                'fit' => 'cover',
                'gravity' => 'south',
                '_inner_style' => self::learningAvatarRoomSystemCharacterScaleStyle($grade),
            ], 'character', 'character', $slotManifest, $assetBasePath, 5);
        }

        return $previews;
    }

    private function learningAvatarRoomSystemCharacterPreviewByGradeAndGender($manifest, $slotManifest, $assetBasePath)
    {
        $previews = [];

        for ($grade = 5; $grade <= 12; $grade++) {
            $classKey = $this->learningAvatarRoomSystemCharacterKey($grade);

            foreach (array_keys(self::learningAvatarGenders()) as $gender) {
                $file = $manifest['characters'][$gender][$classKey] ?? null;
                if (!$file) {
                    continue;
                }

                $previews[$grade][$gender] = $this->learningAvatarRoomSystemLayer([
                    'file' => $file,
                    'slot' => 'character',
                    'fit' => 'cover',
                    'gravity' => 'south',
                    '_inner_style' => self::learningAvatarRoomSystemCharacterScaleStyle($grade),
                ], 'character', 'character', $slotManifest, $assetBasePath, 5);
            }
        }

        return $previews;
    }

    private function learningAvatarRoomSystemPreviewItemsBySlot($manifest, $slotManifest, $assetBasePath)
    {
        $itemsBySlot = [];

        foreach (($manifest['items'] ?? []) as $itemKey => $item) {
            $slot = self::learningAvatarEquippedSlotForItem($item);
            if (!$slot) {
                continue;
            }

            $itemsBySlot[$slot][$itemKey] = $this->learningAvatarRoomSystemLayer($item, $itemKey, $slot, $slotManifest, $assetBasePath, 99);
        }

        return $itemsBySlot;
    }

    private function learningAvatarRoomSystemTrophyLayers($manifest, $slotManifest, $assetBasePath, $baseOrder)
    {
        return $this->learningAvatarPublishedAchievements()
            ->filter(function ($achievement) {
                return (bool) $achievement->trophyImageUrl();
            })
            ->values()
            ->take(3)
            ->map(function ($achievement, $index) use ($slotManifest, $assetBasePath, $baseOrder) {
                $slot = 'shelf_trophy_' . ($index + 1);
                $layerConfig = [
                    'key' => 'achievement_custom_trophy',
                    'name' => $achievement->title,
                    'url' => $achievement->trophyImageUrl(),
                    'slot' => $slot,
                    'fit' => 'contain',
                    'role' => 'achievement_trophy',
                ];
                $layer = $this->learningAvatarRoomSystemLayer($layerConfig, 'achievement_trophy_' . ($achievement->id ?: $index + 1), $slot, $slotManifest, $assetBasePath, $baseOrder + (($index + 1) / 10));
                $layer['achievementTitle'] = $achievement->title;
                $layer['trophyTemplate'] = 'custom';

                return $layer;
            })
            ->values()
            ->all();
    }

    public function learningAvatarRankStageKey()
    {
        $score = $this->score !== null ? (int) $this->score : ($this->exists ? (int) $this->score() : 0);
        $stageKey = 'novice';

        foreach (self::learningAvatarRankStages() as $key => $stage) {
            if ($score >= (int) ($stage['min_score'] ?? 0)) {
                $stageKey = $key;
            }
        }

        return $stageKey;
    }

    public function learningAvatarSafeStageKey()
    {
        $balance = $this->exists ? (int) $this->balance() : 0;
        $stageKey = 'safe_empty';

        foreach (self::learningAvatarSafeStages() as $key => $stage) {
            if ($balance >= (int) ($stage['min_balance'] ?? 0)) {
                $stageKey = $key;
            }
        }

        return $stageKey;
    }

    public function learningAvatarUnlockedItems($manifestKey = 'room-system')
    {
        $manifest = self::learningAvatarManifest($manifestKey);
        $achievementCount = $this->learningAvatarPublishedAchievements()->count();

        return collect($manifest['items'] ?? [])
            ->filter(function ($item) use ($achievementCount) {
                $unlock = $item['unlock'] ?? null;
                if (!is_array($unlock)) {
                    return false;
                }

                return ($unlock['type'] ?? null) === 'achievement_count'
                    && $achievementCount >= (int) ($unlock['min'] ?? 1);
            })
            ->keys()
            ->values()
            ->all();
    }

    private function learningAvatarPublishedAchievements()
    {
        if ($this->relationLoaded('achievements')) {
            return $this->achievements
                ->where('status', Achievement::STATUS_PUBLISHED)
                ->values();
        }

        if (!$this->exists) {
            return collect();
        }

        return $this->achievements()
            ->where('status', Achievement::STATUS_PUBLISHED)
            ->get();
    }

    private function learningAvatarRoomSystemCoursePosterLayer($manifest)
    {
        $course = $this->learningAvatarLatestActiveCourseWithPoster();
        $posterUrl = $course ? $course->learningAvatarPosterUrl() : null;
        $program = $course
            ? ($course->relationLoaded('program') ? $course->program : ($course->exists && $course->program_id ? $course->program()->first() : null))
            : null;

        if ($course && $posterUrl) {
            return [
                'key' => $program ? 'program_' . $program->id : 'course_' . $course->id,
                'name' => $program ? $program->name : $course->name,
                'url' => $posterUrl,
                'role' => 'course_poster',
            ];
        }

        return $this->learningAvatarRoomSystemEntry($manifest['posters'] ?? [], 'default');
    }

    private function learningAvatarLatestActiveCourseWithPoster()
    {
        if ($this->relationLoaded('courses')) {
            return $this->courses
                ->filter(function ($course) {
                    return ($course->state ?? null) === 'started';
                })
                ->sortByDesc(function ($course) {
                    return (int) ($course->id ?? 0);
                })
                ->first(function ($course) {
                    return (bool) $course->learningAvatarPosterUrl();
                });
        }

        if (!$this->exists) {
            return null;
        }

        return $this->courses()
            ->with('program')
            ->where('state', 'started')
            ->orderByDesc('courses.id')
            ->get()
            ->first(function ($course) {
                return (bool) $course->learningAvatarPosterUrl();
            });
    }

    private function learningAvatarDynamicLayerName($entries, $key, $fallbackNames)
    {
        if (is_array($entries[$key] ?? null) && !empty($entries[$key]['name'])) {
            return $entries[$key]['name'];
        }

        foreach ($entries as $entry) {
            if (is_array($entry) && ($entry['key'] ?? null) === $key && !empty($entry['name'])) {
                return $entry['name'];
            }
        }

        return $fallbackNames[$key] ?? $key;
    }

    private function learningAvatarCourseNames()
    {
        if ($this->relationLoaded('courses')) {
            return $this->courses
                ->pluck('name')
                ->map(function ($name) {
                    return mb_strtolower((string) $name);
                })
                ->values()
                ->all();
        }

        if (!$this->exists) {
            return [];
        }

        return $this->courses()
            ->pluck('name')
            ->map(function ($name) {
                return mb_strtolower((string) $name);
            })
            ->values()
            ->all();
    }

    public function learningAvatarPreviewItemsBySlot($manifest = null, $assetBasePath = null)
    {
        $config = $this->learningAvatarConfig();
        $manifest = $manifest ?: self::learningAvatarManifest($config['manifest']);
        $assetBasePath = $assetBasePath ?: ($manifest['asset_base_path'] ?? 'images/avatar-layers/room-system');
        $itemsBySlot = [];

        foreach (($manifest['items'] ?? []) as $itemKey => $item) {
            $slot = $item['slot'] ?? null;
            if (!$slot) {
                continue;
            }

            $equippedSlot = ($item['role'] ?? '') === 'fx' ? 'fx' : $slot;
            $itemsBySlot[$equippedSlot][$itemKey] = $this->learningAvatarLayerPayload($item, $itemKey, $manifest, $assetBasePath, $equippedSlot);
        }

        return $itemsBySlot;
    }

    private function learningAvatarLayerPayload($layer, $key, $manifest, $assetBasePath, $equippedSlot = null)
    {
        $canvas = $manifest['canvas'] ?? ['width' => 1024, 'height' => 1024];
        $slotName = $layer['slot'] ?? null;
        $slot = $slotName && isset($manifest['slots'][$slotName]) ? $manifest['slots'][$slotName] : null;
        $isFullCanvas = !$slot || !empty($layer['fullCanvas']);
        $gravity = $layer['gravity'] ?? ($slot['gravity'] ?? 'center');

        return [
            'key' => $key,
            'role' => $layer['role'] ?? null,
            'src' => $layer['url'] ?? $this->learningAvatarAssetUrl($assetBasePath, $layer['file'] ?? ''),
            'slot' => $slotName,
            'equippedSlot' => $equippedSlot ?: $slotName,
            'slotGeometry' => $slot,
            'fullCanvas' => $isFullCanvas,
            'fit' => $layer['fit'] ?? 'contain',
            'gravity' => $gravity,
            'style' => $layer['_style'] ?? ($slot && !$isFullCanvas
                ? $this->learningAvatarSlotStyle($slot, $canvas)
                : null),
            'innerStyle' => $layer['_inner_style'] ?? null,
            'objectPosition' => $this->learningAvatarObjectPosition($gravity),
        ];
    }

    private function learningAvatarAssetUrl($assetBasePath, $file)
    {
        if (Str::startsWith($file, ['http://', 'https://', '/'])) {
            return $file;
        }

        $relativePath = trim($assetBasePath . '/' . $file, '/');
        $absolutePath = public_path($relativePath);
        $version = file_exists($absolutePath) ? filemtime($absolutePath) : null;

        return url($relativePath) . ($version ? '?v=' . $version : '');
    }

    private function learningAvatarSlotStyle($slot, $canvas)
    {
        $canvasWidth = max(1, (int) ($canvas['width'] ?? 1024));
        $canvasHeight = max(1, (int) ($canvas['height'] ?? 1024));

        return 'left: ' . round(100 * (int) ($slot['x'] ?? 0) / $canvasWidth, 4) . '%;'
            . 'top: ' . round(100 * (int) ($slot['y'] ?? 0) / $canvasHeight, 4) . '%;'
            . 'width: ' . round(100 * (int) ($slot['width'] ?? 0) / $canvasWidth, 4) . '%;'
            . 'height: ' . round(100 * (int) ($slot['height'] ?? 0) / $canvasHeight, 4) . '%;';
    }

    private function learningAvatarObjectPosition($gravity)
    {
        $positions = [
            'northwest' => 'left top',
            'north' => 'center top',
            'northeast' => 'right top',
            'west' => 'left center',
            'center' => 'center center',
            'east' => 'right center',
            'southwest' => 'left bottom',
            'south' => 'center bottom',
            'southeast' => 'right bottom',
        ];

        return $positions[$gravity] ?? $positions['center'];
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

    public function achievements()
    {
        return $this->hasMany('App\Achievement', 'user_id', 'id');
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
