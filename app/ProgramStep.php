<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ProgramStep extends Model
{
    protected $table = 'program_steps';

    protected $fillable = [
        'name', 'description', 'image', 'start_date'
    ];

    protected $dates = [
        'start_date'
    ];


    protected $next = null;
    protected $previous = null;

    public function load_positions()
    {
        $steps = $this->lesson->steps;
        $i = $steps->pluck('id')->search($this->id);
        $this->previous = null;
        $this->next = null;
        if ($i > 0)
            $this->previous = $steps[$i - 1];
        if ($i < count($steps) - 1)
            $this->next = $steps[$i + 1];
    }


    protected $results_cache = array();

    public function program()
    {
        return $this->belongsTo('App\Program', 'program_id', 'id');
    }

    public function chapter()
    {
        return $this->belongsTo('App\ProgramChapter', 'chapter_id', 'id');
    }

    public function lesson()
    {
        return $this->belongsTo('App\Lesson', 'lesson_id', 'id');
    }

    public function questions()
    {
        return $this->hasMany('App\Question', 'step_id', 'id');
    }

    public function tasks()
    {
        return $this->hasMany('App\Task', 'step_id', 'id')->orderBy('sort_index')->orderBy('id');
    }

    public function class_tasks()
    {
        return $this->hasMany('App\Task', 'step_id', 'id')->Where('only_remote', false)->orderBy('sort_index')->orderBy('id');
    }

    public function remote_tasks()
    {
        return $this->hasMany('App\Task', 'step_id', 'id')->Where('only_class', false)->orderBy('sort_index')->orderBy('id');
    }

    public function nextStep()
    {
        if ($this->next == null)
            $this->load_positions();

        return $this->next;
    }

    public function previousStep()
    {
        if ($this->previous == null)
            $this->load_positions();
        return $this->previous;
    }

    public static function createStep($lesson, $data)
    {
        $order = 100;
        if ($lesson->steps->count() != 0)
            $order = $lesson->steps->last()->sort_index + 1;

        $step = new ProgramStep();
        $step->name = $data['name'];
        $step->notes = $data['notes'];
        $step->theory = $data['theory'];
        $step->program_id = $lesson->program->id;
        $step->lesson_id = $lesson->id;
        $step->sort_index = $order;
        $step->start_date = $lesson->start_date;

        if (array_has($data, 'notebook')) {
            $step->is_notebook = true;
            $step->theory = str_replace(array("\n", "\r"), '', $data['theory']);
        }

        $step->save();
        return $step;
    }

    public static function editStep($step, $data)
    {
        $step->name = $data['name'];
        $step->description = $data['description'];
        $step->notes = $data['notes'];
        $step->theory = $data['theory'];
        $step->video_url = $data['video_url'];

        if (array_has($data, 'notebook')) {
            $step->is_notebook = true;
            $step->theory = str_replace(array("\n", "\r"), '', $data['theory']);
        } else {
            $step->is_notebook = false;
        }

        $step->save();
        return $step;
    }

    public function stats(User $student)
    {
        if (isset($this->results_cache) and isset($this->results_cache[$student->id])) {
            return $this->results_cache[$student->id];
        }
        $results = ['percent' => 0, 'points' => 0, 'max_points' => 0];

        $tasks = $this->class_tasks;
        foreach ($tasks as $task) {
            if (!$task->is_star) $results['max_points'] += $task->max_mark;
            $mark = $student->submissions()->where('task_id', $task->id)->max('mark');
            $results['points'] += $mark ? $mark : 0;
        }
        if ($results['max_points'] != 0) {
            $results['percent'] = $results['points'] * 100 / $results['max_points'];
        }

        if (!isset($this->results_cache)) {
            $this->results_cache = [];
        }
        $this->results_cache[$student->id] = $results;
        return $results;
    }

    public function percent(User $student)
    {
        return ($this->stats($student))['percent'];
    }

    public function points(User $student)
    {
        return ($this->stats($student))['points'];
    }

    public function max_points(User $student)
    {
        return ($this->stats($student))['max_points'];
    }


}
