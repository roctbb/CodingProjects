<?php

namespace App\Http\Middleware;

use App\Course;
use App\ProgramStep;
use App\User;
use Closure;
use Illuminate\Support\Facades\Auth;

class HasAccessToStep
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
        if (Auth::User()->role=='admin') {
            return $next($request);
        }

        $user = User::findOrFail(Auth::User()->id);
        $step = ProgramStep::findOrFail($request->id);
        $course = Course::findOrFail($request->course_id);
        if ($course->teachers->contains($user))
        {
            return $next($request);
        }
        if ($course->students->contains($user) and $course->state != 'draft' and ($course->is_sdl or ($course->steps->contains($step) and $step->lesson->isStarted($course))))
        {
            foreach ($step->lesson->prerequisites as $prerequisite)
            {
                if (!$user->checkPrerequisite($prerequisite))
                {
                    return abort(403);
                }
            }
            return $next($request);
        }

        return abort(403);

    }
}
