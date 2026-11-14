<?php

namespace App\CoursePlanner\Models;

class ResourceRef extends PlannerModel
{
    protected $table = 'planner_resource_refs';
    protected $casts = ['public_metadata' => 'array'];
}
