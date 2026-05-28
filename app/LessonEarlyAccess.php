<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LessonEarlyAccess extends Model
{
    protected $table = 'lesson_early_accesses';

    protected $fillable = [
        'course_id', 'lesson_id', 'user_id', 'source', 'granted_by',
    ];

    const SOURCE_PURCHASE = 'purchase';
    const SOURCE_PET = 'pet';
    const SOURCE_TEACHER = 'teacher';

    public function course()
    {
        return $this->belongsTo('App\Course', 'course_id', 'id');
    }

    public function lesson()
    {
        return $this->belongsTo('App\Lesson', 'lesson_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function grantedBy()
    {
        return $this->belongsTo('App\User', 'granted_by', 'id');
    }
}
