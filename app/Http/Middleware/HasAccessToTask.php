<?php
namespace App\Http\Middleware;

use App\Course;
use App\Solution;
use App\Services\CourseAccess;
use Closure;

class HasAccessToTask
{
    public function handle($request, Closure $next, $guard = null)
    {
        $course = Course::findOrFail($request->route('course_id'));
        $access = app(CourseAccess::class);
        $action = $request->route()->getActionMethod();
        $id = (int) $request->route('id');
        if (in_array($action, ['createForm', 'create'], true)) {
            $access->manage($request->user(), $course);
            $access->step($course, $id);
            return $next($request);
        }
        if ($action === 'estimateSolution') {
            $access->manage($request->user(), $course);
            $id = (int) Solution::where('course_id', $course->id)->findOrFail($id)->task_id;
        }
        $task = $access->task($course, $id);
        $studentActions = ['postSolution', 'askForRecheck', 'payDeadlinePenalty', 'useXpBooster', 'buyGeekPasteExtraAttempt'];
        if (!in_array($action, $studentActions, true)) {
            $access->manage($request->user(), $course);
        } else {
            $access->study($request->user(), $course, $task->step->lesson);
            abort_unless($access->manages($request->user(), $course) || $task->isVisible($request->user(), $course), 403);
        }
        return $next($request);
    }
}
