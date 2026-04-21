<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
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
        'hobbies', 'interests', 'git', 'telegram', 'facebook', 'comments', 'letter', 'email_verified_at', 'last_login_at',
        'last_login_ip'
    ];
    protected $casts = [
        'birthday' => 'datetime'
    ];

    protected $prerequisite_cache = [];
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

    public function checkPrerequisite(CoreNode $prerequisite)
    {
        if ($prerequisite->version == 1) {
            foreach ($this->solutions as $solution) {
                foreach ($solution->task->consequences as $consequence) {
                    if ($prerequisite->id == $consequence->id and $solution->mark != null and $solution->mark >= $consequence->pivot->cutscore) {
                        return true;
                    }
                }
            }
        }
        return false;
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

        $this->score = 0;
        
        // Get all solutions grouped by task
        $group = Solution::where('user_id', $this->id)->get()->groupBy('task_id');
        foreach ($group as $task) {
            $this->score += $task->sortByDesc('mark')->first()->mark;
        }

        $completedCourses = $this->completedCourses()->get();

        // Calculate scores from completed courses
        $markScores = [
            'S' => 2000, 'A+' => 1500, 'A' => 1200, 'A-' => 1000,
            'B+' => 800, 'B' => 600, 'B-' => 400,
            'C+' => 300, 'C' => 200, 'C-' => 100,
            'D+' => 50, 'D' => 50, 'D-' => 50
        ];

        foreach ($completedCourses as $course) {
            $this->score += $markScores[$course->mark] ?? 600;
        }

        Cache::put($cacheKey, $this->score, 3600);
        return $this->score;
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

    public function transactions()
    {
        return $this->hasMany('App\CoinTransaction', 'user_id', 'id');
    }

    public function balance()
    {
        return $this->transactions()->sum('price');
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
            $stickers = collect([]);
            foreach ($this->courses as $course) {
                foreach ($course->program->lessons as $lesson) {
                    if ($lesson->percent($this, $course) > 90) {
                        $stickers->push($lesson->sticker);
                    }
                }
            }
            return $stickers->unique();
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
            $descriptions = [];
            foreach ($this->courses as $course) {
                foreach ($course->program->lessons as $lesson) {
                    if ($lesson->percent($this, $course) > 90) {
                        $descriptions[$lesson->sticker] = $lesson->name;
                    }
                }
            }
            return $descriptions;
        });
    }

}
