<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Course extends Model
{
    public ?\App\Support\CourseScope $participantScope = null;
    protected $table = "courses";

    protected $fillable = [
        'name', 'description', 'image', 'icon_key', 'start_date', 'end_date', 'state', 'level', 'invite', 'default_chapter_id',
        'learning_avatar_poster', 'learning_avatar_poster_prompt', 'learning_avatar_poster_generated_at'
    ];

    protected $casts = [
        'current_lesson_id' => 'integer',
        'hide_leaderboard' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'learning_avatar_poster_generated_at' => 'datetime',
    ];

    public static function availableForEnroll()
    {
        return self::where('mode', 'zoom')->where('state', 'draft')->get();
    }

    public function students()
    {
        return $this->belongsToMany('App\User', 'course_students', 'course_id', 'user_id')
            ->withPivot(['is_remote', 'hidden_from_stats'])
            ->orderBy('name');
    }

    public function subgroups()
    {
        return $this->hasMany(CourseSubgroup::class)->orderBy('name')->orderBy('id');
    }

    public function statsStudents()
    {
        return $this->belongsToMany('App\User', 'course_students', 'course_id', 'user_id')
            ->withPivot(['is_remote', 'hidden_from_stats'])
            ->wherePivot('hidden_from_stats', false)
            ->whereNotExists(fn($staff)=>$staff->selectRaw('1')->from('course_teachers')
                ->whereColumn('course_teachers.course_id','course_students.course_id')->whereColumn('course_teachers.user_id','users.id'))
            ->orderBy('name')->orderBy('users.id');
    }

    public function program()
    {
        return $this->belongsTo('App\Program', 'program_id', 'id');
    }

    public function defaultChapter()
    {
        return $this->belongsTo('App\ProgramChapter', 'default_chapter_id', 'id');
    }

    public function currentLesson()
    {
        return $this->belongsTo(Lesson::class, 'current_lesson_id');
    }

    public function currentLessonFor(User $viewer): ?Lesson
    {
        $scope = $this->participantScope ?? \App\Support\CourseScope::for($this, $viewer);
        $currentId = $scope->group?->current_lesson_override ? $scope->group->current_lesson_id : $this->current_lesson_id;
        if (!$currentId || !$this->program) {
            return null;
        }

        $lesson = $this->program->lessons->firstWhere('id', $currentId);
        if (!$lesson || !$this->program->chapters->contains('id', $lesson->chapter_id)
            || !$lesson->isAvailableForUser($this, $viewer)) {
            return null;
        }

        $hasSteps = $lesson->relationLoaded('steps') ? $lesson->steps->isNotEmpty() : $lesson->steps()->exists();
        return $hasSteps ? $lesson : null;
    }

    public function teachers()
    {
        return $this->belongsToMany('App\User', 'course_teachers', 'course_id', 'user_id')
            ->withPivot(['hidden_from_stats']);
    }

    public function statsTeachers()
    {
        return $this->belongsToMany('App\User', 'course_teachers', 'course_id', 'user_id')
            ->withPivot(['hidden_from_stats'])
            ->wherePivot('hidden_from_stats', false);
    }

    public function imageUrl(): string
    {
        if (!$this->image || $this->image === 'course_avatars/blank.png') {
            return url('/images/sidebar-wave.svg');
        }

        if (Str::startsWith($this->image, ['http://', 'https://', '/'])) {
            return $this->image;
        }

        if (Storage::exists($this->image)) {
            return url('/media/' . $this->image);
        }

        return url($this->image);
    }

    public function learningAvatarPosterUrl(): ?string
    {
        $program = $this->relationLoaded('program')
            ? $this->program
            : ($this->exists && $this->program_id ? $this->program()->first() : null);
        if ($program) {
            $programPosterUrl = $program->learningAvatarPosterUrl();
            if ($programPosterUrl) {
                return $programPosterUrl;
            }
        }

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

    public function categories()
    {
        return $this->belongsToMany('App\CourseCategory', 'course_course_category', 'course_id', 'category_id');
    }

    public function steps()
    {
        return $this->hasManyThrough('App\ProgramStep', 'App\Program', 'id', 'program_id', 'program_id');
    }

    public function lessons()
    {
        return $this->hasManyThrough('App\Lesson', 'App\Program', 'id', 'program_id', 'program_id');
    }

    public function solutions()
    {
        return $this->hasMany('App\Solution', 'course_id', 'id');
    }

    public function start()
    {
        $this->state = 'started';
        $this->start_date = Carbon::now();
        do {
            $invite = Str::random(8);
        } while (Course::where('invite', $invite)->count() != 0);
        $this->invite = $invite;
        $this->remote_invite = $invite . '-R';
        $this->save();
    }

    public function isAvailable($lesson)
    {
        $user = User::findOrFail(\Auth::User()->id);
        return $lesson->isAvailableForUser($this, $user);
    }

    public function points(User $student)
    {
        $sum = 0;
        foreach ($this->lessons as $lesson) {
            if (!$lesson->isStarted($this, $student)) {
                continue;
            }

            foreach ($lesson->steps as $step) {
                foreach ($step->tasks as $task) {
                    if ($task->is_hidden) {
                        continue;
                    }
                    $sum += (int) $student->submissions->where('course_id', $this->id)->where('task_id', $task->id)->max('mark');
                }
            }
        }
        return $sum;
    }

    public function max_points(User $student)
    {
        $sum = 0;
        foreach ($this->lessons as $lesson) {
            if (!$lesson->isStarted($this, $student)) {
                continue;
            }

            foreach ($lesson->steps as $step) {
                foreach ($step->tasks as $task) {
                    if (!$task->is_star && !$task->is_hidden) {
                        $sum += $task->max_mark;
                    }
                }
            }
        }
        return $sum;
    }

    public function end()
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
        $locked = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
        if ($locked->state === 'ended') { $this->refresh(); return; }
        $locked->state = 'ended';
        $locked->end_date = Carbon::now();

        CompletedCourse::where('course_id', $this->id)->delete();

        foreach ($locked->students as $student) {
            $completed_course = new CompletedCourse();
            $completed_course->name = $locked->name;
            $completed_course->user_id = $student->id;
            $completed_course->course_id = $this->id;
            $completed_course->mark = Mark::getMark($locked->points($student), $locked->max_points($student));
            $completed_course->save();
        }

        $locked->save();
        $this->refresh();
        });
    }

    public function getPercent(User $user)
    {
        $max_points = $this->max_points($user);
        if ($max_points == 0) {
            return 0;
        }

        return min(100, $this->points($user) * 100 / $max_points);
    }

}
