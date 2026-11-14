<?php

namespace App\CoursePlanner\Models;

class PlannerChange extends PlannerModel
{
    protected $table = 'planner_changes';
    protected $casts = ['delta' => 'array', 'result' => 'array', 'undoable' => 'boolean'];
}
