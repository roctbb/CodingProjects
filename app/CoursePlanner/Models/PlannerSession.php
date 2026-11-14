<?php

namespace App\CoursePlanner\Models;

class PlannerSession extends PlannerModel
{
    protected $table = 'planner_sessions';
    protected $casts = ['pinned' => 'boolean'];
}
