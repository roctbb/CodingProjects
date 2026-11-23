<?php

namespace App\Services\Tutor;

use App\CodeExecutionRun;
use App\CodeSubmission;
use App\CodeTestReport;
use App\Course;
use App\Services\CodeChecking\Artifacts;
use App\Services\CodeChecking\Configuration;
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

    public function forTask(Course $course, Task $task, User $user, ?string $reviewSubmission, ?array &$latestTarget = null, ?int &$latestLegacy = null): array
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
                    $latestLegacy = (int)$row['solution_id'];
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

    /** Only grade records visible in this student's task history, including older runs. */
    public function matchingGrade(Course $course, Task $task, User $user, int $points, int $maximum): array
    {
        $scope = ['user_id'=>$user->id, 'course_id'=>$course->id, 'task_id'=>$task->id];
        $submissions = DB::table('code_submissions')->where($scope)->select('id','created_at');
        $matches = [];
        $runs = DB::table('code_execution_runs as r')->joinSub($submissions, 's', 's.id', '=', 'r.submission_id')
            ->where('r.state','completed')->where('r.points',$points)
            ->whereIn('r.snapshot->check_type',['tests','manual', ...Configuration::AI_TYPES])
            ->orderByDesc('r.finished_at')->get(['r.id','r.submission_id','r.snapshot->points as maximum','r.finished_at','s.created_at']);
        foreach ($runs as $run) {
            if ((int)$run->maximum !== $maximum || $run->maximum === null) continue;
            $matches[] = ['submission_id'=>$run->submission_id, 'run_id'=>$run->id, 'at'=>$run->finished_at ?? $run->created_at];
        }
        if ((int)$task->max_mark === $maximum) {
            $legacy = DB::table('solutions')->where($scope)->whereNotNull('submitted')->whereNotNull('mark')
                ->whereRaw('coalesce(evaluated_mark,raw_mark,mark) = ?', [$points])
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('code_submissions as s')
                    ->whereColumn('s.solution_id','solutions.id')->whereColumn('s.course_id','solutions.course_id')
                    ->whereColumn('s.user_id','solutions.user_id')->whereColumn('s.task_id','solutions.task_id')->whereNull('s.legacy_id'))
                ->get(['id','checked','submitted']);
            foreach ($legacy as $row) $matches[] = ['legacy_solution'=>(int)$row->id, 'at'=>$row->checked ?? $row->submitted];
        }
        return collect($matches)->sortByDesc('at')->values()->all();
    }

    public function recheckResult(Course $course, Task $task, User $user, array $previous): ?array
    {
        $target = $previous['attempt'] ?? [];
        $runs = CodeExecutionRun::whereHas('submission', fn ($q) => $q->where([
            'course_id'=>$course->id,'task_id'=>$task->id,'user_id'=>$user->id,
        ]))->where('snapshot->ai_review->origin','tutor');
        if (isset($target['submission_id'], $target['run_id'])) {
            $runs->where('submission_id',$target['submission_id'])->where('snapshot->ai_review->source_run_id',$target['run_id']);
        } else {
            // Old replies stored the verified finding but not the source run ID.
            $runs->where('snapshot->ai_review->finding->focus',$previous['recheck']['focus'])
                ->where('snapshot->ai_review->finding->claim',$previous['recheck']['claim']);
        }
        $candidates = $runs->limit(2)->get(['id','submission_id','state','snapshot->ai_review->source_run_id as original']);
        if ($candidates->count() !== 1) return null;
        $run = $candidates->first();
        $submission = CodeSubmission::findOrFail($run->submission_id);
        $before = $this->nativeResult($submission, $course, $task, $user, $run->original);
        $after = $this->nativeResult($submission, $course, $task, $user, $run->id);
        return $this->comparison($previous['recheck'], $before, $after)
            + ['target'=>['submission_id'=>$run->submission_id, 'run_id'=>$run->state === 'completed' ? $run->id : $run->original]];
    }

    private function comparison(array $finding, array $before, array $after): array
    {
        $public = fn ($result) => Arr::only($result, ['state','points','maximum']) + ['feedback'=>$this->feedback($result)];
        return ['finding'=>Arr::only($finding,['claim','focus','evidence']), 'before'=>$public($before), 'after'=>$public($after)];
    }

    public function selected(Course $course, Task $task, User $user, string $submission, string $run, bool $includeSource): array
    {
        $record = CodeSubmission::where(['course_id'=>$course->id, 'task_id'=>$task->id, 'user_id'=>$user->id])->findOrFail($submission);
        $result = $this->nativeResult($record, $course, $task, $user, $run);
        abort_unless($result['state'] === 'completed' && in_array($result['method'], ['tests','manual', ...Configuration::AI_TYPES], true), 404);
        $selected = ['result'=>$result];
        if ($includeSource) $selected['source'] = $this->source($record);
        $review = CodeExecutionRun::where('submission_id',$record->id)->whereKey($run)
            ->where('snapshot->ai_review->origin','tutor')
            ->first(['snapshot->ai_review->source_run_id as original', 'snapshot->ai_review->finding as finding']);
        $finding = json_decode($review->finding ?? 'null', true);
        if ($review && $review->original && is_array($finding) && isset($finding['focus'])) {
            $prior = $this->nativeResult($record, $course, $task, $user, $review->original);
            $selected['comparison'] = $this->comparison($finding, $prior, $result);
        }
        return $selected;
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
        foreach ($result['groups'] ?? [] as $group) {
            $lines->push($group['name'].' · '.($group['executed'] ? $group['passed'].' из '.$group['total'] : 'тесты не запускались'));
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
        if (in_array($run->method, ['tests', Configuration::TESTS_THEN_AI], true)) {
            $report = CodeTestReport::where(['user_id'=>$user->id,'course_id'=>$course->id,'task_id'=>$task->id])->find($run->id);
            $unlocked = $report ? DB::table('code_test_purchases')->where(['user_id'=>$user->id, 'task_id'=>$task->id])
                ->pluck('test_id')->all() : [];
            $result += $this->tests($report?->report ?? $run->legacy_report, $unlocked);
        }
        if ($run->state === 'completed' && in_array($run->method, ['manual', ...Configuration::AI_TYPES], true)) {
            $result['comment'] = (string) $run->comment;
            $result['criteria'] = array_map(fn ($criterion) => Arr::only($criterion, ['title','points','maximum','status','comment']), $run->criteria ?? []);
        }
        return $result;
    }

    private function tests(?array $report, array $unlocked = []): array
    {
        if ($report === null) return [];
        $result = Arr::only($report, ['passed','total','groups']);
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
