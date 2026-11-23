<?php

namespace App\Services\CodeChecking;

use App\CodeExecutionRun;
use App\CodeTestReport;
use Illuminate\Support\Facades\DB;

class CombinedTests
{
    public function __construct(private Reports $reports) {}

    public function accept(CodeExecutionRun $run, array $report): CodeTestReport
    {
        return DB::transaction(function () use ($run, $report) {
            $run = CodeExecutionRun::lockForUpdate()->findOrFail($run->id);
            $submission = $run->submission;
            abort_unless(($run->snapshot['check_type'] ?? null) === Configuration::TESTS_THEN_AI
                && ($report['run_id'] ?? null) === $run->id
                && ($report['code_id'] ?? null) === $submission->id
                && ($report['user_id'] ?? null) === (int) $submission->user_id
                && ($report['task_id'] ?? null) === (int) $submission->task_id
                && ($report['course_id'] ?? null) === (int) $submission->course_id, 422);
            abort_if($run->finished_at, 409);

            $configuration = $run->snapshot['test_config'];
            $tests = $report['tests'] ?? null;
            abort_unless(is_array($tests) && array_is_list($tests), 422);
            $compilationError = ($tests[0]['verdict'] ?? null) === 'compilation_error';
            abort_unless(count($tests) === ($compilationError ? 1 : count($configuration['tests'])), 422);

            $groups = [];
            foreach ($configuration['groups'] as $group) {
                $groups[$group['id']] = $group + ['total' => 0, 'executed' => 0, 'passed' => 0];
            }
            foreach ($configuration['tests'] as $index => $configured) {
                $test = $tests[$index] ?? null;
                $group = $configured['group'];
                $groups[$group]['total']++;
                if ($test !== null) {
                    abort_unless(is_array($test) && ($test['is_public'] ?? null) === $configured['is_public']
                        && (($test['verdict'] ?? null) === 'compilation_error') === $compilationError, 422);
                }
                if (!$compilationError) {
                    abort_unless(is_array($test), 422);
                    $groups[$group]['executed']++;
                    $groups[$group]['passed'] += (int) (($test['verdict'] ?? null) === 'passed');
                    $report['tests'][$index]['group'] = $group;
                }
            }

            return $this->reports->accept($report, array_values($groups));
        });
    }

    public function evidence(CodeTestReport $record): array
    {
        $report = $record->report;
        $tests = [];
        $examples = 0;
        $compilationError = $report['tests'][0]['verdict'] === 'compilation_error';
        if (!$compilationError) {
            foreach ($report['tests'] as $test) {
                $row = array_intersect_key($test, array_flip(['number', 'verdict', 'group']));
                if ($test['is_public'] && $test['verdict'] !== 'passed' && $examples++ < 10) {
                    foreach (['input', 'expected', 'actual', 'error'] as $field) {
                        $excerpt = $this->inlineExcerpt($test[$field]);
                        if ($excerpt !== null) $row[$field] = $excerpt;
                    }
                }
                $tests[] = $row;
            }
        }
        return ['groups' => $report['groups'], 'tests' => $tests, 'compilation_failed' => $compilationError]
            + ($compilationError ? ['compilation_diagnostic' => $this->inlineExcerpt($report['tests'][0]['error'])] : []);
    }

    private function inlineExcerpt(?array $value): ?array
    {
        if (($value['type'] ?? null) !== 'inline') return null;
        return ['text' => mb_substr($value['text'], 0, 800),
            'truncated' => $value['truncated'] || mb_strlen($value['text']) > 800];
    }
}
