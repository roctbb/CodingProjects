<?php

namespace App\Http\Middleware;

use App\Course;
use App\Task;
use Closure;

class HasAccessToTask extends AccessMiddleware
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

        $course = Course::findOrFail($request->course_id);
        if ($course->teachers->contains($user)) {
            return $next($request);
        }

        $task = Task::findOrFail($request->id);
        if ($course->students->contains($user) && $course->steps->contains($task->step)) {
            return $next($request);
        }


        return $this->forbidden();

    }
}
