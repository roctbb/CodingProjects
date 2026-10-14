<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'payload',
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'published_at' => 'datetime',
    ];

    public function trophyImageUrl(): ?string
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        $path = $payload['trophy_image'] ?? null;

        if (!is_string($path) || trim($path) === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        if (Storage::exists($path)) {
            $version = Storage::lastModified($path);

            return url('/media/' . $path) . ($version ? '?v=' . $version : '');
        }

        $publicPath = public_path($path);
        if (file_exists($publicPath)) {
            return url($path) . '?v=' . filemtime($publicPath);
        }

        return null;
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
