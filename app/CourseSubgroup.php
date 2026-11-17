<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CourseSubgroup extends Model
{
    protected $guarded = [];
    protected $casts = ['revision' => 'integer', 'current_lesson_id' => 'integer', 'current_lesson_override' => 'boolean'];

    public function course() { return $this->belongsTo(Course::class); }
}
