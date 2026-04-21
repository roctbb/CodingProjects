<?php

namespace App\Http\Middleware;

use App\Course;
use Closure;

class HasAccessToCourse extends AccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @param  string|null $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if ($this->hasRole('admin')) {
            return $next($request);
        }

        $user = $this->currentUser();
        $course_id = $request->route('course_id');
        if (!$course_id) {
            $course_id = $request->id;
        }
        $course = Course::findOrFail($course_id);
        if ($course->teachers->contains($user) || ($course->students->contains($user))) {
            return $next($request);
        }


        return $this->forbidden();

    }
}
