<?php

namespace App\Services\CodeChecking;

use App\CodeExecutionRun;
use App\CodeSubmission;
use App\CodeTaskChecker;
use App\CodeTestReport;
use App\Jobs\DispatchCodeExecution;
use App\Task;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Submissions
{
    public function __construct(private Artifacts $artifacts, private Access $access, private Configuration $configuration) {}

    public function create(Request $request, int $courseId, Task $task): CodeSubmission
    {
        $user = $request->user();
        abort_unless($user->role === 'student' && !$this->access->teacher($user, $courseId), 403);
        abort_if($task->isBlocked($user->id, $courseId), 403, 'Task is blocked.');
        $checker = CodeTaskChecker::findOrFail($task->id);
        abort_if($checker->config['requires_conversion'] ?? false, 409, 'Legacy checking configuration needs conversion.');
        $payloads = app(SubmissionPayload::class);
        $input = $payloads->body($request);
        abort_unless(is_array($input) && Str::isUuid($input['request_id'] ?? ''), 422, 'request_id must be a UUID.');
        $value = $payloads->parse($request, $checker->config, $courseId, $task->id);
        $snapshot = $this->configuration->snapshot($task, $checker);
        $language = $value['language'];
        $files = $value['files'];
        if ($value['mode'] !== 'repository') {
            $files = $this->executionFiles(new CodeSubmission(['files' => $files]), $snapshot);
            $value['files'] = $files;
        }
        $hash = hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
        return app(\App\Services\CoinWallet::class)->transaction($user->id, function () use ($user, $courseId, $task, $checker, $language, $input, $files, $hash, $value, $snapshot) {
            $existing = CodeSubmission::where('user_id', $user->id)->where('course_id', $courseId)->where('task_id', $task->id)
                ->where('request_id', $input['request_id'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'Request ID was used for another submission.');
                return $existing;
            }
            $credit = app(Attempts::class)->reserve($user, $task, $checker);
            $submissionId = (string) Str::uuid();
            $runId = (string) Str::uuid();
            $submission = CodeSubmission::create(['id' => $submissionId, 'user_id' => $user->id, 'course_id' => $courseId,
                'task_id' => $task->id, 'request_id' => $input['request_id'], 'payload_hash' => $hash,
                'language' => $language, 'files' => $files, 'current_run_id' => $runId, 'mode' => $value['mode'],
                'answer_text' => $value['text'], 'source_metadata' => $value['source_metadata'],
                'preparation_state' => $value['mode'] === 'repository' ? 'pending' : 'ready']);
            $this->newRun($submission, $runId, $snapshot);
            if ($snapshot['check_type'] === 'manual' && \Illuminate\Support\Facades\Schema::hasTable('solutions')) {
                // Manual submissions must enter the existing teacher review queue immediately.
                $solution = new \App\Solution();
                $solution->forceFill(['user_id' => $user->id, 'course_id' => $courseId, 'task_id' => $task->id,
                    'text' => url('/insider/code-submissions/'.$submissionId), 'submitted' => $submission->created_at,
                    'teacher_id' => \App\Course::findOrFail($courseId)->teachers->first()?->id])->save();
                $submission->update(['solution_id' => $solution->id]);
                \App\CourseActivity::recordSolutionSubmitted($solution);
            }
            if ($credit !== null) DB::table('code_attempt_credits')->where('id', $credit)->update(['submission_id' => $submissionId]);
            \App\Jobs\CheckCodeIntegrity::dispatch($submissionId)->afterCommit();
            return $submission;
        });
    }

    private function newRun(CodeSubmission $submission, string $runId, array $snapshot): CodeExecutionRun
    {
        $run = CodeExecutionRun::create(['id' => $runId, 'submission_id' => $submission->id, 'snapshot' => $snapshot,
            'state' => $snapshot['check_type'] === 'manual' ? 'awaiting_review' : 'queued',
            'progress' => ['phase' => $snapshot['check_type'] === 'manual' ? 'awaiting_review' : 'queued', 'completed' => 0, 'total' => 0]]);
        if ($snapshot['check_type'] === 'manual' && $submission->mode !== 'repository') $run->update(['dispatched_at' => now()]);
        if ($snapshot['check_type'] !== 'manual' || $submission->mode === 'repository') DispatchCodeExecution::dispatch($runId)->afterCommit();
        return $run;
    }

    public function recheck(CodeSubmission $submission, User $teacher, bool $latestConfiguration = false): CodeExecutionRun
    {
        $task = $this->access->task($teacher, $submission->course_id, $submission->task_id, true);
        return DB::transaction(function () use ($submission, $task, $latestConfiguration) {
            $locked = CodeSubmission::lockForUpdate()->findOrFail($submission->id);
            $previous = $locked->currentRun;
            abort_unless($previous->finished_at || $previous->state === 'awaiting_review', 409, 'A check is already running.');
            $snapshot = $latestConfiguration
                ? $this->configuration->snapshot($task, CodeTaskChecker::findOrFail($task->id)) : $previous->snapshot;
            abort_if(($snapshot['requires_conversion'] ?? false)
                || (($submission->source_metadata['requires_conversion'] ?? false) && $snapshot['check_type'] !== 'manual'),
                409, 'Convert the legacy checker/source or select a converted configuration.');
            $snapshot['legacy']=false;
            $id = (string) Str::uuid();
            $run = $this->newRun($locked, $id, $snapshot);
            $locked->update(['current_run_id' => $id]);
            return $run;
        });
    }

    public function manualReview(CodeSubmission $submission, User $teacher): CodeExecutionRun
    {
        $this->access->task($teacher,$submission->course_id,$submission->task_id,true);
        return DB::transaction(function () use ($submission) {
            $locked=CodeSubmission::lockForUpdate()->findOrFail($submission->id);
            $old=$locked->currentRun;
            abort_unless($old->finished_at || in_array($old->state,['imported_pending','awaiting_review'],true),409,'Дождитесь окончания проверки.');
            if ($old->state==='awaiting_review' && $old->snapshot['check_type']==='manual') return $old;
            $snapshot=array_replace($old->snapshot,['check_type'=>'manual','requires_conversion'=>false,'legacy'=>false,'manual_override_of'=>$old->id]);
            $run=$this->newRun($locked,(string)Str::uuid(),$snapshot);
            $locked->update(['current_run_id'=>$run->id]);
            return $run;
        });
    }

    public function view(CodeSubmission $submission, User $viewer, bool $includeSource = true): array
    {
        $this->access->authorOrTeacher($viewer, $submission);
        $run = $submission->currentRun;
        $files = [];
        $sourceBudget = $includeSource ? 2097152 : 0;
        foreach ($submission->files as $index => $file) {
            $item = ['name' => $file['name'], 'original_name'=>$file['original_name']??$file['name'], 'bytes' => $file['artifact']['bytes'],
                'download_url' => route('code-submissions.file', ['submission' => $submission->id, 'file' => $index])];
            if ($file['artifact']['bytes'] <= $sourceBudget && preg_match('/\.(py|cpp|cc|c|h|hpp|txt|json|js|html|css|php|sql|java|cs|swift|xml|ipynb)$/iD', $file['name'])) {
                $text = $this->artifacts->get($file['artifact']['blob_id']);
                if (mb_check_encoding($text, 'UTF-8')) { $item['source'] = $text; $sourceBudget -= strlen($text); }
            }
            $files[] = $item;
        }
        $report = CodeTestReport::find($run->id);
        $awardedXp = $submission->solution_id ? \App\Solution::find($submission->solution_id)?->mark : null;
        return ['id' => $submission->id, 'user_id' => (int) $submission->user_id, 'course_id' => (int) $submission->course_id,
            'task_id' => (int) $submission->task_id, 'language' => $submission->language, 'files' => $files,
            'mode' => $submission->mode, 'text' => $includeSource ? $submission->answer_text : null,
            'user_name' => User::find($submission->user_id)?->name,
            'maximum_points' => $run->snapshot['points'],
            'repository' => array_intersect_key($submission->source_metadata ?? [], array_flip(['provider', 'url', 'ref', 'commit'])),
            'preparation_state' => $submission->preparation_state,
            'created_at' => $submission->created_at?->toIso8601String(), 'execution_id' => $run->id,
            'state' => $run->state, 'progress' => $run->progress, 'points' => $run->points, 'comment' => $run->comment,
            'awarded_xp' => $awardedXp,
            'report' => $report ? app(Reports::class)->view($report, $viewer) : $run->legacy_report,
            'teacher_viewed'=>$submission->teacher_viewed, 'is_public'=>$submission->is_public,
            'review_request'=>app(SubmissionReview::class)->status($submission),
            'legacy'=>isset($submission->source_metadata['legacy_id']) ? [
                'source_url'=>url('/insider/code-submissions/'.$submission->id.'/legacy-source'),
                'warnings'=>$submission->source_metadata['migration_warnings']??[],
                'requires_conversion'=>$submission->source_metadata['requires_conversion']??false,
                'commit_unknown'=>$submission->source_metadata['commit_unknown']??false] : null,
            'runs' => CodeExecutionRun::where('submission_id', $submission->id)->orderByDesc('created_at')->get()
                ->map(fn ($item) => ['id' => $item->id, 'state' => $item->state, 'points' => $item->points,
                    'comment' => $item->comment, 'created_at' => $item->created_at?->toIso8601String()])->all(),
            'integrity' => app(Integrity::class)->view($submission, $this->access->teacher($viewer, (int) $submission->course_id)),
            'subscription' => $run->snapshot['check_type'] === 'manual' ? null : app(RunnerClient::class)->subscription($run->id, (int) $viewer->id)];
    }

    public function input(CodeExecutionRun $run): array
    {
        $submission = $run->submission;
        abort_unless($submission->preparation_state === 'ready' && $run->snapshot['check_type'] !== 'manual', 409, 'Execution input is not ready.');
        $withUrls = function ($value) use (&$withUrls, $run) {
            if (!is_array($value)) return $value;
            if (($value['type'] ?? null) === 'blob') {
                $token = app(ServiceToken::class)->issue('execution-artifact', 'codingprojects',
                    ['execution_id' => $run->id, 'blob_id' => $value['blob_id']], 300);
                $base = rtrim(config('services.code_executor_callback_url') ?: config('app.url'), '/');
                $value['download_url'] = $base.'/api/executor/artifacts/'.$value['blob_id'].'?token='.rawurlencode($token);
                return $value;
            }
            return array_map($withUrls, $value);
        };
        return ['execution_id' => $run->id, 'submission_id' => $submission->id, 'user_id' => (int) $submission->user_id,
            'course_id' => (int) $submission->course_id, 'task_id' => (int) $submission->task_id,
            'task' => $withUrls(array_replace($run->snapshot, ['language' => $submission->language])), 'files' => $withUrls($this->executionFiles($submission, $run->snapshot)), 'language' => $submission->language,
            'answer' => ['mode' => $submission->mode, 'text' => $submission->answer_text ?? '']];
    }
    public function executionFiles(CodeSubmission $submission, array $snapshot): array
    {
        $files = array_values(array_filter($submission->files, fn ($file) => ($file['origin'] ?? 'student') !== 'task'));
        foreach ($snapshot['task_files'] ?? [] as $file) {
            if ($file['kind'] !== 'starter' || !$file['read_only']) continue;
            $matches = array_keys(array_column($files, 'name'), $file['name'], true);
            foreach ($matches as $index) abort_unless($files[$index]['artifact']['sha256'] === $file['artifact']['sha256'], 422, 'Read-only starter was modified.');
            if (!$matches) $files[] = ['name' => $file['name'], 'artifact' => $file['artifact'], 'origin' => 'task'];
        }
        $names = [];
        foreach ($files as $file) {
            foreach ($names as $other) abort_if(str_starts_with($file['name'].'/', $other.'/') || str_starts_with($other.'/', $file['name'].'/'), 422, 'Conflicting starter paths.');
            $names[] = $file['name'];
        }
        return $files;
    }

    public function recheckLatest(User $teacher, int $course, int $task, bool $latest): array
    {
        $this->access->task($teacher, $course, $task, true);
        $seen = []; $queued = []; $skipped = [];
        foreach (CodeSubmission::where('course_id', $course)->where('task_id', $task)->orderByDesc('created_at')->orderByDesc('id')->cursor() as $submission) {
            if (isset($seen[$submission->user_id])) continue;
            $seen[$submission->user_id] = true;
            if (!$submission->currentRun->finished_at && $submission->currentRun->state !== 'awaiting_review') {
                $skipped[] = $submission->id;
                continue;
            }
            $queued[] = $this->recheck($submission, $teacher, $latest)->id;
        }
        return ['execution_ids' => $queued, 'skipped_active' => $skipped];
    }

}
