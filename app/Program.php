<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Program extends Model
{
    protected $table = "programs";

    protected $fillable = [
        'name', 'description', 'image',
        'learning_avatar_poster', 'learning_avatar_poster_prompt', 'learning_avatar_poster_generated_at',
    ];

    protected $casts = [
        'learning_avatar_poster_generated_at' => 'datetime',
    ];

    public function courses()
    {
        return $this->hasMany('App\Course', 'program_id', 'id');
    }

    public function steps()
    {
        return $this->hasMany('App\ProgramStep', 'program_id', 'id')->orderBy('sort_index')->orderBy('id');
    }

    public function chapters()
    {
        return $this->hasMany('App\ProgramChapter', 'program_id', 'id')->orderBy('sort_index')->orderBy('id');
    }

    public function lessons()
    {
        return $this->hasMany('App\Lesson', 'program_id', 'id')->with('steps', 'steps.tasks')->orderBy('sort_index')->orderBy('id');
    }

    public function authors()
    {
        return $this->belongsToMany('App\User', 'programs_authors', 'program_id', 'user_id');
    }

    public function learningAvatarPosterUrl(): ?string
    {
        if (!$this->learning_avatar_poster) {
            return null;
        }

        if (Str::startsWith($this->learning_avatar_poster, ['http://', 'https://', '/'])) {
            return $this->learning_avatar_poster;
        }

        if (Storage::exists($this->learning_avatar_poster)) {
            $version = Storage::lastModified($this->learning_avatar_poster);

            return url('/media/' . $this->learning_avatar_poster) . ($version ? '?v=' . $version : '');
        }

        $publicPath = public_path($this->learning_avatar_poster);
        if (file_exists($publicPath)) {
            return url($this->learning_avatar_poster) . '?v=' . filemtime($publicPath);
        }

        return null;
    }
}
