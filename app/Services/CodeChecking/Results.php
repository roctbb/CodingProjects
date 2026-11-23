<?php

namespace App\Services\CodeChecking;

use App\CodeExecutionRun;
use App\CodeSubmission;
use App\CodeTestReport;
use Illuminate\Support\Facades\DB;

class Results
{
    public function __construct(private Reports $reports, private Grading $grading) {}

    public function progress(CodeExecutionRun $run, array $data): void
    {
        abort_unless(is_int($data['sequence'] ?? null) && $data['sequence'] > 0
            && (!isset($data['execution_id']) || $data['execution_id'] === $run->id)
            && in_array($data['phase'] ?? null, ['queued', 'preparing', 'compiling', 'testing', 'grading', 'publishing'], true)
            && is_int($data['completed'] ?? null) && is_int($data['total'] ?? null)
            && $data['completed'] >= 0 && $data['total'] >= $data['completed'] && $data['total'] <= 50, 422);
        // A late progress callback cannot reopen a terminal run or replace a newer event.
        CodeExecutionRun::whereKey($run->id)->whereNull('finished_at')->where('progress_sequence', '<', $data['sequence'])
            ->update(['progress_sequence' => $data['sequence'], 'state' => 'running', 'progress' => json_encode(
                array_intersect_key($data, array_flip(['sequence', 'phase', 'completed', 'total'])), JSON_THROW_ON_ERROR)]);
    }

