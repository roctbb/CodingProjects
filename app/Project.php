<?php
/**
 * Created by PhpStorm.
 * User: AlexNerru
 * Date: 03.09.2017
 * Time: 23:06
 */

namespace App;


use Illuminate\Database\Eloquent\Model;


class Project extends Model
{

    protected $table = "projects";

    protected $fillable = [
        'name', 'short_description', 'description', 'type', 'url', 'author_id', 'image'
    ];

    public function team()
    {
        return $this->belongsToMany('App\User', 'project_students', 'project_id', 'user_id');
    }

    public static function createProject($data)
    {
        $project = Project::create(['author_id' => \Auth::id(), 'name' => $data['name'], 'short_description' => clean($data['short_description']), 'description' => clean($data['description'])]);

        return $project;
    }

    public function author()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }
    public function getRewards()
    {
        return $this->hasMany('App\ProjectAward');
    }
    
    public function getRewardAmount()
    {
        return $this->hasMany('App\ProjectAward')->sum('amount');
    }
    public function author_()
    {
        return $this->belongsTo('App\User', 'author_id', 'id');
    }
    public function task()
    {
        return $this->belongsTo('App\Task', 'task_id', 'id');
    }
    public function task_course()
    {
        return $this->belongsTo('App\Course', 'course_id', 'id');
    }

    public function basedOn()
    {
        return $this->hasMany('App\ProjectIdea');
    }

    public function editProject($data)
    {

        $this->name = $data['name'];
        $this->short_description = clean($data['short_description']);
        $this->description = clean($data['description']);
        $this->type = $data['type'];
        $this->url = $data['url'];
        $this->save();
        return $this;
    }
}
