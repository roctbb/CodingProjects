<?php

namespace App\Services\CodeChecking;

use App\CodeTaskChecker;
use App\CodeTestReport;
use App\CoinTransaction;
use App\Task;
use App\User;
use App\Services\CoinWallet;
use App\Services\SystemStoreSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Reports
{
    public const VERDICTS = ['passed', 'wrong_answer', 'runtime_error', 'time_limit_exceeded', 'output_limit_exceeded', 'compilation_error', 'error'];
    public const PURCHASABLE = ['wrong_answer', 'runtime_error', 'time_limit_exceeded', 'output_limit_exceeded'];

    public function __construct(private Artifacts $artifacts, private Access $access) {}

    public function accept(array $report): CodeTestReport
    {
        abort_unless(($report['schema_version'] ?? null) === 1 && Str::isUuid($report['run_id'] ?? '')
            && is_string($report['code_id'] ?? null) && preg_match('/^[a-zA-Z0-9-]{1,64}$/D', $report['code_id'])
            && ($report['test_mode'] ?? null) === 'io', 422, 'Invalid test report.');
        foreach (['user_id', 'course_id', 'task_id', 'started_at', 'finished_at', 'total'] as $key) {
            abort_unless(is_int($report[$key] ?? null) && $report[$key] > 0, 422, 'Invalid report identity.');
        }
        abort_unless(is_int($report['passed'] ?? null) && $report['passed'] >= 0 && $report['passed'] <= $report['total']
            && $report['finished_at'] >= $report['started_at'] && is_array($report['tests'] ?? null)
            && array_is_list($report['tests']) && count($report['tests']) === $report['total'] && $report['total'] <= 50
            && preg_match('/^[a-f0-9]{64}$/D', $report['test_set_version'] ?? ''), 422, 'Invalid report counts.');
        // Resolve task/course membership before storing any report supplied by the runner.
        $author = User::findOrFail($report['user_id']);
        $this->access->task($author, $report['course_id'], $report['task_id']);
        $tests = [];
        foreach ($report['tests'] as $index => $test) {
            abort_unless(is_array($test) && ($test['number'] ?? null) === $index + 1
                && is_bool($test['is_public'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $test['test_id'] ?? '')
                && in_array($test['verdict'] ?? null, self::VERDICTS, true), 422, 'Invalid test result.');
            $row = array_intersect_key($test, array_flip(['number', 'test_id', 'is_public', 'verdict']));
            foreach (['input', 'expected', 'actual', 'error'] as $key) {
                $row[$key] = $this->artifacts->descriptor($test[$key] ?? null, in_array($key, ['input', 'expected'], true));
            }
            foreach (['input', 'expected'] as $key) abort_if($row[$key]['truncated'], 422, 'Incomplete fixture.');
            $row['comparison'] = $test['comparison'] ?? [];
            $row['limits'] = $test['limits'] ?? [];
            abort_unless(is_array($row['comparison']) && is_array($row['limits']), 422);
            $tests[] = $row;
        }
        abort_unless(count(array_filter($tests, fn ($test) => $test['verdict'] === 'passed')) === $report['passed'], 422);
        $report = array_intersect_key($report, array_flip(['schema_version', 'run_id', 'code_id', 'user_id', 'course_id', 'task_id',
            'started_at', 'finished_at', 'test_mode', 'test_set_version', 'total', 'passed']));
        $report['tests'] = $tests;
        $hash = hash('sha256', json_encode($report, JSON_THROW_ON_ERROR));
        $existing = CodeTestReport::find($report['run_id']);
        if ($existing) {
            abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'Report is immutable.');
            return $existing;
        }
        return CodeTestReport::create(['id' => $report['run_id'], 'code_id' => $report['code_id'],
            'user_id' => $report['user_id'], 'course_id' => $report['course_id'], 'task_id' => $report['task_id'],
            'started_at' => $report['started_at'], 'payload_hash' => $hash, 'report' => $report, 'created_at' => now()]);
    }

    private function purchased(User $user, CodeTestReport $record): array
    {
        return DB::table('code_test_purchases')->where('user_id', $user->id)->where('task_id', $record->task_id)->pluck('test_id')->all();
    }

    public function view(CodeTestReport $record, User $viewer): array
    {
        $this->access->authorOrTeacher($viewer, $record);
        $teacher = $this->access->teacher($viewer, (int) $record->course_id);
        $purchased = $this->purchased($viewer, $record);
        $report = $record->report;
        $report['tests'] = array_map(function ($test) use ($teacher, $purchased, $record, $viewer) {
            $unlocked = $teacher || $test['is_public'] || in_array($test['test_id'], $purchased, true);
            $row = array_intersect_key($test, array_flip(['number', 'test_id', 'is_public', 'verdict']));
            $row['unlocked'] = $unlocked;
            $row['can_purchase'] = !$unlocked && $this->canPurchase($record, $test, $viewer);
            if ($unlocked) {
                foreach (['input', 'expected', 'actual', 'error'] as $key) {
                    $field = $test[$key];
                    if ($field && $field['type'] === 'blob') {
                        unset($field['blob_id'], $field['sha256']);
                        $field['download_url'] = route('code-reports.download', ['run' => $record->id, 'test' => $test['test_id'], 'field' => $key]);
                    }
                    $row[$key] = $field;
                }
                $row['comparison'] = $test['comparison'];
                $row['limits'] = $test['limits'];
            }
            return $row;
        }, $report['tests']);
        $report['price'] = app(SystemStoreSettings::class)->price('code_test_reveal');
        return $report;
    }

    private function canPurchase(CodeTestReport $record, array $test, User $user): bool
    {
        return $user->role === 'student' && (int) $record->user_id === (int) $user->id && !$test['is_public']
            && in_array($test['verdict'], self::PURCHASABLE, true)
            && (CodeTaskChecker::find($record->task_id)?->allows_test_reveals ?? true)
            && !Task::findOrFail($record->task_id)->isBlocked($user->id, $record->course_id);
    }

    public function buy(CodeTestReport $record, string $testId, User $user, ?int $expectedPrice = null): array
    {
        $this->access->authorOrTeacher($user, $record);
        return app(CoinWallet::class)->transaction($user->id, function ($lockedUser) use ($record, $testId, $expectedPrice) {
            $test = collect($record->report['tests'])->firstWhere('test_id', $testId);
            abort_unless($test, 404);
            $already = in_array($testId, $this->purchased($lockedUser, $record), true);
            abort_unless($already || $this->canPurchase($record, $test, $lockedUser), 409, 'Test cannot be purchased.');
            $price = app(SystemStoreSettings::class)->price('code_test_reveal');
            if (!$already) {
                abort_if($expectedPrice !== null && $expectedPrice !== $price, 409, 'Price changed.');
                abort_if($lockedUser->balance() < $price, 409, 'Insufficient coins.');
                $transaction = CoinTransaction::register($lockedUser->id, -$price,
                    'Code test reveal Task #'.$record->task_id.' Test #'.$testId);
                DB::table('code_test_purchases')->insert(['user_id' => $lockedUser->id, 'task_id' => $record->task_id,
                    'test_id' => $testId, 'amount' => $price, 'coin_transaction_id' => $transaction->id,
                    'created_at' => now(), 'updated_at' => now()]);
            }
            return ['purchased' => true, 'already_purchased' => $already, 'charged' => $already ? 0 : $price,
                'balance' => (int) $lockedUser->balance(), 'report' => $this->view($record, $lockedUser)];
        });
    }

    public function download(CodeTestReport $record, string $testId, string $field, User $user)
    {
        $view = $this->view($record, $user);
        $test = collect($view['tests'])->firstWhere('test_id', $testId);
        abort_unless($test && $test['unlocked'], 403);
        abort_unless(in_array($field, ['input', 'expected', 'actual', 'error'], true), 404);
        $value = collect($record->report['tests'])->firstWhere('test_id', $testId)[$field];
        abort_unless($value && $value['type'] === 'blob', 404);
        return $this->artifacts->download($value, 'test-'.$test['number'].'-'.$field.'.txt');
    }
}