    public function accept(CodeExecutionRun $run, array $data, string $hash): void
    {
        $type = $run->snapshot['check_type'] ?? null;
        $combined = $type === Configuration::TESTS_THEN_AI;
        abort_unless(($data['execution_id'] ?? null) === $run->id
            && in_array($data['state'] ?? null, ['done', 'partially done', 'execution error'], true)
            && is_int($data['points'] ?? null) && $data['points'] >= 0 && $data['points'] <= $run->snapshot['points']
            && is_string($data['comment'] ?? null) && strlen($data['comment']) <= 200000, 422, 'Invalid execution result.');
        if (array_key_exists('criteria', $data)) {
            $rows = $data['criteria'];
            abort_unless(in_array($type, Configuration::AI_TYPES, true) && $data['state'] !== 'execution error'
                && is_array($rows) && array_is_list($rows) && count($rows) <= 100, 422);
            foreach ($rows as $row) {
                abort_unless(is_array($row)
                    && is_string($row['title'] ?? null) && trim($row['title']) !== '' && mb_strlen($row['title']) <= 200
                    && is_string($row['comment'] ?? null) && trim($row['comment']) !== '' && mb_strlen($row['comment']) <= 4000, 422);
                if (count($row) === 3 && in_array($row['status'] ?? null, ['met','partial','unmet'], true)) continue;
                abort_unless(count($row) === 4
                    && is_int($row['points'] ?? null) && is_int($row['maximum'] ?? null)
                    && $row['maximum'] > 0 && $row['points'] >= 0 && $row['points'] <= $row['maximum'], 422);
            }
            $remaining = $data['points'] - array_sum(array_column($rows, 'points'));
            $unweighted = (int)$run->snapshot['points'] - array_sum(array_column($rows, 'maximum'));
            abort_if($rows && ($remaining < 0 || $remaining > $unweighted
                || (!array_column($rows, 'status') && ($remaining !== 0 || $unweighted !== 0))), 422, 'Invalid rubric totals.');
        }
        if ($combined) {
            abort_if(array_key_exists('report', $data), 422, 'Test report must precede grading.');
            if ($data['state'] !== 'execution error') {
                $criteria = $data['criteria'] ?? [];
                $expected = $run->snapshot['rubric_criteria'];
                abort_unless(count($criteria) === count($expected), 422, 'Incomplete rubric result.');
                foreach ($expected as $index => $criterion) {
                    abort_unless($criteria[$index]['title'] === $criterion['title']
                        && ($criteria[$index]['maximum'] ?? null) === $criterion['maximum'], 422, 'Rubric differs from the snapshot.');
                }
            }
        }
        if (isset($data['suite_diagnostics'])) {
            abort_unless(($run->snapshot['test_mode']??null)==='suite' && is_array($data['suite_diagnostics'])
                && is_string($data['suite_diagnostics']['text']??null) && strlen($data['suite_diagnostics']['text'])<=400000
                && ($data['suite_diagnostics']['suite_id']??null)===($run->snapshot['test_config']['suite_id']??null)
                && ($data['suite_diagnostics']['suite_version']??null)===($run->snapshot['test_config']['suite_version']??null),422,'Invalid suite diagnostics.');
        }
        DB::transaction(function () use ($run, $data, $hash, $combined) {
            $run = CodeExecutionRun::lockForUpdate()->findOrFail($run->id);
            if ($run->result_hash) {
                abort_unless(hash_equals($run->result_hash, $hash), 409, 'Result is immutable.');
                return;
            }
            $submission = CodeSubmission::lockForUpdate()->findOrFail($run->submission_id);
            if ($combined && $data['state'] !== 'execution error') {
                abort_unless(CodeTestReport::whereKey($run->id)->exists(), 409, 'Test report has not been saved.');
            }
            if (isset($data['report'])) {
                $report = $data['report'];
                abort_unless(($report['run_id'] ?? null) === $run->id && ($report['code_id'] ?? null) === $submission->id
                    && ($report['user_id'] ?? null) === (int) $submission->user_id
                    && ($report['task_id'] ?? null) === (int) $submission->task_id
                    && ($report['course_id'] ?? null) === (int) $submission->course_id, 422, 'Report belongs to another execution.');
                $this->reports->accept($report);
            }
            if (isset($data['criteria'])) $run->criteria = $data['criteria'];
            $run->update(['result_hash' => $hash, 'points' => $data['points'], 'comment' => $data['comment'],
                'legacy_report'=>isset($data['suite_diagnostics'])?['suite_diagnostics'=>$data['suite_diagnostics']]:$run->legacy_report,
                'state' => $data['state'] === 'execution error' ? 'error' : 'completed', 'finished_at' => now(),
                'progress' => ['phase' => $data['state'] === 'execution error' ? 'error' : 'completed', 'completed' => $data['report']['total'] ?? 0,
                    'total' => $data['report']['total'] ?? 0]]);
            if ($data['state'] === 'execution error') {
                app(\App\Services\ClassroomBonus::class)->reconcile($submission->course_id, $submission->task_id, $submission->user_id);
                app(ExecutionAlerts::class)->technicalError($run);
            }
            // Historical runs remain inspectable; only the current run can update the grade.
            if ($submission->current_run_id === $run->id && $data['state'] !== 'execution error') {
                $user = null;
                if (\Illuminate\Support\Facades\Schema::hasTable('reward_notices')) {
                    $user = \App\User::lockForUpdate()->findOrFail($submission->user_id);
                    $user->rescore();
                    $before = (int) $user->score();
                }
                $gradedSource=clone $submission;
                if(isset($run->snapshot['review_solution_id']))$gradedSource->solution_id=(int)$run->snapshot['review_solution_id'];
                $this->grading->apply($gradedSource, $data);
                if ($user) app(\App\Services\RewardNotices::class)->experience($user, $before, $run->id, $run->snapshot['name'] ?? 'За решение задачи');
                $gradedSubmission = $submission->fresh();
                $awarded = $gradedSubmission->solution_id ? \App\Solution::find($gradedSubmission->solution_id)?->mark : null;
                if ($awarded !== null && $awarded >= $run->snapshot['points'] && $data['points'] > 0 && $data['points'] === $run->snapshot['points']
                    && (!isset($data['report']) || $data['report']['passed'] === $data['report']['total'])) {
                    app(\App\Services\RewardNotices::class)->taskCompleted($submission->user_id, $submission->task_id, $run->snapshot['name'] ?? 'Задача');
                }
            }
        });
    }

}
