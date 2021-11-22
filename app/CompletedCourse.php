<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CompletedCourse extends Model
{
    protected $table = 'completed_courses';

    public $timestamps = false;

    protected $fillable = [
        'mark', 'name', 'provider', 'class'
    ];

    public function course()
    {
        return $this->belongsTo('App\Course', 'course_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }



}
