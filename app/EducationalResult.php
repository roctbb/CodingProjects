<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EducationalResult extends Model
{
    //

    public function tasks()
    {
        return $this->hasMany('App\Task', 'result_id', 'id');
    }
}
