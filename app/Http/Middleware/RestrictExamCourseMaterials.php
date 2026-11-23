<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RestrictExamCourseMaterials
{
    public function handle(Request $request, Closure $next)
    {
        if ((string) config('services.geekexam_course_access_secret') === '') {
            return $next($request);
        }

        $uri = $request->route()->uri();
        if (!Str::is([
            'open/course-material-access', 'insider/courses/{*', 'open/steps/*', 'insider/code-submissions/*', 'insider/code-test-reports/*',
            'shared/code-submissions/*', 'legacy/geekpaste/*', 'material-resources/*',
            'insider/material-history/*', 'insider/material-code-history/*',
        ], $uri)) {
            return $next($request);
        }

        $user = $request->user();
        if ($user && DB::table('geekexam_course_restrictions')->useWritePdo()
            ->where('user_id', $user->id)->whereNull('released_at')->exists()) {
            $message = 'Материалы курсов будут доступны после завершения строгой попытки в GeekExam.';
            $response = $request->expectsJson()
                ? response()->json(['error' => 'strict_exam_active', 'message' => $message], 403)
                : response()->view('courses.exam-restricted', ['message' => $message], 403);
        } else {
            $request->attributes->set('watch_course_access', $user !== null && $request->isMethod('get'));
            $response = $next($request);
        }
        // Public and private versions of the same URL must never share cached material.
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
