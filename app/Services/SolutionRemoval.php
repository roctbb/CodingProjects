<?php

namespace App\Services;

use App\CodeExecutionRun;
use App\CodeSubmission;
use App\Course;
use App\CourseStudentPoints;
use App\LessonStudentStats;
use App\Solution;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A submitted attempt is removed, rather than retained as a zero grade. */
final class SolutionRemoval
{
    public function remove(User $actor, int $courseId, int $taskId, string $attempt): int
    {
        $course = Course::findOrFail($courseId);
        abort_unless(app(CourseAccess::class)->manages($actor, $course), 403);
        $task = app(CourseAccess::class)->task($course, $taskId);
        return DB::transaction(function () use ($actor, $course, $task, $attempt) {
            $source = null;
            if (preg_match('/^solution-([1-9][0-9]*)$/D', $attempt, $match)) {
                $solution = Solution::where('course_id', $course->id)->where('task_id', $task->id)
                    ->whereNotNull('submitted')->lockForUpdate()->findOrFail((int)$match[1]);
                if (Schema::hasTable('code_submissions')) {
                    $event = app(\App\Services\CodeChecking\History::class)->event($actor, $course->id, $task, $solution->id);
                    if ($event['source_submission_id']) $source = CodeSubmission::where('course_id', $course->id)
                        ->where('task_id', $task->id)->where('user_id', $solution->user_id)->lockForUpdate()->find($event['source_submission_id']);
                }
            } else {
                $source = CodeSubmission::where('course_id', $course->id)->where('task_id', $task->id)->lockForUpdate()->findOrFail($attempt);
                // Archive files are shared source material. Delete a selected historical event instead.
                abort_unless($source->legacy_id === null, 409, 'Выберите конкретную сдачу ученика в истории.');
                $solution = $source->solution_id ? Solution::where('course_id', $course->id)->where('task_id', $task->id)
                    ->where('user_id', $source->user_id)->lockForUpdate()->find($source->solution_id) : null;
            }
            $user = User::lockForUpdate()->findOrFail($solution?->user_id ?? $source->user_id);
            // Native source snapshots are the submitted work; its repeated checks are not separate submissions.
            $native = $source && $source->legacy_id === null && (!$solution || (int)$source->solution_id === (int)$solution->id);
            $ids = $solution ? [$solution->id] : [];
            if ($native) $ids = $this->linked($source)->pluck('id')->all();
            if ($source) {
                $runs = CodeExecutionRun::where('submission_id', $source->id)->orderBy('id')->lockForUpdate()->get();
                abort_if($source->preparation_state === 'pending' || $runs->contains(fn ($run) => !$run->finished_at && !in_array($run->state, ['completed','error','awaiting_review','imported_pending'], true)),
                    409, 'Сейчас идёт проверка этой работы. Дождитесь её завершения и повторите удаление.');
            }
            if ($ids) {
                if (Schema::hasTable('course_activities')) DB::table('course_activities')->where('course_id', $course->id)->whereIn('solution_id', $ids)->delete();
                // Already issued coin receipts remain immutable and prevent duplicate classroom rewards.
                // Published achievements are independent awards; their FK becomes null on deletion.
                Solution::where('course_id', $course->id)->where('task_id', $task->id)->where('user_id', $user->id)->whereIn('id', $ids)->delete();
            }
            if ($source) {
                $remaining = $this->linked($source)->orderBy('submitted')->orderBy('id')->first();
                if ($remaining) $source->update(['solution_id' => $remaining->id]);
                else $this->removeSource($source);
            }
            CourseStudentPoints::recalculate($course->id, $user->id);
            LessonStudentStats::recalculateForStudents($course->id, [$user->id], false);
            // Deleting an earlier pending attempt may release a later legitimate classroom award.
            app(ClassroomBonus::class)->reconcile($course->id, $task->id, $user->id);
            $user->rescore();
            DB::afterCommit(fn () => $user->rescore());
            return (int)$user->id;
        }, 5);
    }

    private function linked(CodeSubmission $source)
    {
        return Solution::where('course_id', $source->course_id)->where('task_id', $source->task_id)->where('user_id', $source->user_id)
            ->where(function ($query) use ($source) {
                $query->where('id', $source->solution_id)->orWhere('text', 'like', '%/code-submissions/'.$source->id.'%');
                if ($source->legacy_id !== null) {
                    $legacy = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $source->legacy_id);
                    $query->orWhere('geekpaste_code_id', $source->legacy_id);
                    foreach (['?id=', '&id=', '&amp;id='] as $prefix) foreach (['', '&%', '#%'] as $suffix)
                        $query->orWhereRaw("text LIKE ? ESCAPE '!'", ['%'.$prefix.$legacy.$suffix]);
                }
            });
    }

    private function removeSource(CodeSubmission $source): void
    {
        if (Schema::hasTable('reward_notices')) DB::table('reward_notices')->where('user_id', $source->user_id)->where('kind', 'xp')
            ->whereIn('source_key', CodeExecutionRun::where('submission_id', $source->id)->select('id'))->delete();
        foreach (['code_execution_alerts','code_fingerprints','code_integrity_reports','code_attempt_credits','code_execution_runs'] as $table) {
            if (Schema::hasTable($table)) DB::table($table)->where('submission_id', $source->id)->delete();
        }
        if (Schema::hasTable('code_test_reports')) DB::table('code_test_reports')->where('course_id', $source->course_id)
            ->where('task_id', $source->task_id)->where('user_id', $source->user_id)
            ->whereIn('code_id', array_filter([$source->id, $source->legacy_id]))->delete();
        // Content-addressed blobs may also belong to drafts, other submissions or task fixtures.
        // Remove ownership/access here; shared storage is not erased by an attempt deletion.
        $source->delete();
    }
}
