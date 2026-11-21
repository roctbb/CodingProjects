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
        $this->access->task($user,$courseId,(int)$task->id);
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

    public function recheck(CodeSubmission $submission, User $teacher, bool $latestConfiguration = false, bool $strongerAi = false, string $comment = '',?int $reviewSolution=null, ?string $expectedRun=null): CodeExecutionRun
    {
        $task = $this->access->task($teacher, $submission->course_id, $submission->task_id, true);
        return DB::transaction(function () use ($submission, $task, $teacher, $latestConfiguration, $strongerAi, $comment,$reviewSolution,$expectedRun) {
            $locked = CodeSubmission::lockForUpdate()->findOrFail($submission->id);
            abort_if($expectedRun !== null && $expectedRun !== $locked->current_run_id, 409, 'По этой посылке уже началась другая проверка. Откройте её в чате.');
            $previous = $locked->currentRun;
            $automatic=$this->automaticRun($locked,$previous);
            abort_unless($previous->finished_at || $previous->state === 'awaiting_review', 409, 'A check is already running.');
            abort_if($strongerAi && $latestConfiguration, 422, 'Для усиленной ИИ-проверки используются настройки исходной проверки.');
            abort_if($strongerAi && ($automatic->snapshot['check_type'] ?? null) !== 'gpt', 409, 'Исходное решение проверялось без ИИ.');
            abort_if($strongerAi && $this->sourceVersionUnknown($locked) && $reviewSolution===null, 409, 'Для перепроверки сохранённых файлов выберите конкретную сдачу ученика.');
            if($reviewSolution!==null){
                $event=app(History::class)->event($teacher,$locked->course_id,$task,$reviewSolution);
                abort_unless(($event['source_submission_id']??null)===$locked->id,409,'Сдача относится к другим исходникам. Обновите страницу.');
            }
            $snapshot = $latestConfiguration
                ? $this->configuration->snapshot($task, CodeTaskChecker::findOrFail($task->id)) : $automatic->snapshot;
            abort_if(($snapshot['requires_conversion'] ?? false)
                || (($submission->source_metadata['requires_conversion'] ?? false) && $snapshot['check_type'] !== 'manual'),
                409, 'Convert the legacy checker/source or select a converted configuration.');
            $snapshot['legacy']=false;
            unset($snapshot['review_solution_id']);
            if($reviewSolution!==null)$snapshot['review_solution_id']=$reviewSolution;
            if (!$strongerAi && !$latestConfiguration && isset($snapshot['ai_review'])) {
                // The ordinary action restores the frozen original model, even after several stronger checks.
                $baseRun = $automatic;
                $seen = [$baseRun->id => true];
                while ($source = $baseRun->snapshot['ai_review']['source_run_id'] ?? null) {
                    $baseRun = CodeExecutionRun::where('submission_id', $locked->id)->find($source);
                    abort_unless($baseRun && !isset($seen[$baseRun->id]), 409, 'Не удалось восстановить настройки обычной модели.');
                    $seen[$baseRun->id] = true;
                }
                foreach (['gpt_model', 'gpt_timeout'] as $key) {
                    if (array_key_exists($key, $baseRun->snapshot)) $snapshot[$key] = $baseRun->snapshot[$key];
                    else unset($snapshot[$key]);
                }
                unset($snapshot['ai_review']);
            }
            // Replace a prior reviewer note when checking the same immutable work again.
            if (isset($snapshot['review_instructions_original'])) $snapshot['private_instructions'] = $snapshot['review_instructions_original'];
            unset($snapshot['review_comment'], $snapshot['review_instructions_original']);
            if (trim($comment) !== '') {
                $snapshot['review_comment'] = trim($comment);
                if (($snapshot['check_type'] ?? null) === 'gpt') {
                    $snapshot['review_instructions_original'] = $snapshot['private_instructions'] ?? '';
                    $snapshot['private_instructions'] = trim(($snapshot['private_instructions'] ?? '')."\n\nКомментарий преподавателя к перепроверке:\n".trim($comment));
                }
            }
            if ($strongerAi) {
                $model = app(\App\Services\Ai\AiSettings::class)->recheck();
                abort_unless($model, 409, 'Модель для повторной ИИ-проверки не настроена администратором.');
                $snapshot = $this->strongerSnapshot($snapshot, $automatic, $model, $teacher);
            }
            $id = (string) Str::uuid();
            $run = $this->newRun($locked, $id, $snapshot);
            $locked->update(['current_run_id' => $id]);
            return $run;
        });
    }

    private function strongerSnapshot(array $snapshot, CodeExecutionRun $source, array $model, User $requester): array
    {
        $snapshot['gpt_model'] = $model['model'];
        $snapshot['gpt_timeout'] = $model['timeout'];
        $snapshot['ai_review'] = ['source_run_id'=>$source->id, 'requested_by'=>(int) $requester->id,
            'previous_model'=>$source->snapshot['gpt_model'] ?? null, 'model'=>$model['model'], 'timeout'=>$model['timeout']];
        return $snapshot;
    }

    /** Called only with a tutor finding independently accepted for this student's frozen source. */
    public function tutorRecheck(User $student, int $course, Task $task, array $target, array $finding): string
    {
        return DB::transaction(function () use ($student, $course, $task, $target, $finding) {
            $submission = CodeSubmission::where(['user_id'=>$student->id, 'course_id'=>$course, 'task_id'=>$task->id])
                ->lockForUpdate()->find($target['submission']);
            if (!$submission) return 'unavailable';
            $existing = CodeExecutionRun::where('submission_id', $submission->id)
                ->where('snapshot->ai_review->origin', 'tutor')->where('snapshot->ai_review->source_run_id', $target['run'])->exists();
            if ($existing) return 'already_requested';
            $run = $submission->currentRun;
            if (!$run || $run->id !== $target['run']) return 'superseded';
            $snapshot = $run->snapshot;
            if ($run->state !== 'completed' || !$run->finished_at || $run->graded_by || $submission->preparation_state !== 'ready'
                || ($snapshot['check_type'] ?? null) !== 'gpt' || isset($snapshot['ai_review'])
                || ($snapshot['requires_conversion'] ?? false) || ($submission->source_metadata['requires_conversion'] ?? false)
                || $this->sourceVersionUnknown($submission) || $task->isBlocked($student->id, $course)) return 'teacher';
            if ($submission->solution_id) {
                $lastGrade = \App\CourseActivity::where('solution_id', $submission->solution_id)
                    ->where('type', \App\CourseActivity::TYPE_SOLUTION_CHECKED)->latest('id')->first(['payload']);
                if (($lastGrade?->payload['feedback_source'] ?? null) === 'teacher') return 'teacher';
            }
            $model = app(\App\Services\Ai\AiSettings::class)->recheck();
            if (!$model || $model['model'] === ($snapshot['gpt_model'] ?? null)) return 'unavailable';
            if (!\App\Services\Tutor\Budget::canRecheck($student->id)) return 'teacher';
            $snapshot = $this->strongerSnapshot($snapshot, $run, $model, $student);
            $snapshot['ai_review']['origin'] = 'tutor';
            $snapshot['ai_review']['finding'] = array_intersect_key($finding, array_flip(['claim','evidence','focus']));
            $id = (string) Str::uuid();
            $this->newRun($submission, $id, $snapshot);
            $submission->update(['current_run_id'=>$id]);
            return 'queued';
        });
    }

    /** Retry infrastructure failures against the same immutable submission and configuration. */
    public function retry(CodeSubmission $submission, User $viewer): CodeExecutionRun
    {
        $this->access->authorOrTeacher($viewer, $submission);
        return DB::transaction(function () use ($submission) {
            $locked = CodeSubmission::lockForUpdate()->findOrFail($submission->id);
            abort_unless($this->retryAllowed($locked, $locked->currentRun), 409, 'Повторить можно только завершившуюся технической ошибкой проверку.');
            $snapshot = $locked->currentRun->snapshot;
            // This is a new native run; a later archive import must not overwrite it.
            $snapshot['legacy'] = false;
            $run = $this->newRun($locked, (string) Str::uuid(), $snapshot);
            $locked->update(['current_run_id' => $run->id]);
            return $run;
        });
    }

    private function retryAllowed(CodeSubmission $submission, CodeExecutionRun $run): bool
    {
        return (string) $submission->current_run_id === (string) $run->id && $run->state === 'error'
            && $run->finished_at && $submission->preparation_state === 'ready'
            && !$this->sourceVersionUnknown($submission)
            && in_array($run->snapshot['check_type'] ?? null, ['tests', 'gpt'], true)
            && !($run->snapshot['requires_conversion'] ?? false)
            && !($submission->source_metadata['requires_conversion'] ?? false)
            && !Task::findOrFail($submission->task_id)->isBlocked($submission->user_id, $submission->course_id);
    }

    private function sourceVersionUnknown(CodeSubmission $submission): bool
    {
        return $submission->legacy_id !== null || isset($submission->source_metadata['legacy_id']);
    }

    private function automaticRun(CodeSubmission $submission,CodeExecutionRun $run):CodeExecutionRun
    {
        $seen=[$run->id=>true];
        while(($run->snapshot['check_type']??null)==='manual' && isset($run->snapshot['manual_override_of'])){
            $previous=CodeExecutionRun::where('submission_id',$submission->id)->find($run->snapshot['manual_override_of']);
            if(!$previous || isset($seen[$previous->id]))break;
            $seen[$previous->id]=true;$run=$previous;
        }
        return $run;
    }

    public function executionError(CodeSubmission $submission, CodeExecutionRun $run): ?array
    {
        if ($run->state !== 'error') return null;
        $allowed = $this->retryAllowed($submission, $run);
        // Upstream comments can contain tracebacks, private tests, URLs or credentials.
        return ['code' => 'execution_error',
            'message' => 'Не удалось завершить проверку из-за технической ошибки. Оценка за этот запуск не выставлена.',
            'can_retry' => $allowed,
            'retry_unavailable_reason' => $this->sourceVersionUnknown($submission) ? 'Версия кода исторической сдачи не подтверждена. Обратитесь к преподавателю.' : null,
            'retry_url' => $allowed ? url('/insider/code-submissions/'.$submission->id.'/retry') : null];
    }

    public function aiRecheck(CodeSubmission $submission, CodeExecutionRun $run, User $viewer): ?array
    {
        $automatic=$this->automaticRun($submission,$run);
        if (($automatic->snapshot['check_type'] ?? null) !== 'gpt' || !$this->access->teacher($viewer, (int) $submission->course_id)) return null;
        $model = app(\App\Services\Ai\AiSettings::class)->recheck();
        return ['available' => (bool) ($model && $run->finished_at && (string) $submission->current_run_id === (string) $run->id
                && !($automatic->snapshot['requires_conversion'] ?? false) && !($submission->source_metadata['requires_conversion'] ?? false)),
            'model' => $model['model'] ?? null,
            'requires_review_solution'=>$this->sourceVersionUnknown($submission),
            'reason' => !$model ? 'Модель для повторной ИИ-проверки не настроена администратором.'
                : (($automatic->snapshot['requires_conversion']??false)||($submission->source_metadata['requires_conversion']??false)?'Сначала настройте проверку этой задачи.':null)];
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

    public function view(CodeSubmission $submission, User $viewer, bool $includeSource = true,bool $includeTestFields=true): array
    {
        $this->access->authorOrTeacher($viewer, $submission);
        $run = $submission->currentRun;
        $files = [];
        $sourceBudget = $includeSource ? 2097152 : 0;
        foreach ($submission->files as $index => $file) {
            $item = ['name' => $file['name'], 'is_answer' => !empty($file['is_answer']), 'original_name'=>$file['original_name']??$file['name'], 'bytes' => $file['artifact']['bytes'],
                'download_url' => route('code-submissions.file', ['submission' => $submission->id, 'file' => $index])];
            if (app(ArchivePreview::class)->available($submission, $index)) {
                $item['archive_url'] = route('code-submissions.archive', ['submission' => $submission->id, 'file' => $index]);
            }
            if ($file['artifact']['bytes'] <= $sourceBudget && preg_match('/\.(py|cpp|cc|c|h|hpp|txt|json|js|html|css|php|sql|java|cs|swift|xml|ipynb|md|markdown|csv|tsv|yml|yaml|toml|ini|log|rst)$/iD', $file['name'])) {
                $text = $this->artifacts->get($file['artifact']['blob_id']);
                if (mb_check_encoding($text, 'UTF-8') && !str_contains($text,"\0")) { $item['source'] = $text; $sourceBudget -= strlen($text); }
            }
            $files[] = $item;
        }
        $report = CodeTestReport::find($run->id);
        $reportView=$report?app(Reports::class)->view($report,$viewer,$includeTestFields)
            :(isset($run->legacy_report['suite_diagnostics'])?null:app(ArchivedTests::class)->view($run,$viewer,$includeTestFields));
        if($reportView){
            $reportView['run_id']=$run->id;
            if(!$includeTestFields)$reportView=app(Reports::class)->metadata($reportView);
        }
        $legacy=isset($submission->source_metadata['legacy_id']);
        // An imported mutable GP fragment cannot identify which CP attempt earned XP.
        $solution = !$legacy && $submission->solution_id ? \App\Solution::find($submission->solution_id) : null;
        $awardedXp = $solution?->mark;
        return ['id' => $submission->id, 'user_id' => (int) $submission->user_id, 'course_id' => (int) $submission->course_id,
            'task_id' => (int) $submission->task_id, 'language' => $submission->language, 'files' => $files,
            'mode' => $submission->mode, 'text' => $includeSource ? $submission->answer_text : null,
            'bundle_url'=>count($files)>1?route('code-submissions.bundle',['submission'=>$submission->id]):null,
            'user_name' => $submission->author?->name,
            'maximum_points' => $run->snapshot['points'],
            'check_type'=>$run->snapshot['check_type']??null,
            'recheck_type'=>$this->automaticRun($submission,$run)->snapshot['check_type']??null,
            'repository' => array_intersect_key($submission->source_metadata ?? [], array_flip(['provider', 'url', 'ref', 'commit'])),
            'preparation_state' => $submission->preparation_state,
            'created_at' => $submission->created_at?->toIso8601String(), 'execution_id' => $run->id,
            'checked_at'=>$run->finished_at?->toIso8601String(),
            'state' => $run->state, 'progress' => $run->progress, 'points' => $run->points, 'criteria' => $run->criteria,
            'execution_error' => $this->executionError($submission, $run),
            'ai_recheck' => $this->aiRecheck($submission, $run, $viewer),
            'comment' => $run->state === 'error' || (!$includeTestFields && ($reportView || ($run->snapshot['check_type']??null)==='tests'))?'':$run->comment,
            'text_report_available'=>$run->state !== 'error' && !$includeTestFields && !$reportView && ($run->snapshot['check_type']??null)==='tests' && (bool)($run->comment || isset($run->legacy_report['suite_diagnostics'])),
            'text_report_url'=>url('/insider/code-submissions/'.$submission->id.'/runs/'.$run->id).'?test_data=full',
            'awarded_xp' => $awardedXp,
            'award_breakdown'=>$solution ? app(SolutionAward::class)->view($solution, $viewer) : null,
            'report' => $reportView,
            'checker_diagnostics' => $includeTestFields && $this->access->teacher($viewer,(int)$submission->course_id) ? ($run->legacy_report['suite_diagnostics']??null) : null,
            'teacher_viewed'=>$submission->teacher_viewed, 'is_public'=>$submission->is_public,
            'review'=>$this->access->teacher($viewer,(int)$submission->course_id) ? app(ReviewActions::class)->forSolution($solution) : null,
            'lesson_progress'=>$this->lessonProgress($submission),
            'review_request'=>app(SubmissionReview::class)->status($submission),
            'legacy'=>isset($submission->source_metadata['legacy_id']) ? [
                'source_url'=>url('/insider/code-submissions/'.$submission->id.'/legacy-source'),
                'warnings'=>$submission->source_metadata['migration_warnings']??[],
                'requires_conversion'=>$submission->source_metadata['requires_conversion']??false,
                'commit_unknown'=>$submission->source_metadata['commit_unknown']??false] : null,
            'source_version_unknown'=>$legacy,
            'runs' => CodeExecutionRun::where('submission_id', $submission->id)->orderByDesc('created_at')->get(['id','state','points','created_at'])
                ->map(fn ($item) => ['id' => $item->id, 'state' => $item->state, 'points' => $item->points,
                    'created_at' => $item->created_at?->toIso8601String()])->all(),
            'integrity' => app(Integrity::class)->view($submission, $this->access->teacher($viewer, (int) $submission->course_id), $viewer),
            'subscription' => $run->snapshot['check_type'] === 'manual' ? null : app(RunnerClient::class)->subscription($run->id, (int) $viewer->id)];
    }

    private function lessonProgress(CodeSubmission $submission): ?array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('lesson_student_stats')) return null;
        $stats=DB::table('lesson_student_stats as stats')->join('program_steps as step','step.lesson_id','=','stats.lesson_id')
            ->join('tasks as task','task.step_id','=','step.id')->where('task.id',$submission->task_id)
            ->where('stats.course_id',$submission->course_id)->where('stats.student_id',$submission->user_id)
            ->first(['stats.lesson_id','stats.percent','stats.max_points']);
        if (!$stats || (int)$stats->max_points <= 0) return null;
        return ['lesson_id'=>(int)$stats->lesson_id,'percent'=>min(100,max(0,(float)$stats->percent)), 'earned'=>(float)$stats->percent>90];
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
        $settings = array_replace($run->snapshot, ['language' => $submission->language]);
        if ($settings['check_type'] === 'gpt') {
            $settings['grading_format'] = 'criteria-v1';
            $settings['qualitative_criteria'] = true;
        }
        return ['execution_id' => $run->id, 'submission_id' => $submission->id, 'user_id' => (int) $submission->user_id,
            'course_id' => (int) $submission->course_id, 'task_id' => (int) $submission->task_id,
            'task' => $withUrls($settings), 'files' => $withUrls($this->executionFiles($submission, $run->snapshot)), 'language' => $submission->language,
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
        foreach (CodeSubmission::forCourseStudents($course)->where('task_id', $task)->orderByDesc('created_at')->orderByDesc('id')->cursor() as $submission) {
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
