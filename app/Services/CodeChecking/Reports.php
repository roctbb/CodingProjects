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

    public function view(CodeTestReport $record, User $viewer,bool $includeFields=true): array
    {
        $this->access->authorOrTeacher($viewer, $record);
        $teacher = $this->access->teacher($viewer, (int) $record->course_id);
        $purchased = $this->purchased($viewer, $record);
        $canPurchase=$teacher?false:$this->purchaseAllowed($record,$viewer);
        $report = $record->report;
        // The compiler never receives test input. Its diagnostic is visible
        // immediately, while the first test's fixtures remain private.
        $compilation = collect($report['tests'])->firstWhere('verdict', 'compilation_error');
        if ($compilation && !empty($compilation['error'])) {
            $report['compilation_error'] = $this->artifacts->testField($compilation['error'],
                route('code-reports.download', ['run'=>$record->id, 'test'=>$compilation['test_id'], 'field'=>'error']));
        }
        $report['tests'] = array_map(function ($test) use ($teacher, $purchased, $record, $viewer,$includeFields,$canPurchase) {
            $unlocked = $teacher || $test['is_public'] || in_array($test['test_id'], $purchased, true);
            $row = array_intersect_key($test, array_flip(['number', 'test_id', 'is_public', 'verdict']));
            $row['unlocked'] = $unlocked;
            $row['can_purchase'] = !$unlocked && $canPurchase && in_array($test['verdict'],self::PURCHASABLE,true);
            $row['has_details']=$unlocked;
            if ($unlocked && $includeFields) {
                foreach (['input', 'expected', 'actual', 'error'] as $key) {
                    $field = $test[$key];
                    if ($field) $field = $this->artifacts->testField($field, route('code-reports.download', ['run'=>$record->id, 'test'=>$test['test_id'], 'field'=>$key]));
                    $row[$key] = $field;
                }
                $row['comparison'] = $test['comparison'];
                $row['limits'] = $test['limits'];
            }
            return $row;
        }, $report['tests']);
        $report['price'] = app(SystemStoreSettings::class)->price('code_test_reveal');
        if(!$includeFields)$report['test_data']='metadata';
        return $report;
    }

    public function test(CodeTestReport $record,int $number,User $viewer):array
    {
        $summary=$this->view($record,$viewer,false);
        $row=collect($summary['tests'])->firstWhere('number',$number);abort_unless($row,404);
        abort_unless($row['unlocked'],403);
        $stored=collect($record->report['tests'])->firstWhere('number',$number);
        foreach(['input','expected','actual','error'] as $key){
            $field=$stored[$key]??null;
            if($field)$field=$this->artifacts->testField($field,route('code-reports.download',['run'=>$record->id,'test'=>$stored['test_id'],'field'=>$key]));
            $row[$key]=$field;
        }
        $row['comparison']=$stored['comparison'];$row['limits']=$stored['limits'];
        return $row;
    }

    public function metadata(array $report):array
    {
        // Free-form historical notes may contain test bodies too.
        unset($report['notes']);
        $tests=$report['tests']??[];
        foreach($tests as &$test){
            $test['has_details']=$test['has_details']??(bool)array_intersect(['input','expected','actual','error'],array_keys($test));
            foreach(['input','expected','actual','error','comparison','limits'] as $field)unset($test[$field]);
        }unset($test);
        $report['tests']=$tests;
        $report['test_data']='metadata';return $report;
    }

    private function purchaseAllowed(CodeTestReport $record,User $user):bool
    {
        return $user->role==='student' && (int)$record->user_id===(int)$user->id
            && (CodeTaskChecker::find($record->task_id)?->allows_test_reveals??true)
            && !Task::findOrFail($record->task_id)->isBlocked($user->id,$record->course_id);
    }

    private function canPurchase(CodeTestReport $record, array $test, User $user): bool
    {
        return !$test['is_public']
            && in_array($test['verdict'], self::PURCHASABLE, true)
            && $this->purchaseAllowed($record,$user);
    }

    /** Use the same lock and permanent test identity as a coin purchase. */
    public function tutorExamples(CodeTestReport $record, User $user): array
    {
        if (!$this->purchaseAllowed($record, $user)) return [];
        $report = $record->report;
        $limit = min(3, intdiv(count($report['tests']), 3));
        if (!$limit || $report['passed'] === $report['total']) return [];

        return app(CoinWallet::class)->transaction($user->id, function () use ($record, $user, $report, $limit) {
            $scope = ['user_id' => $user->id, 'task_id' => $record->task_id];
            $unlocks = DB::table('code_test_purchases')->where($scope)->pluck('from_tutor', 'test_id');
            $remaining = $limit - $unlocks->filter()->count();
            $examples = [];
            foreach ($report['tests'] as $test) {
                if ($test['is_public'] || !in_array($test['verdict'], self::PURCHASABLE, true)) continue;
                $id = $test['test_id'];
                if (!$unlocks->get($id)) {
                    if ($unlocks->has($id) || $remaining <= 0) continue;
                    DB::table('code_test_purchases')->insert($scope + ['test_id' => $id, 'amount' => 0,
                        'from_tutor' => true, 'created_at' => now(), 'updated_at' => now()]);
                    $unlocks->put($id, true);
                    $remaining--;
                }
                $example = ['number' => $test['number']];
                foreach (['input', 'expected', 'actual'] as $key) {
                    if (isset($test[$key])) {
                        $field = $this->artifacts->testField($test[$key], '');
                        if ($field['type'] === 'inline') $example[$key] = mb_strimwidth($field['text'], 0, 400, '…');
                    }
                }
                $examples[] = $example;
                if (count($examples) === $limit) break;
            }
            return $examples;
        });
    }

    public function buy(CodeTestReport $record, string $testId, User $user, ?int $expectedPrice = null,bool $includeFields=true): array
    {
        $this->access->authorOrTeacher($user, $record);
        return app(CoinWallet::class)->transaction($user->id, function ($lockedUser) use ($record, $testId, $expectedPrice,$includeFields) {
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
                'balance' => (int) $lockedUser->balance(), 'report' => $this->view($record, $lockedUser,$includeFields)];
        });
    }

    public function download(CodeTestReport $record, string $testId, string $field, User $user)
    {
        $view = $this->view($record, $user);
        $test = collect($view['tests'])->firstWhere('test_id', $testId);
        abort_unless($test && ($test['unlocked'] || ($field === 'error' && $test['verdict'] === 'compilation_error')), 403);
        abort_unless(in_array($field, ['input', 'expected', 'actual', 'error'], true), 404);
        $value = collect($record->report['tests'])->firstWhere('test_id', $testId)[$field];
        abort_unless($value && $value['type'] === 'blob', 404);
        return $this->artifacts->download($value, 'test-'.$test['number'].'-'.$field.'.txt');
    }
}
