<?php

namespace App\CoursePlanner\Models;

class Provider extends PlannerModel
{
    protected $table = 'planner_providers';
    protected $casts = ['enabled' => 'boolean'];
}
