<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $table = "programs";

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
}
