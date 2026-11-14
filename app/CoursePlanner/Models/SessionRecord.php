<?php

namespace App\CoursePlanner\Models;

class SessionRecord extends PlannerModel
{
    protected $table = 'planner_session_records';
    protected $casts = ['plan_snapshot' => 'array'];
}
