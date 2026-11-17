<?php

namespace App\Policies;

use App\Course;
use App\User;

class CoursePlannerPolicy
{
    public function view(User $user, Course $course): bool
    {
        return $this->manage($user, $course) || ($course->state !== 'draft' && $course->students()->where('users.id', $user->id)->exists());
    }

    public function manage(User $user, Course $course): bool
    {
        return $user->role === 'admin' || ($user->role === 'teacher' && $course->teachers()->where('users.id', $user->id)->exists());
    }
}
