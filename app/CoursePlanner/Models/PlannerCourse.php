<?php

namespace App\CoursePlanner\Models;

class PlannerCourse extends PlannerModel
{
    protected $table = 'planner_courses';
    protected $casts = ['revision' => 'integer', 'default_duration_minutes' => 'integer'];
}
