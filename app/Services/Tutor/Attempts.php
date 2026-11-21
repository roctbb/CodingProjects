<?php

namespace App\Services\Tutor;

use App\CodeExecutionRun;
use App\CodeSubmission;
use App\CodeTestReport;
use App\Course;
use App\Services\CodeChecking\Artifacts;
use App\Services\CodeChecking\HistoricalReport;
use App\Services\CodeChecking\History;
use App\Task;
use App\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class Attempts
{
    private const PREVIOUS = 3;

    public function __construct(private History $history, private Artifacts $artifacts) {}

    public function forTask(Course $course, Task $task, User $user, ?string $reviewSubmission, ?array &$latestTarget = null): array
    {
        // The history folds graded native attempts and their Solution row together and identifies archive-only records.
        $attempts = [];
        $page = 1;
        do {
            $history = $this->history->page($user, $course->id, $task, $user->id, null, $page++);
            foreach ($history['items'] as $row) {
                if ($row['counts_as_attempt']) $attempts[] = $row;
                if (count($attempts) > self::PREVIOUS) break;
            }
        } while ($history['has_more'] && count($attempts) < min($history['total_attempts'], self::PREVIOUS + 1));

        $latest = null;
        $previous = [];
        foreach ($attempts as $i => $row) {
            $withSource = $i === 0;
            if ($row['kind'] === 'submission') {
                $columns = ['id','current_run_id','mode','language','preparation_state','legacy_id','source_metadata'];
                if ($withSource) array_push($columns, 'files', 'answer_text');
                $record = CodeSubmission::where(['course_id'=>$course->id,'task_id'=>$task->id,'user_id'=>$user->id])
                    ->findOrFail($row['submission_id'], $columns);
                $result = $this->nativeResult($record, $course, $task, $user);
                if ($withSource) {
                    $latest = $this->source($record) + ['result'=>$result];
                    $latestTarget = ['submission'=>$record->id, 'run'=>$record->current_run_id];
                }
            } else {
                $event = $this->history->event($user, $course->id, $task, $row['solution_id']);
                $result = ['method'=>$event['check_type'], 'state'=>$row['state'], 'points'=>$row['evaluated_points'] ?? null];
                if ($event['check_type'] === 'tests') {
                    $comment = DB::table('solutions')->where('id', $row['solution_id'])->value('comment');
                    $result += $this->tests(app(HistoricalReport::class)->parse($comment));
                } elseif ($row['state'] === 'completed') {
                    $result['comment'] = $event['comment'];
                }
                if ($withSource) {
                    $exact = $event['source_available'] && !$event['original_reference'] && is_string($event['text']);
                    $latest = ['language'=>null, 'mode'=>'legacy', 'result'=>$result, 'source_status'=>$exact ? 'ready' : 'unavailable',
                        'text'=>$exact ? $event['text'] : '', 'files'=>[]];
                }
            }
            if ($withSource) {
                if ($reviewSubmission !== null) $latest['is_reviewed_submission'] = $row['submission_id'] === $reviewSubmission;
            } else {
                unset($result['assignment']);
                $previous[] = $result;
            }
        }
        return ['latest_submission'=>$latest, 'previous_results'=>$previous];
    }

    private function source(CodeSubmission $record): array
    {
        $files = $this->files($record);
        $partial = collect($files)->contains(fn ($file) => $file['kind'] !== 'text');
        $unknown = $record->legacy_id !== null || isset($record->source_metadata['legacy_id']);
        return ['language'=>$record->language, 'mode'=>$record->mode, 'files'=>$files, 'text'=>(string) $record->answer_text,
            'source_status'=>$unknown ? 'unverified' : ($partial ? 'partial' : $record->preparation_state)];
    }

    public function selected(Course $course, Task $task, User $user, string $submission, string $run, bool $includeSource): array
    {
        $record = CodeSubmission::where(['course_id'=>$course->id, 'task_id'=>$task->id, 'user_id'=>$user->id])->findOrFail($submission);
        $result = $this->nativeResult($record, $course, $task, $user, $run);
        abort_unless($result['state'] === 'completed' && in_array($result['method'], ['tests','gpt','manual'], true), 404);
        return $includeSource ? ['result'=>$result, 'source'=>$this->source($record)] : ['result'=>$result];
    }

    public function feedback(array $result): array
    {
        $lines = collect([$result['comment'] ?? null]);
        foreach (collect($result['criteria'] ?? [])->sortBy(fn ($row) => ($row['points'] ?? null) === ($row['maximum'] ?? null)) as $row) {
            $score = isset($row['status']) ? ['met'=>'Зачтён', 'partial'=>'Частично', 'unmet'=>'Не зачтён'][$row['status']]
                : ($row['points'] ?? '?').'/'.($row['maximum'] ?? '?');
            $lines->push(trim(implode(' ', [$row['title'] ?? '', $score, $row['comment'] ?? ''])));
        }
        foreach ($result['tests'] ?? [] as $test) {
            $lines->push('Тест '.($test['number'] ?? '?').' — '.($test['verdict'] ?? '?'));
            foreach (['expected'=>'Ожидалось', 'actual'=>'Получено'] as $key=>$label) {
                if (isset($test[$key])) $lines->push($label.' '.$test[$key]);
            }
        }
        return $lines->filter(fn ($value) => is_string($value) && trim($value) !== '')->values()->all();
    }

    private function nativeResult(CodeSubmission $record, Course $course, Task $task, User $user, ?string $runId = null): array
    {
        $run = CodeExecutionRun::where('submission_id', $record->id)->find($runId ?? $record->current_run_id,
            ['id','state','points','comment','criteria','legacy_report','snapshot->points as maximum','snapshot->check_type as method','snapshot->public_text as assignment']);
        if (!$run) return ['state'=>$record->preparation_state ?? 'pending', 'method'=>null];
        $result = ['state'=>$run->state, 'method'=>$run->method, 'points'=>$run->points, 'maximum'=>$run->maximum];
        if ($run->assignment && $run->state === 'completed') $result['assignment'] = $run->assignment;
        if ($run->method === 'tests') {
            $report = CodeTestReport::where(['user_id'=>$user->id,'course_id'=>$course->id,'task_id'=>$task->id])->find($run->id);
            $unlocked = $report ? DB::table('code_test_purchases')->where(['user_id'=>$user->id, 'task_id'=>$task->id])
                ->pluck('test_id')->all() : [];
            return $result + $this->tests($report?->report ?? $run->legacy_report, $unlocked);
        }
        if ($run->state === 'completed' && in_array($run->method, ['gpt','manual'], true)) {
            $result['comment'] = (string) $run->comment;
            $result['criteria'] = array_map(fn ($criterion) => Arr::only($criterion, ['title','points','maximum','status','comment']), $run->criteria ?? []);
        }
        return $result;
    }

    private function tests(?array $report, array $unlocked = []): array
    {
        if ($report === null) return [];
        $result = Arr::only($report, ['passed','total']);
        $result['tests'] = [];
        $examples = 0;
        foreach ($report['tests'] ?? [] as $test) {
            $row = Arr::only($test, ['number','verdict']);
            $visible = ($test['is_public'] ?? false) || in_array($test['test_id'] ?? null, $unlocked, true);
            if ($visible && ($test['verdict'] ?? '') !== 'passed' && $examples++ < 3) {
                foreach (['input','expected','actual','error'] as $field) {
                    $value = $test[$field] ?? null;
                    if (($value['type'] ?? '') === 'inline') $row[$field] = mb_strimwidth($value['text'], 0, 600, '…');
                }
            }
            $result['tests'][] = $row;
        }
        return $result;
    }

    private function files(CodeSubmission $record): array
    {
        $files = [];
        $bytes = strlen((string) $record->answer_text);
        foreach ($record->files ?? [] as $file) {
            if (($file['origin'] ?? 'student') === 'task') continue;
            if (!empty($file['is_answer']) && $record->answer_text !== null
                && ($file['artifact']['sha256'] ?? null) === hash('sha256', $record->answer_text)) continue;
            $entry = ['name'=>$file['name']];
            try {
                $stream = $this->artifacts->readStream($file['artifact']['blob_id']);
            } catch (NotFoundHttpException) {
                $files[] = $entry + ['kind'=>'unavailable'];
                continue;
            }
            try {
                $prefix = fread($stream, 8192);
                if (preg_match('/[\x00-\x08\x0e-\x1a]/', $prefix)) {
                    $files[] = $entry + ['kind'=>'binary'];
                    continue;
                }
                abort_if($bytes + $file['artifact']['bytes'] > Context::MAX_BYTES, 413, Context::TOO_LARGE);
                $text = $prefix.stream_get_contents($stream, Context::MAX_BYTES + 1);
            } finally {
                fclose($stream);
            }
            if (strlen($text) !== (int) $file['artifact']['bytes'] || !hash_equals($file['artifact']['blob_id'], hash('sha256', $text))) {
                $files[] = $entry + ['kind'=>'unavailable'];
                continue;
            }
            if (!mb_check_encoding($text, 'UTF-8') || str_contains($text, "\0")) {
                $files[] = $entry + ['kind'=>'binary'];
                continue;
            }
            $bytes += strlen($text);
            abort_if($bytes > Context::MAX_BYTES, 413, Context::TOO_LARGE);
            $files[] = $entry + ['kind'=>'text','text'=>$text];
        }
        return $files;
    }
}
