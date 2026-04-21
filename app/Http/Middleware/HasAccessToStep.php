<?php

namespace App\Http\Middleware;

use App\Course;
use App\ProgramStep;
use Closure;

class HasAccessToStep extends AccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if ($this->hasRole('admin')) {
            return $next($request);
        }

        $user = $this->currentUser();
        $step = ProgramStep::findOrFail($request->id);
        $course = Course::findOrFail($request->course_id);
        if ($course->teachers->contains($user)) {
            return $next($request);
        }
        if (
            $course->students->contains($user) &&
            $course->state != 'draft' &&
            ($course->steps->contains($step) && $step->lesson->isStarted($course))
        ) {
            return $next($request);
        }

        return $this->forbidden();

    }
}
