<?php

namespace App\Http\Middleware;

use App\Course;
use App\User;
use Closure;
use Illuminate\Support\Facades\Auth;

class HasAccessToCourse
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
        if (Auth::User()->role == 'admin') {
            return $next($request);
        }

        $user = User::findOrFail(Auth::User()->id);
        $course_id = $request->route('course_id');
        if (!$course_id) {
            $course_id = $request->id;
        }
        $course = Course::findOrFail($course_id);
        if ($course->teachers->contains($user) || ($course->students->contains($user))) {
            return $next($request);
        }


        return abort(403);

    }
}
