<?php
namespace App\Services;

use App\Course;
use App\User;
use Illuminate\Support\Facades\DB;

class CourseEnrollment
{
    public function invitation(string $code): ?array
    {
        if ($course = Course::where('invite', $code)->first()) return ['course'=>$course, 'remote'=>false];
        if ($course = Course::where('remote_invite', $code)->first()) return ['course'=>$course, 'remote'=>true];
        return null;
    }

    public function enroll(Course $course, User $user, bool $remote = false): bool
    {
        return DB::transaction(function () use ($course, $user, $remote) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (DB::table('course_students')->where('course_id', $course->id)->where('user_id', $locked->id)->exists()) return false;
            $course->students()->attach([$locked->id=>['is_remote'=>$remote]]);
            if ($locked->role === 'novice') { $locked->role='student'; $locked->save(); $user->role='student'; }
            return true;
        }, 5);
    }
}
