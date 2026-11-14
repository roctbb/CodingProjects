<?php
namespace App\Http\Middleware;

use App\Course;
use App\Services\CourseAccess;
use Closure;

class HasAccessToStep
{
    public function handle($request, Closure $next, $guard = null)
    {
        $course = Course::findOrFail($request->route('course_id'));
        $access = app(CourseAccess::class);
        $action = $request->route()->getActionMethod();
        if (in_array($action, ['createView', 'create'], true)) {
            $access->manage($request->user(), $course);
            $access->lesson($course, (int) $request->route('id'));
        } else {
            $step = $access->step($course, (int) $request->route('id'));
            if (in_array($action, ['details', 'downloadNotebook'], true)) $access->study($request->user(), $course, $step->lesson);
            else $access->manage($request->user(), $course);
        }
        return $next($request);
    }
}
