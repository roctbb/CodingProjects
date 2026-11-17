<?php

namespace App\Services;

use App\Course;
use App\Support\CourseScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CourseScopeStatistics
{
    public function build(Course $course, CourseScope $scope, Collection $students, Collection $lessons, Collection $solutions, Collection $pending, Request $request): array
    {
        $input = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $to = CarbonImmutable::parse($input['to'] ?? now()->toDateString())->endOfDay();
        $from = CarbonImmutable::parse($input['from'] ?? $to->subDays(13)->toDateString())->startOfDay();
        abort_if($from->gt($to) || $to->diffInDays($from, true) > 366, 422, 'Выберите период не больше года.');
        $byStudent = $solutions->groupBy('user_id');
        $deadlines = app(CourseDeadlineService::class);
        $memberships = app(CourseSubgroups::class)->available()
            ? \Illuminate\Support\Facades\DB::table('course_subgroup_memberships')->where('course_id', $course->id)->whereNotNull('active_user_id')->pluck('subgroup_id', 'user_id') : collect();
        $rows = $students->map(function ($student) use ($course, $lessons, $byStudent, $deadlines, $memberships, $pending, $from, $to) {
            $attempts = $byStudent->get($student->id, collect());
            $best = $attempts->groupBy('task_id');
            $row = ['students'=>1, 'points'=>0, 'maximum'=>0, 'solved'=>0, 'tasks'=>0, 'overdue'=>0,
                'pending'=>$pending->where('user_id', $student->id)->count(),
                'active'=>$attempts->contains(fn ($s) => $s->submitted && $s->submitted->betweenIncluded($from, $to)) ? 1 : 0,
                'audience_id'=>(int) $memberships->get($student->id, 0)];
            foreach ($lessons as $lesson) {
                if (!$lesson->isStarted($course, $student)) continue;
                foreach ($lesson->steps->flatMap->tasks->where('is_hidden', false) as $task) {
                    $work = $best->get($task->id, collect());
                    $mark = (int) ($work->max('mark') ?? 0);
                    $full = $task->max_mark > 0 && $work->contains(fn ($s) => $s->mark !== null && $s->evaluatedMark() >= $task->max_mark);
                    $row['points'] += $mark;
                    if ($task->is_star) continue;
                    $row['maximum'] += $task->max_mark;
                    $row['tasks']++;
                    $row['solved'] += $full ? 1 : 0;
                    $rule = $deadlines->forTask($task, $course->id, $row['audience_id']);
                    $cutoff = $rule instanceof \App\Support\DeadlineRule ? $rule->cutoffAt() : $rule?->expiration?->copy()->addDay();
                    if (!$full && $cutoff?->lte(now())) $row['overdue']++;
                }
            }
            return $row;
        });
        $aggregate = function (Collection $items): array {
            $result = [];
            foreach (['students','points','maximum','solved','tasks','overdue','pending','active'] as $key) $result[$key] = $items->sum($key);
            $result['percent'] = $result['maximum'] > 0 ? min(100, (int) round($result['points'] / $result['maximum'] * 100)) : null;
            return $result;
        };
        $comparison = [];
        if ($scope->key === 'all' && app(CourseSubgroups::class)->available() && $course->subgroups->isNotEmpty()) {
            foreach ($course->subgroups as $group) $comparison[] = $aggregate($rows->where('audience_id', $group->id)) + ['name'=>$group->name, 'id'=>$group->id];
            if ($rows->contains('audience_id', 0)) $comparison[] = $aggregate($rows->where('audience_id', 0)) + ['name'=>'Без подгруппы', 'id'=>'none'];
        }
        return ['summary'=>$aggregate($rows), 'comparison'=>$comparison, 'label'=>$scope->label(), 'from'=>$from->toDateString(), 'to'=>$to->toDateString()];
    }
}
