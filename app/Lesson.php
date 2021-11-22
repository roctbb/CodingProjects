<?php

namespace App;

use Carbon\Carbon;
use function foo\func;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Lesson extends Model
{
    protected $table = 'lessons';

    protected $fillable = [
        'name', 'description', 'image', 'start_date'
    ];

    protected $dates = [
        'start_date'
    ];

    protected $results_cache = array();

    public function program()
    {
        return $this->belongsTo('App\Program', 'program_id', 'id');
    }

    public function chapter()
    {
        return $this->belongsTo('App\ProgramChapter', 'chapter_id', 'id');
    }

    public function sdl_node()
    {
        return $this->belongsTo('App\CoreNode', 'sdl_node_id', 'id');
    }

    public function scale()
    {
        return $this->belongsTo('App\ResultScale', 'scale_id', 'id');
    }

    public function steps()
    {
        return $this->hasMany('App\ProgramStep', 'lesson_id', 'id')->with('tasks', 'tasks.consequences')->orderBy('sort_index')->orderBy('id');
    }

    public function prerequisites()
    {
        return $this->belongsToMany('App\CoreNode', 'core_prerequisites', "lesson_id", "node_id");
    }

    public function getConsequences()
    {
        $tasks = $this->tasks();

        $consequences = collect([]);
        foreach ($tasks as $task) {
            foreach ($task->consequences as $consequence) {
                $consequences->push($consequence);
            }
        }

        return $consequences->unique();
    }

    public function percent(User $student)
    {
        $points = $this->points($student);
        $max_points = $this->max_points($student);
        if ($max_points == 0) return 100;
        return $points * 100 / $max_points;

    }

    public function points(User $student)
    {
        $sum = 0;
        foreach ($this->steps as $step)
            $sum += $step->points($student);
        return $sum;
    }

    public function max_points(User $student)
    {
        $sum = 0;
        foreach ($this->steps as $step)
            $sum += $step->max_points($student);
        return $sum;
    }

    public function tasks()
    {
        $tasks = new \Illuminate\Database\Eloquent\Collection;;
        foreach ($this->steps as $step) {
            $tasks = $tasks->merge($step->tasks);
        }
        return $tasks;
    }

    public function info()
    {
        return $this->hasMany('App\LessonInfo', "lesson_id");
    }

    public function getStartDate($course)
    {
        $info = $this->info->where('course_id', $course->id)->first();
        if ($info == null) return null;
        else return $info->start_date;
    }

    public function setStartDate($course, $date)
    {
        $info = $this->info->where('course_id', $course->id)->first();
        if ($info == null) {
            $info = new LessonInfo();
            $info->lesson_id = $this->id;
            $info->course_id = $course->id;
            $info->start_date = $date;
        } else {
            $info->start_date = $date;
        }
        $info->save();
    }

    public function isStarted($course)
    {
        $info = $this->info->where('course_id', $course->id)->first();
        if ($info == null) return false;
        if ($info->start_date == null) return false;
        return $info->start_date->lt(Carbon::now()->setTime(23, 59));
    }

    public function isAvailable($course)
    {
        $user = User::findOrFail(\Auth::User()->id);
        if (!$this->isStarted($course)) return false;
        return $this->isAvailableForUser($course, $user);
    }

    public function isAvailableForUser($course, $user)
    {
        if (!$this->isStarted($course)) return false;
        if ($user->role == 'teacher') return true;
        foreach ($this->prerequisites as $prerequisite) {
            if (!$user->checkPrerequisite($prerequisite)) return false;
        }
        return true;
    }

    public function isDone($course)
    {
        $user = User::findOrFail(\Auth::User()->id);
        return $this->isDoneByUser($course, $user);
    }

    public function isDoneByUser($course, $user)
    {
        if (!$this->isStarted($course)) return false;
        if ($user->role == 'teacher') return true;
        foreach ($this->getConsequences() as $consequence) {
            if (!$user->checkPrerequisite($consequence)) return false;
        }
        return true;
    }

    public function export()
    {
        $lesson = Lesson::where('id', $this->id)->with('steps')->first();
        unset($lesson->id);
        unset($lesson->updated_at);
        foreach ($lesson->steps as $key => $step) {
            unset($lesson->steps[$key]->id);
            unset($lesson->steps[$key]->updated_at);
            unset($lesson->steps[$key]->lesson_id);
            unset($lesson->steps[$key]->program_id);

            foreach ($lesson->steps[$key]->tasks as $tkey => $task) {
                unset($lesson->steps[$key]->tasks[$tkey]->id);
                unset($lesson->steps[$key]->tasks[$tkey]->step_id);
                unset($lesson->steps[$key]->tasks[$tkey]->updated_at);
            }
        }
        return $lesson->toJson();
    }

    public function import($lesson_json)
    {
        $new_lesson = json_decode($lesson_json);
        foreach ($new_lesson->steps as $step) {
            $tasks = $step->tasks;
            unset($step->tasks);
            $new_step = new ProgramStep();
            foreach ($step as $property => $value)
                $new_step->$property = $value;
            $new_step->lesson_id = $this->id;
            $new_step->program_id = $this->program_id;
            $new_step->save();

            foreach ($tasks as $task) {
                $new_task = new Task();
                foreach ($task as $property => $value) {
                    if ($property == 'consequences') continue;
                    $new_task->$property = $value;
                }


                $new_task->step_id = $new_step->id;
                $new_task->save();
                if ($task->consequences != null)
                    foreach ($task->consequences as $consequence) {
                        $new_task->consequences()->attach($consequence->id);
                    }
            }
        }
        $this->save();
    }

    public static function getAvailableSdlLessons($user, Course $course, $idea = null)
    {
        $current_lessons = $course->user_sdl_lessons($user)->with('sdl_node', 'sdl_node.children', 'sdl_node.parents')->get();
        $version = $course->sdl_core_version;

        if ($idea == null)
        {
            $nodes = CoreNode::where('version', $version)->with('parents', 'children')->get();
        }
        else
        {
            $start_node = CoreNode::findOrFail($idea->sdl_node_id);
            $nodes = collect([]);
            $nodes->push($start_node);

            $nodes_to_look = $start_node->parents;

            while ($nodes_to_look->count() != 0)
            {
                $current_node = $nodes_to_look->pop();
                $nodes->push($current_node);

                foreach ($current_node->parents as $parent)
                {
                    if ($nodes->contains($parent) or $nodes_to_look->contains($parent)) continue;
                    $nodes_to_look->push($parent);
                }
            }
        }

        $lessons = Lesson::whereIn('sdl_node_id', $nodes->pluck('id'))->where('is_sdl', true)->with('sdl_node', 'sdl_node.children', 'sdl_node.parents')->get();
        $available_lessons = $lessons->filter(function ($lesson) use ($current_lessons) {
            if ($lesson->sdl_node->parents()->count() == 0 and !$current_lessons->contains($lesson)) return true;
            return false;
        });

        $completed_nodes = collect([]);

        foreach ($lessons as $lesson) {
            if ($lesson->percent($user) > 90) {
                $completed_nodes->push($lesson->sdl_node);
            }
        }
        foreach ($lessons as $lesson) {
            if ($current_lessons->contains($lesson) or $available_lessons->contains($lesson)) continue;
            if ($completed_nodes->count() == 0 and $lesson->sdl_node->parents->count() != 0) continue;
            if ($lesson->sdl_node->parents->pluck('id')->diff($completed_nodes->pluck('id'))->intersect($lesson->sdl_node->parents->pluck('id'))->count() != 0) continue;
            $available_lessons->push($lesson);
        }

        return $available_lessons;
    }


}
