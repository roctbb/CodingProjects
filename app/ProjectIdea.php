<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectIdea extends Model
{
    //
    public $fillable = ['project_id', 'idea_id'];

    public function idea()
    {
        return $this->belongsTo("\App\Idea");
    }

    public function project()
    {
        return $this->belongsTo("\App\Project");
    }
}
