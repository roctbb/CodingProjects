<?php

namespace App\Http\Controllers;

use App\Services\SolutionRemoval;
use Illuminate\Http\Request;

final class SolutionRemovalController extends Controller
{
    public function destroy(Request $request, int $course, int $task, string $attempt, SolutionRemoval $removal)
    {
        $request->validate(['confirm' => 'required|accepted', 'return_to' => ['nullable','string','max:2000','regex:~^/insider/reviews(?:\?[^\r\n]*)?$~']]);
        $student = $removal->remove($request->user(), $course, $task, $attempt);
        if ($request->expectsJson()) return response()->json(['deleted' => true, 'redirect_url' => app(\App\Services\TaskLocation::class)
            ->task($course, \App\Task::findOrFail($task), $student)]);
        return redirect($request->input('return_to', '/insider/reviews'))->with('success', 'Решение удалено. Баллы и прогресс пересчитаны.');
    }
}
