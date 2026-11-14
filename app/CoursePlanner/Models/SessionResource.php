<?php

namespace App\CoursePlanner\Models;

class SessionResource extends PlannerModel
{
    protected $table = 'planner_session_resources';
    protected $casts = ['resource_snapshot' => 'array'];
}
