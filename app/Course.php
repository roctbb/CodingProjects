<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class Course extends Model
{
    protected $table = "courses";

    protected $fillable = [
        'name', 'description', 'image', 'start_date', 'end_date', 'state', 'level', 'invite'
    ];

    protected $dates = [
        'start_date', 'end_date'
    ];

    public function sdl_lessons()
    {
        return $this->belongsToMany('App\Lesson', 'sdl_courses_users_lessons', 'course_id', 'lesson_id');
    }

    public function user_sdl_lessons($user)
    {
        return $this->sdl_lessons()->wherePivot('user_id', $user->id);
    }

    public function students()
    {
        return $this->belongsToMany('App\User', 'course_students', 'course_id', 'user_id')->withPivot(['is_remote', 'idea_id'])->orderBy('name');
    }

    public function feedback()
    {
        return $this->hasMany('App\DetailedFeedback', 'course_id', 'id');
    }

    public function provider()
    {
        return $this->hasOne('App\Provider', 'id', 'provider_id');
    }

    public function program()
    {
        return $this->hasOne('App\Program', 'id', 'program_id');
    }

    public function teachers()
    {
        return $this->belongsToMany('App\User', 'course_teachers', 'course_id', 'user_id');
    }

    public function categories()
    {
        return $this->belongsToMany('App\CourseCategory', 'course_course_category', 'course_id', 'category_id');
    }

    public function steps()
    {
        return $this->program->steps();
    }

    public function lessons()
    {
        return $this->program->lessons();
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
            $invite = str_random(8);
        } while (Course::where('invite', $invite)->count() != 0);
        $this->invite = $invite;
        $this->remote_invite = $invite . '-R';
        $this->save();
    }

    public function isAvailable($lesson)
    {
        $user = User::findOrFail(\Auth::User()->id);
        if (!$this->isStarted($lesson)) return false;
        if ($user->role == 'teacher') return true;
        foreach ($lesson->prerequisites as $prerequisite) {
            if (!$user->checkPrerequisite($prerequisite)) return false;
        }
        return true;
    }

    public function points(User $student)
    {
        $sum = 0;
        foreach ($this->lessons as $step)
            $sum += $step->points($student, $this);
        return $sum;
    }

    public function max_points(User $student)
    {
        $sum = 0;
        foreach ($this->steps as $step)
            $sum += $step->max_points($student);
        return $sum;
    }

    public function end()
    {
        $this->state = 'ended';
        $this->end_date = Carbon::now();

        CompletedCourse::where('course_id', $this->id)->delete();

        $cert_request = ['courses' => []];

        array_push($cert_request['courses'], [
            'name' => $this->name,
            'date' => Carbon::now()->format('d.m.Y'),
            'teachers' => [
                ['name' => $this->teachers[0]->name]
            ],
            'students' => []

        ]);

        foreach ($this->students as $student) {
            array_push($cert_request['courses'][0]['students'], [
                'id' => $student->id,
                'name' => $student->name,
                'mark' => Mark::getMark($this->points($student), $this->max_points($student))
            ]);
        }

        $client = new \GuzzleHttp\Client();
        $res = $client->post('https://cert.geekclass.ru', [
            'body' => json_encode($cert_request)
        ]);
        $cert_result = json_decode($res->getBody()->getContents());


        foreach ($this->students as $student) {
            $completed_course = new CompletedCourse();
            $completed_course->name = $this->name;
            $completed_course->provider = $this->provider->short_name;
            $completed_course->user_id = $student->id;
            $completed_course->course_id = $this->id;
            $id = $student->id;
            $completed_course->cert_link = $cert_result->$id->link;
            $completed_course->mark = Mark::getMark($this->points($student), $this->max_points($student));
            $completed_course->save();
        }

        $this->save();
    }

    public function getPercent(User $user)
    {
        $course = $this;
        $lessons = $course->lessons->filter(function ($lesson) use ($course) {
            return $lesson->isStarted($this);
        });

        $temp_steps = collect([]);
        foreach ($lessons as $lesson) {
            $temp_steps = $temp_steps->merge($lesson->steps);
        }

        $percent = 100;
        $max_points = 0;
        $points = 0;
        foreach ($temp_steps as $step) {
            $tasks = $step->class_tasks;

            foreach ($tasks as $task) {
                if (!$task->is_star) $max_points += $task->max_mark;
                $points += $user->submissions->where('task_id', $task->id)->max('mark');
            }
        }
        if ($max_points != 0) {
            $percent = min(100, $points * 100 / $max_points);
        }
        return $percent;
    }

    public function average_mark()
    {
        return $this->feedback()->where('is_filled', true)->average('mark');
    }

    public function marks_count()
    {
        return $this->feedback()->where('is_filled', true)->count();
    }

    public function recent_marks_count()
    {
        return $this->feedback()->where('is_filled', true)->where('created_at', '>', Carbon::now()->addWeeks(-1))->count();
    }

    public function recent_mark()
    {
        return $this->feedback()->where('is_filled', true)->where('created_at', '>', Carbon::now()->addWeeks(-1))->average('mark');
    }
}
