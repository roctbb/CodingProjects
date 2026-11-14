<?php

namespace App\Policies;

use App\Course;
use App\User;

class CoursePlannerPolicy
{
    public function manage(User $user, Course $course): bool
    {
        return $user->role === 'admin' || ($user->role === 'teacher' && $course->teachers()->where('users.id', $user->id)->exists());
    }
}
