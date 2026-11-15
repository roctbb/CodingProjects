<?php

namespace App\Services\CodeChecking;

use App\CodeExecutionRun;
use App\CodeTaskChecker;
use App\CoinTransaction;
use App\Services\CoinWallet;
use App\Services\SystemStoreSettings;
use App\Task;
use App\User;
use Illuminate\Support\Facades\DB;

/** Reveal the imported GP definition, keeping its provenance separate from execution output. */
final class ArchivedTests
{
    private const NOTICE = 'Вводы и эталоны доступны из снимка GeekPaste при переносе. Версия набора на дату отправки не фиксировалась; фактический вывод скрытых тестов не сохранялся.';

    public function __construct(private Access $access, private Artifacts $artifacts) {}

    public function view(CodeExecutionRun $run, User $viewer, bool $fields = true): ?array
    {
        if (!isset($run->legacy_report['tests'])) return null;
        $submission = $run->submission;
        $this->access->authorOrTeacher($viewer, $submission);
        $teacher = $this->access->teacher($viewer, (int) $submission->course_id);
        $report = app(HistoricalReport::class)->view($run->legacy_report, $teacher);
        if (!$report || !isset($report['tests'])) return null;
        $fixtures = $this->fixtures($run, $report);
        $purchased = $fixtures ? DB::table('code_test_purchases')->where('user_id', $viewer->id)
            ->where('task_id', $submission->task_id)->pluck('test_id')->all() : [];
        $canBuy = $fixtures && $this->canBuy($run, $viewer);
        foreach ($report['tests'] as &$test) {
            $fixture = $fixtures[$test['number']] ?? null;
            if ($fixture) {
                $test['test_id'] = $this->identity($fixture);
                $test['data_source'] = 'geekpaste_archive';
                $test['purchase_url'] = $this->base($run, $test['number']).'/purchase';
                $test['unlocked'] = $teacher || $test['is_public'] || in_array($test['test_id'], $purchased, true);
                $test['can_purchase'] = !$test['unlocked'] && $canBuy && in_array($test['verdict'], Reports::PURCHASABLE, true);
            }
            $test['has_details'] = (bool) ($fixture || array_intersect(['input','expected','actual','error'], array_keys($test)));
            if ($fields && $test['unlocked'] && $fixture) {
                foreach (['input'=>'input', 'expected'=>'output'] as $key=>$source) {
                    if (!isset($test[$key])) $test[$key] = $this->field($run, $test['number'], $key, $fixture[$source]);
                }
                $test['notice'] = 'Ввод и эталон — из архива GeekPaste.'.(!isset($test['actual']) ? ' Вывод программы не сохранился.' : '');
            }
            if (!$fields || !$test['unlocked']) foreach (['input','expected','actual','error','comparison','limits'] as $key) unset($test[$key]);
        }
        unset($test);
        if ($fixtures) $report['notice'] = self::NOTICE;
        $report['price'] = app(SystemStoreSettings::class)->price('code_test_reveal');
        return $report;
    }

    public function test(CodeExecutionRun $run, int $number, User $viewer): array
    {
        $report = $this->view($run, $viewer, false);
        $test = collect($report['tests'] ?? [])->firstWhere('number', $number);
        abort_unless($test, 404);
        abort_unless($test['unlocked'], 403);
        // Hydrate this test only; expanding a row never downloads an entire suite.
        $saved = collect($run->legacy_report['tests'])->firstWhere('number', $number);
        foreach (['input','expected','actual','error'] as $key) if (isset($saved[$key])) $test[$key] = $saved[$key];
        $fixture = $this->fixtures($run, $run->legacy_report)[$number] ?? null;
        if ($fixture) {
            foreach (['input'=>'input', 'expected'=>'output'] as $key=>$source) if (!isset($test[$key])) $test[$key] = $this->field($run, $number, $key, $fixture[$source]);
            $test['notice'] = 'Ввод и эталон — из архива GeekPaste.'.(!isset($test['actual']) ? ' Вывод программы не сохранился.' : '');
        }
        if (!$test['has_details']) $test += ['data_unavailable'=>true, 'notice'=>'Подробные данные этой проверки не сохранились.'];
        return $test;
    }

