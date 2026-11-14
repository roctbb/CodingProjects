<?php

namespace App\CoursePlanner\Models;

class ScheduleRule extends PlannerModel
{
    protected $table = 'planner_schedule_rules';
    protected $casts = ['active' => 'boolean'];
}
