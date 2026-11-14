<?php

namespace App\Http\Controllers;

use App\Course;
use App\Solution;
use Illuminate\Http\Request;

final class StudentCourseTasksController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'course']);
    }

    public function index(int $id, Request $request)
    {
        $request->validate(['chapter' => ['nullable', 'integer', 'min:1'], 'state' => ['nullable', 'in:all,pending,done,review']]);
        $user = $request->user();
        $course = Course::with([
            'teachers', 'students', 'program.chapters',
            'program.lessons' => fn ($query) => $query->select(['id', 'program_id', 'chapter_id', 'name', 'sort_index'])->orderBy('sort_index')->orderBy('id'),
            'program.lessons.info' => fn ($query) => $query->where('course_id', $id),
            'program.lessons.earlyAccesses' => fn ($query) => $query->where('course_id', $id)->where('user_id', $user->id),
        ])->findOrFail($id);
        abort_unless($user->role === 'student' && $course->students->contains('id', $user->id), 403);

        $chapters = $course->program->chapters;
        $available = $chapters->filter(fn ($chapter) => $chapter->isAvailableForUser($course, $user));
        $chapter = $request->filled('chapter') ? $chapters->firstWhere('id', $request->integer('chapter'))
            : ($available->firstWhere('id', $course->currentLessonFor($user)?->chapter_id)
                ?? $available->firstWhere('id', $course->default_chapter_id) ?? $available->first() ?? $chapters->first());
        abort_if($request->filled('chapter') && !$chapter, 404);

        $lessons = $course->program->lessons->where('chapter_id', $chapter?->id)
            ->filter(fn ($lesson) => $lesson->isAvailableForUser($course, $user))->values();
        $lessons->load([
            'steps:id,lesson_id,name,sort_index',
            'steps.tasks' => fn ($query) => $query->select(['id', 'step_id', 'name', 'max_mark', 'is_hidden', 'is_star', 'sort_index']),
            'steps.tasks.solutions' => fn ($query) => $query->where('course_id', $id)->where('user_id', $user->id)
                ->select(['id', 'task_id', 'course_id', 'user_id', 'mark', 'submitted', 'recheck_requested', 'review_skipped', 'updated_at'])->withEvaluation(),
            'steps.tasks.blockedTasks' => fn ($query) => $query->where('course_id', $id)->where('user_id', $user->id),
            'steps.tasks.deadlines' => fn ($query) => $query->where('course_id', $id),
        ]);
        $taskGroups = $lessons->map(function ($lesson) use ($course, $user) {
            $rows = $lesson->steps->flatMap(fn ($step) => $step->tasks->filter(fn ($task) => $task->isVisible($user, $course))->map(function ($task) use ($step, $course, $user) {
                $state = match (true) {
                    $task->isBlocked($user->id, $course->id) => 'blocked',
                    $task->isOnCheck($user->id) => 'review',
                    $task->isFullDone($user->id) => 'done',
                    $task->isSubmitted($user->id) => 'partial',
                    default => 'empty',
                };
                $best = Solution::bestScoredIn($task->solutions);
                return compact('task', 'step', 'state', 'best');
            }));
            return compact('lesson', 'rows');
        })->filter(fn ($group) => $group['rows']->isNotEmpty())->values();
        $allRows = $taskGroups->flatMap(fn ($group) => $group['rows']);
        $state = $request->input('state') ?: 'all';
        $counts = ['all' => $allRows->count(), 'pending' => $allRows->where('state', '!=', 'done')->count(),
            'done' => $allRows->where('state', 'done')->count(), 'review' => $allRows->where('state', 'review')->count()];

        return view('courses.chapter-tasks', compact('course', 'user', 'chapter', 'available', 'taskGroups', 'counts', 'state'));
    }
}