    public function buy(CodeExecutionRun $run, int $number, User $viewer, int $expectedPrice): array
    {
        $this->access->authorOrTeacher($viewer, $run->submission);
        abort_unless($viewer->role === 'student' && (int) $run->submission->user_id === (int) $viewer->id, 403);
        return app(CoinWallet::class)->transaction($viewer->id, function ($user) use ($run, $number, $expectedPrice) {
            $report = $this->view($run, $user, false);
            $test = collect($report['tests'] ?? [])->firstWhere('number', $number);
            abort_unless($test && ($test['data_source'] ?? null) === 'geekpaste_archive', 404);
            $already = DB::table('code_test_purchases')->where('user_id', $user->id)->where('task_id', $run->submission->task_id)->where('test_id', $test['test_id'])->exists();
            abort_unless($already || $test['can_purchase'], 409, 'Этот тест недоступен для покупки.');
            $price = $report['price'];
            if (!$already) {
                abort_if($expectedPrice !== $price, 409, 'Цена изменилась. Обновите страницу.');
                abort_if($user->balance() < $price, 409, 'Недостаточно GC.');
                $fixture = $this->fixtures($run, $run->legacy_report)[$number];
                foreach (['input','output'] as $key) if ($fixture[$key]['type'] === 'blob') abort_unless($this->artifacts->exists($fixture[$key]['blob_id'], $fixture[$key]['bytes']), 404, 'Архивный файл недоступен.');
                $transaction = CoinTransaction::register($user->id, -$price, 'Archived code test reveal Task #'.$run->submission->task_id.' Test #'.$test['test_id']);
                DB::table('code_test_purchases')->insert(['user_id'=>$user->id, 'task_id'=>$run->submission->task_id, 'test_id'=>$test['test_id'],
                    'amount'=>$price, 'coin_transaction_id'=>$transaction->id, 'created_at'=>now(), 'updated_at'=>now()]);
            }
            return ['purchased'=>true, 'already_purchased'=>$already, 'charged'=>$already ? 0 : $price, 'balance'=>(int) $user->balance()];
        });
    }

    public function download(CodeExecutionRun $run, int $number, string $field, User $viewer)
    {
        $this->test($run, $number, $viewer);
        abort_unless(in_array($field, ['input','expected'], true), 404);
        $fixture = $this->fixtures($run, $run->legacy_report)[$number] ?? null;
        abort_unless($fixture, 404);
        $value = $fixture[$field === 'expected' ? 'output' : 'input'];
        if ($value['type'] === 'inline') return response($value['text'])->header('Content-Type','text/plain; charset=utf-8')->header('Content-Disposition','attachment; filename="test-'.$number.'-'.$field.'.txt"')->header('Cache-Control','private, no-store')->header('X-Content-Type-Options','nosniff');
        return $this->artifacts->download($value, 'test-'.$number.'-'.$field.'.txt');
    }

    private function fixtures(CodeExecutionRun $run, array $report): array
    {
        $snapshot = $run->snapshot;
        if (empty($snapshot['legacy']) || empty($snapshot['legacy_definition_hash']) || ($snapshot['test_mode'] ?? null) !== 'io') return [];
        $fixtures = $snapshot['test_config']['tests'] ?? [];
        if (count($fixtures) !== ($report['total'] ?? 0) || count($fixtures) !== count($report['tests'] ?? [])) return [];
        $result = [];
        foreach ($report['tests'] as $row) {
            $fixture = $fixtures[$row['number'] - 1] ?? null;
            if (!$fixture || (bool) $fixture['is_public'] !== $row['is_public']) return [];
            foreach (['input','output'] as $field) {
                $value = $fixture[$field] ?? [];
                if (!in_array($value['type'] ?? null, ['inline','blob'], true) || ($value['truncated'] ?? false)) return [];
                if ($value['type'] === 'blob' && !preg_match('/^[a-f0-9]{64}$/D', $value['blob_id'] ?? '')) return [];
                if ($value['type'] === 'inline' && !is_string($value['text'] ?? null)) return [];
            }
            $result[$row['number']] = $fixture;
        }
        return $result;
    }

    private function identity(array $fixture): string
    {
        return hash('sha256', 'gp-archive-test-v1:'.implode(':', array_map(fn ($field) => $field['sha256'] ?? $field['blob_id'] ?? hash('sha256', $field['text']), [$fixture['input'], $fixture['output']])));
    }

    private function canBuy(CodeExecutionRun $run, User $viewer): bool
    {
        $submission = $run->submission;
        return $viewer->role === 'student' && (int) $submission->user_id === (int) $viewer->id
            && (CodeTaskChecker::find($submission->task_id)?->allows_test_reveals ?? false)
            && !Task::findOrFail($submission->task_id)->isBlocked($viewer->id, $submission->course_id);
    }

    private function base(CodeExecutionRun $run, int $number): string
    {
        return url('/insider/code-submissions/'.$run->submission_id.'/runs/'.$run->id.'/tests/'.$number);
    }

    private function field(CodeExecutionRun $run, int $number, string $name, array $value): array
    {
        $url = $this->base($run, $number).'/'.$name;
        return $this->artifacts->testField($value, $url) + ['download_url'=>$url];
    }
}
