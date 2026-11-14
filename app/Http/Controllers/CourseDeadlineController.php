<?php
namespace App\Http\Controllers;

use App\Course;
use App\Lesson;
use App\Task;
use App\Services\CourseDeadlineService;
use Illuminate\Http\Request;

class CourseDeadlineController extends Controller
{
    public function edit(Request $request, int $course_id, string $type, int $target, CourseDeadlineService $deadlines)
    {
        $course = $this->course($request, $course_id);
        $this->target($course, $type, $target);
        return response()->json($deadlines->editor($course, $type, $target));
    }

    public function update(Request $request, int $course_id, string $type, int $target, CourseDeadlineService $deadlines)
    {
        $course = $this->course($request, $course_id);
        $input = $request->all();
        $input['stages'] ??= [];
        $policy = $deadlines->savePolicy($course, $type, $target, $input, $request->user()->id);
        return $request->expectsJson() ? response()->json(['revision' => $policy->revision]) : back()->with('status', 'Сроки сохранены.');
    }

    private function course(Request $request, int $id): Course
    {
        $course = Course::findOrFail($id);
        abort_unless($request->user()->role === 'admin' || $course->teachers()->where('users.id', $request->user()->id)->exists(), 403);
        return $course;
    }

    private function target(Course $course, string $type, int $id): void
    {
        abort_unless(in_array($type, ['task', 'lesson'], true), 404);
        $record = $type === 'task' ? Task::with('step.lesson')->findOrFail($id) : Lesson::findOrFail($id);
        abort_unless((int) ($type === 'task' ? $record->step?->lesson?->program_id : $record->program_id) === (int) $course->program_id, 404);
    }
}
