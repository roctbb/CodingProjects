<?php

namespace App\Services;

use App\Achievement;
use App\Course;
use App\Solution;
use App\Task;
use App\User;
use App\Support\SqlTextSearch;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ReviewWorkspace
{
    public function courses(User $actor, bool $includeArchived = false)
    {
        return Course::query()->when(!$includeArchived, fn ($query) => $query->where('state', 'started'))
            ->when($actor->role !== 'admin', fn ($query) => $query->whereHas('teachers', fn ($teachers) => $teachers->where('users.id', $actor->id)))
            ->orderBy('name')->get(['id', 'name']);
    }

    public function filters(Request $request): array
    {
        $data = $request->validate(['q' => 'nullable|string|max:200', 'review_status' => 'nullable|in:all,new,recheck', 'course' => 'nullable|integer|min:1', 'lesson' => 'nullable|integer|min:1']);
        $filters = ['q' => trim($data['q'] ?? ''), 'review_status' => $data['review_status'] ?? 'all'];
        if (!empty($data['lesson'])) $filters['lesson'] = (int) $data['lesson'];
        return $filters;
    }

    public function queue(User $actor, ?int $courseId = null, array $filters = []): Builder
    {
        $courseIds = Course::where('state', 'started')
            ->when($actor->role !== 'admin', fn ($query) => $query->whereHas('teachers', fn ($teachers) => $teachers->where('users.id', $actor->id)))
            ->select('id');
        $query = Solution::select(['id', 'course_id', 'task_id', 'user_id', 'submitted', 'mark', 'review_skipped', 'recheck_requested', 'recheck_comment', 'updated_at'])
            ->withReviewVersion()
            ->with(['course:id,name', 'task:id,name,step_id', 'user:id,name,image,custom_title,custom_title_expires_at,avatar_frame,avatar_frame_config,avatar_frame_expires_at'])
            ->has('user')->has('task')->whereIn('course_id', $courseIds)->when($courseId !== null, fn ($query) => $query->where('course_id', $courseId))
            ->pendingReview()->orderByDesc('submitted')->orderByDesc('id');
        if(Schema::hasTable('code_submissions'))$query->addSelect(['native_submission_id'=>\App\CodeSubmission::select('id')->whereNull('legacy_id')
            ->whereColumn('solution_id','solutions.id')->whereColumn('user_id','solutions.user_id')
            ->whereColumn('course_id','solutions.course_id')->whereColumn('task_id','solutions.task_id')->limit(1)]);
        else $query->selectRaw('NULL as native_submission_id');
        if (!empty($filters['lesson'])) $query->whereHas('task.step', fn ($step) => $step->where('lesson_id', $filters['lesson']));
        if (($filters['review_status'] ?? '') === 'recheck') $query->where('recheck_requested', true);
        if (($filters['review_status'] ?? '') === 'new') $query->where(fn ($q) => $q->where('recheck_requested', false)->orWhereNull('recheck_requested'));
        if (filled($filters['q'] ?? '')) {
            $pattern = SqlTextSearch::pattern($filters['q']);
            $name = SqlTextSearch::lower('name');
            $query->where(fn ($q) => $q->whereHas('user', fn ($q) => $q->whereRaw("{$name} LIKE ? ESCAPE '!'", [$pattern]))
                ->orWhereHas('task', fn ($q) => $q->whereRaw("{$name} LIKE ? ESCAPE '!'", [$pattern]))
                ->orWhereHas('course', fn ($q) => $q->whereRaw("{$name} LIKE ? ESCAPE '!'", [$pattern])));
        }
        return $query;
    }

    public function data(Course $course, Task $task, User $student, LengthAwarePaginator $reviewQueue, bool $reviewQueueAll = false, array $reviewFilters = []): array
    {
        $solutions = Solution::where('course_id', $course->id)->where('task_id', $task->id)
            ->where('user_id', $student->id)->with('teacher')->orderByDesc('submitted')->orderByDesc('id')->get();
        $taskAchievement = Achievement::where('course_id', $course->id)->where('task_id', $task->id)->where('user_id', $student->id)->first();
        $otherWork = $this->queue(auth()->user(), $reviewQueueAll ? null : $course->id, $reviewFilters)
            ->where(fn ($query) => $query->where('course_id', '!=', $course->id)->orWhere('task_id', '!=', $task->id)->orWhere('user_id', '!=', $student->id));
        $current = $this->queue(auth()->user(), $reviewQueueAll ? null : $course->id, $reviewFilters)
            ->where('course_id', $course->id)->where('task_id', $task->id)->where('user_id', $student->id)->first();
        $nextReview = $current ? (clone $otherWork)->where(fn ($q) => $q->where('submitted', '<', $current->submitted)
            ->orWhere(fn ($q) => $q->where('submitted', $current->submitted)->where('id', '<', $current->id)))->first() : null;
        $nextReview ??= $otherWork->first();

        $reviewCourses = $this->courses(auth()->user());
        $reviewFilters += ['q' => '', 'review_status' => 'all'];
        return compact('course', 'task', 'student', 'solutions', 'taskAchievement', 'nextReview', 'reviewQueue', 'reviewQueueAll', 'reviewFilters', 'reviewCourses');
    }
}
