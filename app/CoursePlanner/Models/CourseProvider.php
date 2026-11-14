<?php

namespace App\CoursePlanner\Models;

class CourseProvider extends PlannerModel
{
    protected $table = 'planner_course_providers';
    protected $casts = ['enabled' => 'boolean', 'show_in_summary' => 'boolean'];
}
