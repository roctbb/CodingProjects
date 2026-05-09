<?php

namespace App\Http\Middleware;

use App\Course;
use App\ProgramStep;
use App\Task;
use App\User;
use Closure;
use Illuminate\Support\Facades\Auth;

class HasAccessToTask
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

        $course = Course::with('teachers', 'students', 'steps')->findOrFail($request->course_id);
        if ($course->teachers->contains($user)) {
            return $next($request);
        }

        $task = Task::findOrFail($request->id);
        if (
            $course->students->contains($user)
            && $course->steps->contains($task->step)
            && $task->isVisible($user, $course)
        ) {
            return $next($request);
        }


        return abort(403);

    }
}
