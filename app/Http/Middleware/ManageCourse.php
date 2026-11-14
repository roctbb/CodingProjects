<?php
namespace App\Http\Middleware;

use App\Course;
use App\ProgramChapter;
use App\Services\CourseAccess;
use Closure;

class ManageCourse
{
    public function handle($request, Closure $next, ?string $target = null)
    {
        $course = Course::findOrFail($request->route('course_id') ?? $request->route('id'));
        $access = app(CourseAccess::class);
        $access->manage($request->user(), $course);
        if ($target === 'lesson' && $request->route('course_id') !== null) {
            $access->lesson($course, (int) $request->route('id'));
        }
        if ($target === 'chapter') {
            ProgramChapter::where('program_id', $course->program_id)->findOrFail($request->route('chapter_id'));
        }
        return $next($request);
    }
}
