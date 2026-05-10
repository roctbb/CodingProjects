<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    const SOURCE_AI_TASK_SOLUTION = 'ai_task_solution';
    const STATUS_PUBLISHED = 'published';
    const STATUS_HIDDEN = 'hidden';

    protected $table = 'achievements';

    protected $fillable = [
        'user_id',
        'course_id',
        'task_id',
        'solution_id',
        'source',
        'status',
        'title',
        'description',
        'icon_key',
        'payload',
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'published_at' => 'datetime',
    ];

    public static function iconOptions()
    {
        return [
            'sparkles' => 'fas fa-magic',
            'rocket' => 'fas fa-rocket',
            'bug' => 'fas fa-bug',
            'shield' => 'fas fa-shield-alt',
            'brain' => 'fas fa-brain',
            'flame' => 'fas fa-fire',
            'wand' => 'fas fa-wand-magic-sparkles',
            'code' => 'fas fa-code',
            'compass' => 'fas fa-compass',
            'trophy' => 'fas fa-trophy',
            'seedling' => 'fas fa-seedling',
            'gem' => 'fas fa-gem',
            'lightbulb' => 'fas fa-lightbulb',
        ];
    }

    public function iconClass()
    {
        return static::iconOptions()[$this->icon_key] ?? static::iconOptions()['sparkles'];
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function course()
    {
        return $this->belongsTo('App\Course', 'course_id', 'id');
    }

    public function task()
    {
        return $this->belongsTo('App\Task', 'task_id', 'id');
    }

    public function solution()
    {
        return $this->belongsTo('App\Solution', 'solution_id', 'id');
    }
}
