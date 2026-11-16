<?php

namespace App\Services;

use App\Course;
use App\Solution;
use App\Support\SqlTextSearch;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RecentReviews
{
    public function forTeacher(User $actor, ?int $courseId, array $filters): LengthAwarePaginator
    {
        abort_unless(in_array($actor->role, ['teacher', 'admin'], true), 403);
        $courses = Course::query()->when($actor->role !== 'admin', fn ($query) => $query
            ->whereHas('teachers', fn ($teachers) => $teachers->where('users.id', $actor->id)))->select('id');
        $native = Schema::hasTable('code_submissions') && Schema::hasTable('code_execution_runs');
        $columns = Schema::getColumnListing('solutions');
        $evaluation = fn ($alias) => 'COALESCE('.implode(',', array_map(fn ($field) => $alias.'.'.$field,
            array_values(array_filter(['evaluated_mark', 'raw_mark', 'mark'], fn ($field) => in_array($field, $columns, true))))).')';
        // COALESCE requires two arguments on SQLite; very old schemas only contain mark.
        if (!in_array('raw_mark', $columns, true) && !in_array('evaluated_mark', $columns, true)) $evaluation = fn ($alias) => $alias.'.mark';
        $pending = Solution::select('id')->pendingReview();
        $legacy = $this->base('solutions as s', 's', $courses, $courseId, $filters)
            ->whereNotNull('s.submitted')
            ->selectRaw("'solution' as kind, CAST(s.id AS TEXT) as item_id, s.id as sort_id, s.submitted as sent_at,
                CASE WHEN s.mark IS NOT NULL THEN 'completed' WHEN s.review_skipped THEN 'skipped' ELSE 'awaiting_review' END as run_state,
                CASE WHEN s.mark IS NOT NULL THEN ".$evaluation('s')." ELSE NULL END as points, s.mark as xp,
                CASE WHEN s.recheck_requested THEN 1 ELSE 0 END as recheck")
            ->selectSub((clone $pending)->whereColumn('solutions.id', 's.id'), 'needs_review');
        if ($native) {
            $legacy->whereNotExists(fn ($query) => $query->selectRaw('1')->from('code_submissions as duplicate')
                ->whereNull('duplicate.legacy_id')->whereColumn('duplicate.solution_id', 's.id')
                ->whereColumn('duplicate.course_id', 's.course_id')->whereColumn('duplicate.task_id', 's.task_id')->whereColumn('duplicate.user_id', 's.user_id'));
            $submissions = $this->base('code_submissions as n', 'n', $courses, $courseId, $filters)
                ->leftJoin('code_execution_runs as runs', fn ($join) => $join->on('runs.id', '=', 'n.current_run_id')->on('runs.submission_id', '=', 'n.id'))
                ->leftJoin('solutions as awarded', fn ($join) => $join->on('awarded.id', '=', 'n.solution_id')
                    ->on('awarded.course_id', '=', 'n.course_id')->on('awarded.task_id', '=', 'n.task_id')->on('awarded.user_id', '=', 'n.user_id'))
                ->whereNull('n.legacy_id')
                ->selectRaw("'submission' as kind, CAST(n.id AS TEXT) as item_id, NULL as sort_id, n.created_at as sent_at,
                    COALESCE(runs.state, 'queued') as run_state, CASE WHEN runs.state = 'completed' THEN runs.points ELSE NULL END as points,
                    awarded.mark as xp, CASE WHEN awarded.recheck_requested THEN 1 ELSE 0 END as recheck")
                ->selectSub((clone $pending)->whereColumn('solutions.id', 'n.solution_id')
                    ->whereColumn('solutions.course_id', 'n.course_id')->whereColumn('solutions.task_id', 'n.task_id')
                    ->whereColumn('solutions.user_id', 'n.user_id'), 'needs_review');
            $legacy->unionAll($submissions);
        }
        $status = "CASE WHEN run_state = 'error' THEN 'error' WHEN needs_review IS NOT NULL OR run_state = 'awaiting_review' THEN 'pending'
            WHEN run_state = 'completed' THEN 'graded' WHEN run_state = 'skipped' THEN 'skipped' ELSE 'checking' END";
        $query = DB::query()->fromSub($legacy, 'recent')->select('recent.*')->selectRaw($status.' as status');
        if (($filters['activity_status'] ?? 'all') !== 'all') $query->whereRaw($status.' = ?', [$filters['activity_status']]);
        $items = $query->orderByDesc('sent_at')->orderByDesc('kind')->orderByDesc('sort_id')->orderByDesc('item_id')->paginate(40)->withQueryString();
        $items->getCollection()->transform(function ($item) {
            $item->sent_at = Carbon::parse($item->sent_at);
            $task = (new Task())->forceFill(['id' => $item->task_id, 'step_id' => $item->step_id]);
            $item->url = app(TaskLocation::class)->task((int) $item->course_id, $task, (int) $item->user_id,
                $item->kind === 'submission' ? $item->item_id : 'solution-'.$item->item_id);
            return $item;
        });
        return $items;
    }

    private function base(string $table, string $alias, $courses, ?int $courseId, array $filters): Builder
    {
        $query = DB::table($table)->join('courses as course', 'course.id', '=', $alias.'.course_id')
            ->join('users as author', 'author.id', '=', $alias.'.user_id')->join('tasks as task', 'task.id', '=', $alias.'.task_id')
            ->join('program_steps as step', 'step.id', '=', 'task.step_id')->join('lessons as lesson', 'lesson.id', '=', 'step.lesson_id')
            ->whereColumn('lesson.program_id', 'course.program_id')->whereIn($alias.'.course_id', clone $courses)
            ->when($courseId !== null, fn ($query) => $query->where($alias.'.course_id', $courseId))
            ->select([$alias.'.course_id', $alias.'.task_id', $alias.'.user_id', 'task.step_id', 'task.max_mark',
                'author.name as student_name', 'task.name as task_name', 'course.name as course_name', 'lesson.name as lesson_name']);
        if (!empty($filters['lesson'])) $query->where('lesson.id', $filters['lesson']);
        if (filled($filters['q'] ?? '')) {
            $pattern = SqlTextSearch::pattern($filters['q']);
            $query->where(function ($query) use ($pattern) {
                foreach (['author.name', 'task.name', 'course.name'] as $column) $query->orWhereRaw(SqlTextSearch::lower($column)." LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
        return $query;
    }
}
