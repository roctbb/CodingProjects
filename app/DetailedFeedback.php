<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DetailedFeedback extends Model
{
    protected $table = 'detailed_feedback';

    public static function getRecord($course, $student)
    {
        $record = new self();
        $record->access_key = Str::uuid();
        $record->course_id = $course->id;
        $record->user_id = $student->id;
        $record->save();

        return $record;
    }

    public static function getForms($user, $key)
    {
        $records = self::where('user_id', $user->id)->where('access_key', $key)->where('is_filled', false)->get();
        return $records;
    }

    public function course()
    {
        return $this->belongsTo('App\Course', 'course_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }
}
