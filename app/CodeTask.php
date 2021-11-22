<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CodeTask extends Model
{

    protected $table = 'code_task_tests';
    public function task()
    {
        return $this->belongsTo('App\Task', 'task_id', 'id');
    }
}
