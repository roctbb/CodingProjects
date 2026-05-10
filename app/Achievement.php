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

    public static function visualOptions()
    {
        return [
            '' => 'Обычная иконка',
            'pipes' => 'Трубы и поток',
            'circuit' => 'Схема',
            'compass' => 'Маршрут',
            'spark' => 'Искра',
            'quantum' => 'Квантовый эффект',
            'language' => 'Язык',
        ];
    }

    public static function svgForVisualKey(?string $visualKey)
    {
        switch ($visualKey) {
            case 'pipes':
                return '<svg viewBox="0 0 48 48" role="img" aria-hidden="true" focusable="false"><path d="M10 13h12v8h7" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M38 35H26v-8h-7" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M29 21l5 5-5 5" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 27l-5-5 5-5" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="13" r="3" fill="currentColor"/><circle cx="38" cy="35" r="3" fill="currentColor"/></svg>';
            case 'circuit':
                return '<svg viewBox="0 0 48 48" role="img" aria-hidden="true" focusable="false"><path d="M12 14h10v20h14" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 24h12" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/><circle cx="12" cy="14" r="4" fill="none" stroke="currentColor" stroke-width="3"/><circle cx="36" cy="24" r="4" fill="none" stroke="currentColor" stroke-width="3"/><circle cx="36" cy="34" r="4" fill="none" stroke="currentColor" stroke-width="3"/></svg>';
            case 'compass':
                return '<svg viewBox="0 0 48 48" role="img" aria-hidden="true" focusable="false"><circle cx="24" cy="24" r="16" fill="none" stroke="currentColor" stroke-width="3"/><path d="M30 12l-4 15-12 9 4-15 12-9z" fill="currentColor"/><circle cx="24" cy="24" r="2.5" fill="var(--gc-surface, #fff)"/></svg>';
            case 'spark':
                return '<svg viewBox="0 0 48 48" role="img" aria-hidden="true" focusable="false"><path d="M24 7l3.8 11.2L39 22l-11.2 3.8L24 37l-3.8-11.2L9 22l11.2-3.8L24 7z" fill="currentColor"/><path d="M36 32l1.7 4.3L42 38l-4.3 1.7L36 44l-1.7-4.3L30 38l4.3-1.7L36 32z" fill="currentColor" opacity=".65"/></svg>';
            case 'quantum':
                return '<svg viewBox="0 0 48 48" role="img" aria-hidden="true" focusable="false"><circle cx="24" cy="24" r="4" fill="currentColor"/><ellipse cx="24" cy="24" rx="17" ry="7" fill="none" stroke="currentColor" stroke-width="3" transform="rotate(25 24 24)"/><ellipse cx="24" cy="24" rx="17" ry="7" fill="none" stroke="currentColor" stroke-width="3" transform="rotate(-25 24 24)"/><path d="M35 13l3-3m-3 25l3 3M10 24h5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';
            case 'language':
                return '<svg viewBox="0 0 48 48" role="img" aria-hidden="true" focusable="false"><path d="M10 12h28v24H10z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M16 20l5 4-5 4m9 3h8" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M15 12V8h18v4" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';
            default:
                return null;
        }
    }

    public function visualKey()
    {
        $visualKey = $this->payload['visual_key'] ?? null;

        return is_string($visualKey) && $visualKey !== '' && array_key_exists($visualKey, static::visualOptions()) ? $visualKey : null;
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
