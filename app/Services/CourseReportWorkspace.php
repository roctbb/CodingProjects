<?php

namespace App\Services;

use App\ActionLog;
use App\Course;
use App\CourseStudentPoints;
use App\Jobs\RecalculateCoursePoints;
use App\LessonStudentStats;
use App\Solution;
use App\Support\CourseReportLessons;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class CourseReportWorkspace
{
    public function data(int $id, User $viewer, Request $request): array
    {
        $fields = array_values(array_intersect(Schema::getColumnListing('solutions'), [
            'id', 'course_id', 'task_id', 'user_id', 'submitted', 'checked', 'created_at', 'mark', 'raw_mark', 'evaluated_mark',
            'review_skipped', 'recheck_requested', 'deadline_penalty_amount', 'deadline_penalty_days', 'deadline_penalty_paid_at',
            'deadline_penalty_waived_at', 'xp_booster_amount', 'xp_booster_used_at', 'geekpaste_code_id', 'geekpaste_ai_warning',
            'geekpaste_ai_confidence', 'geekpaste_llm_probability', 'geekpaste_similarity_checked', 'geekpaste_similarity_warning',
            'geekpaste_similarity_critical', 'geekpaste_similarity_max_percent', 'geekpaste_similarity_matches_count',
            'geekpaste_integrity_synced_at', 'geekpaste_integrity_dismissed_at',
        ]));
        $course = Course::with(['teachers', 'students', 'statsStudents', 'program.chapters', 'program.lessons.info',
            'program.lessons.earlyAccesses' => fn ($query) => $query->where('course_id', $id),
            'program.lessons.steps.tasks.blockedTasks' => fn ($query) => $query->where('course_id', $id),
        ])->findOrFail($id);
        abort_unless(app(CourseAccess::class)->manages($viewer, $course), 403);
        [$lessons, $reportChapterGroups] = CourseReportLessons::groups($course);
        $students = $course->statsStudents;
        $requested = $request->query('student');
        abort_if($requested !== null && !$students->contains('id', (int) $requested), 404);
        $tasks = $lessons->flatMap->steps->flatMap->tasks;
        // Integrity ignores empty sources. Project only their presence, never the answer itself.
        $trim = \DB::connection()->getDriverName() === 'pgsql' ? 'BTRIM(text, ?)' : 'TRIM(text, ?)';
        $solutions = Solution::where('course_id', $id)->whereIn('task_id', $tasks->pluck('id'))
            ->whereIn('user_id', $students->pluck('id'))->select($fields)
            ->selectRaw("CASE WHEN {$trim} <> '' THEN '1' ELSE '' END AS text", [" \t\n\r\x0B"])
            ->get()->groupBy('task_id');
        foreach ($lessons as $lesson) foreach ($lesson->steps as $step) {
            $step->setRelation('lesson', $lesson);
            foreach ($step->tasks as $task) {
                $task->setRelation('step', $step);
                $task->setRelation('solutions', $solutions->get($task->id, collect()));
            }
        }
        $geekPasteIntegrityStats = app(CourseReportIntegrity::class)->forStudents($students, $lessons);
        $cached = CourseStudentPoints::where('course_id', $id)->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id');
        $missing = collect();
        foreach ($students as $student) {
            $score = $cached->get($student->id);
            $student->points = $score?->points ?? 0;
            $student->max_points = $score?->max_points ?? 0;
            $student->percent = $score?->percent ?? 0;
            if (!$score) $missing->push($student->id);
        }
        $pending = $course->state === 'started'
            ? Solution::where('course_id', $id)->pendingReview()->get(['id', 'task_id', 'user_id']) : collect();
        $pendingByStudent = $pending->countBy('user_id');
        foreach ($students as $student) $student->pending_count = $pendingByStudent->get($student->id, 0);
        $attention = $students->filter(fn ($student) => ($student->max_points > 0 && $student->percent < 50)
            || in_array($geekPasteIntegrityStats[$student->id]['overall']['risk_level'] ?? 'none', ['high', 'medium'], true));
        $reportSummary = ['students' => $students->count(), 'attention' => $attention->count(), 'pending' => $pending->count()];
        if ($request->query('attention') === '1' && !$request->boolean('report_detail')) $students = $attention->values();
        $selectedStudent = $students->firstWhere('id', (int) $requested) ?? $students->first();
        $selectedId = $selectedStudent?->id;
        // Each detail view receives only the selected student's attempts and blocks.
        foreach ($tasks as $task) {
            $task->setRelation('solutions', $task->solutions->where('user_id', $selectedId)->values());
            $task->setRelation('blockedTasks', $task->blockedTasks->where('user_id', $selectedId)->where('course_id', $id)->values());
        }
        $lessonStats = LessonStudentStats::where('course_id', $id)->whereIn('lesson_id', $lessons->pluck('id'))
            ->where('student_id', $selectedId)->get()->groupBy('lesson_id')->map(fn ($rows) => $rows->keyBy('student_id'));
        if ($selectedStudent) foreach ($lessons as $lesson) {
            if (!$lessonStats->has($lesson->id)) $lessonStats->put($lesson->id, collect());
            if (!$lessonStats[$lesson->id]->has($selectedId)) {
                $missing->push($selectedId);
                $lessonStats[$lesson->id]->put($selectedId, new LessonStudentStats([
                    'course_id' => $id, 'lesson_id' => $lesson->id, 'student_id' => $selectedId,
                    'points' => 0, 'max_points' => 0, 'percent' => 0,
                ]));
            }
        }
        if ($missing->isNotEmpty()) RecalculateCoursePoints::dispatch($id, $missing->unique()->values()->all());
        $steps = $lessons->flatMap->steps;
        [$pulse_keys, $pulse_values, $task_keys, $task_values] = $this->activity($course, $selectedStudent, $steps->pluck('id'));
        $pendingReviewPairs = $pending->mapWithKeys(fn ($solution) => [$solution->user_id.':'.$solution->task_id => true]);
        $pendingSolutionsCount = $pending->count();
        $user = $viewer;
        return compact('course', 'user', 'students', 'selectedStudent', 'reportSummary', 'steps', 'lessons', 'reportChapterGroups',
            'lessonStats', 'geekPasteIntegrityStats', 'pendingReviewPairs', 'pendingSolutionsCount',
            'pulse_keys', 'pulse_values', 'task_keys', 'task_values');
    }

    private function activity(Course $course, ?User $student, Collection $steps): array
    {
        $keys = collect(); $values = collect(); $taskKeys = collect(); $taskValues = collect();
        if (!$student) return [$keys, $values, $taskKeys, $taskValues];
        $from = now()->subWeeks(2);
        $views = ActionLog::where('user_id', $student->id)->where('created_at', '>', $from)
            ->where(fn ($query) => $query->where(fn ($query) => $query->where('type', 'course')->where('object_id', $course->id))
                ->orWhere(fn ($query) => $query->where('type', 'step')->whereIn('object_id', $steps)))
            ->get(['created_at'])->groupBy(fn ($row) => $row->created_at->format('Y-m-d'))->map->count();
        $scores = Solution::where('course_id', $course->id)->where('user_id', $student->id)->where('created_at', '>', $from)
            ->get(['created_at', 'mark'])->groupBy(fn ($row) => $row->created_at->format('Y-m-d'))->map->sum('mark');
        $days = collect();
        for ($day = $from->copy()->startOfDay(); $day->lte(now()); $day->addDay()) $days->push($day->format('Y-m-d'));
        foreach ([[$views, $keys, $values], [$scores, $taskKeys, $taskValues]] as [$series, $dates, $points]) {
            if ($series->isEmpty()) continue;
            $dates->put($student->id, $days->toJson());
            $points->put($student->id, $days->map(fn ($day) => $series->get($day, 0))->toJson());
        }
        return [$keys, $values, $taskKeys, $taskValues];
    }
}
