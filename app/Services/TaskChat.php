<?php

namespace App\Services;

use Carbon\Carbon;
use App\Task;
use App\Services\CodeChecking\Artifacts;
use App\Services\CodeChecking\History;
use App\Services\CodeChecking\HistoricalReport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TaskChat
{
    private const FILE = 1;
    private const LEGACY_FILE = 2;
    private const CHECK = 3;
    private const LEGACY_GRADE = 4;
    private const MESSAGE = 5;

    private function scope(int $course, int $task, int $student): array
    {
        return ['course_id'=>$course, 'task_id'=>$task, 'user_id'=>$student];
    }

    public function send(int $course, int $task, int $student, int $author, array $input, bool $withTutor = false): void
    {
        $scope = $this->scope($course, $task, $student);
        $body = trim($input['body']);
        abort_if($body === '', 422);
        DB::transaction(function () use ($course, $task, $student, $author, $scope, $input, $body, $withTutor) {
            $submission = $solution = null;
            $attempt = $input['attempt'] ?? null;
            if ($attempt && str_starts_with($attempt, 'solution-')) {
                $solution = DB::table('solutions')->where($scope)->whereNotNull('submitted')
                    ->where('id', substr($attempt, 9))->lockForUpdate()->value('id');
                abort_if($solution === null, 404);
                $submission = DB::table('code_submissions')->where($scope)->whereNull('legacy_id')
                    ->where('solution_id', $solution)->value('id');
                if ($submission !== null) $solution = null;
            } elseif ($attempt) {
                $submission = DB::table('code_submissions')->where($scope)
                    ->where('id', $attempt)->lockForUpdate()->value('id');
                abort_if($submission === null, 404);
            }
            $runId = $input['run_id'] ?? null;
            if ($runId !== null) {
                abort_unless($submission && DB::table('code_execution_runs')->where(['id'=>$runId, 'submission_id'=>$submission])->exists(), 404);
            }
            $key = ['author_id'=>$author,'client_id'=>$input['client_id']];
            $message = ['course_id'=>$course,'task_id'=>$task,'student_id'=>$student,
                'submission_id'=>$submission,'solution_id'=>$solution,'review_run_id'=>$runId,'body'=>$body];
            $table = DB::table('task_chat_messages');
            $table->insertOrIgnore($key + $message + ['created_at'=>now()->format('Y-m-d H:i:s.u')]);
            $id = $table->where($key + $message)->value('id');
            abort_unless($id, 409, 'Этот запрос уже отправлен с другим сообщением.');
            if ($withTutor && !DB::table('task_chat_messages')->where('answer_to',$id)->exists()) \App\Jobs\AnswerTaskQuestion::dispatch((int)$id)->afterCommit();
        });
    }

    public function recordRecheck(\App\Solution $solution, ?string $submission = null): void
    {
        DB::table('task_chat_messages')->insert([
            'course_id'=>$solution->course_id, 'task_id'=>$solution->task_id,
            'student_id'=>$solution->user_id, 'author_id'=>$solution->user_id,
            'solution_id'=>$submission ? null : $solution->id, 'submission_id'=>$submission,
            'automatic'=>true, 'body'=>"**Запрос на перепроверку**\n\n".$solution->recheck_comment,
            'client_id'=>(string) \Illuminate\Support\Str::uuid(), 'created_at'=>now()->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function waitingStudents(int $course, array $tasks, ?int $student = null)
    {
        return DB::table('task_chat_messages')->where('course_id', $course)->whereIn('task_id', $tasks)
            ->when($student !== null, fn ($q) => $q->where('student_id', $student))->where('automatic', false)
            ->select('task_id', 'student_id')->groupBy('task_id', 'student_id')
            ->havingRaw('max(case when author_id = student_id then id else 0 end) > max(case when author_id <> student_id then id else 0 end)')
            ->get()->groupBy('task_id')->map(fn ($rows) => $rows->pluck('student_id')->flip());
    }

    private function events(Builder $query, int $kind, string $id, string $time): Builder
    {
        return $query->selectRaw("$kind as kind, CAST($id AS CHAR(64)) as event_id, $time as happened_at");
    }

    public function page(int $course, Task $assignment, int $student): array
    {
        $task = (int)$assignment->id;
        $scope = $this->scope($course, $task, $student);
        $native = DB::table('code_submissions')->where($scope);
        $legacy = DB::table('solutions')->where($scope)->whereNotNull('submitted')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('code_submissions as native')
                ->whereNull('native.legacy_id')->whereColumn('native.solution_id','solutions.id')
                ->whereColumn('native.user_id','solutions.user_id')->whereColumn('native.task_id','solutions.task_id')
                ->whereColumn('native.course_id','solutions.course_id'));
        $messages = DB::table('task_chat_messages')->where(['course_id'=>$course,'task_id'=>$task,'student_id'=>$student]);
        $threadSources = DB::table('code_submissions')->where($scope)->select('id','legacy_id','current_run_id','preparation_state');
        $runs = DB::table('code_execution_runs as r')->joinSub($threadSources,'s','s.id','=','r.submission_id');

        $stream = $this->events(clone $native,self::FILE,'id','created_at')
            ->unionAll($this->events(clone $legacy,self::LEGACY_FILE,'id','submitted'))
            ->unionAll($this->events(clone $runs,self::CHECK,'r.id','COALESCE(r.finished_at,r.created_at)'))
            ->unionAll($this->events((clone $legacy)->whereNotNull('mark'),self::LEGACY_GRADE,'id','COALESCE(checked,submitted)'))
            ->unionAll($this->events(clone $messages,self::MESSAGE,'id','created_at'));

        // Page the combined timeline first. Large source files and test inputs are never loaded for a bubble.
        $page = DB::query()->fromSub($stream,'events')->orderByDesc('happened_at')->orderByDesc('kind')
            ->orderByDesc('event_id')->cursorPaginate(40,['*'],'before');
        $rows = $page->getCollection();
        $ids = fn (...$kinds) => $rows->whereIn('kind',$kinds)->pluck('event_id')->map(fn ($id) => trim($id))->all();
        $files = $native->whereIn('id',$ids(self::FILE))->get(['id','mode','files','legacy_id'])->keyBy('id');
        $legacyRows = $legacy->whereIn('id',$ids(self::LEGACY_FILE, self::LEGACY_GRADE))
            ->get(['id','mark','evaluated_mark','raw_mark','comment','geekpaste_code_id'])->keyBy('id');
        $manualTask = $legacyRows->isNotEmpty() && !$assignment->is_code && !$assignment->is_quiz
            && !in_array(DB::table('code_task_checkers')->where('task_id',$task)->value('config->check_type'), ['gpt','tests'], true);
        $teacherFeedback = collect();
        if ($legacyRows->isNotEmpty() && \Illuminate\Support\Facades\Schema::hasTable('course_activities')) {
            $teacherFeedback = \App\CourseActivity::where($scope)->where('type', \App\CourseActivity::TYPE_SOLUTION_CHECKED)
                ->whereIn('solution_id', $ids(self::LEGACY_GRADE))->orderBy('id')->get(['solution_id','payload'])->keyBy('solution_id');
        }
        $checks = $runs->leftJoin('code_test_reports as t','t.id','=','r.id')->whereIn('r.id',$ids(self::CHECK))
            ->get(['r.id','r.submission_id','s.legacy_id','s.current_run_id','s.preparation_state','r.state','r.points','r.comment','r.criteria','r.snapshot->check_type as method',
                'r.snapshot->points as maximum','r.snapshot->ai_review->source_run_id as stronger',
                'r.snapshot->review_solution_id as solution','t.report->passed as passed','t.report->total as total',
                'r.legacy_report->passed as archive_passed','r.legacy_report->total as archive_total',
                't.report->tests[0]->verdict as first_verdict','r.legacy_report->tests[0]->verdict as archive_verdict',
                'r.legacy_report->tests[0]->error->text as archive_diagnostic'])->keyBy('id');
        // Compilation precedes the first input. Fetch only the compiler diagnostic for those runs.
        $diagnostics = DB::table('code_test_reports')->whereIn('id', $checks->where('first_verdict','compilation_error')->keys())
            ->pluck('report->tests[0]->error as diagnostic','id');
        $notes = $messages->join('users as u','u.id','=','task_chat_messages.author_id')
            ->whereIn('task_chat_messages.id',$ids(self::MESSAGE))->get(['task_chat_messages.*','u.name'])->keyBy('id');

        $events = [];
        foreach ($rows->reverse() as $row) {
            $id = trim($row->event_id);
            $kind = (int) $row->kind;
            $item = ['id'=>$kind.'-'.$id, 'at'=>Carbon::parse($row->happened_at)->format('Y-m-d\TH:i:s.uP')];
            if ($kind === self::FILE && $file = $files->get($id)) {
                $names = array_column(json_decode($file->files,true) ?? [],'name');
                $item += ['type'=>'file','attempt'=>$id,'archived'=>$file->legacy_id!==null,'label'=>count($names)>1 ? 'Файлов: '.count($names) :
                    ($names[0] ?? ($file->mode === 'repository' ? 'Репозиторий' : 'Текст ответа'))];
            } elseif ($kind === self::LEGACY_FILE) {
                $legacyRow = $legacyRows->get($id);
                $item += ['type'=>'file','attempt'=>'solution-'.$id,'label'=>'Посылка № '.$id,
                    'awaiting_teacher'=>$manualTask && $legacyRow && !$legacyRow->geekpaste_code_id && $legacyRow->mark === null];
            } elseif ($kind === self::LEGACY_GRADE && $grade = $legacyRows->get($id)) {
                $comment = (string)$grade->comment;
                $feedback = $teacherFeedback->get($id)?->payload ?? [];
                $teacherComment = History::verifiedTeacherComment($feedback, $comment);
                $manual = $teacherComment !== null || ($manualTask && !$grade->geekpaste_code_id && !app(HistoricalReport::class)->parse($comment));
                $item += ['type'=>'grade','attempt'=>'solution-'.$id,'method'=>$manual ? 'manual' : null,
                    'points'=>$grade->evaluated_mark ?? $grade->raw_mark ?? $grade->mark,'maximum'=>(int)$assignment->max_mark,
                    'text'=>$manual ? ($teacherComment ?? $comment) : 'Начислено '.$grade->mark.' XP'];
            } elseif ($kind === self::CHECK && $run = $checks->get($id)) {
                $item += ['type'=>'check','attempt'=>$run->solution ? 'solution-'.$run->solution : $run->submission_id,
                    'source'=>$run->submission_id,'archived'=>$run->legacy_id!==null && !$run->solution,'run'=>$id,'state'=>$run->state,'method'=>$run->method,
                    'criteria'=>$run->criteria ? json_decode($run->criteria, true) : null,
                    'stronger'=>(bool)$run->stronger,'points'=>$run->points,'maximum'=>$run->maximum,
                    'passed'=>$run->passed ?? $run->archive_passed,'total'=>$run->total ?? $run->archive_total,
                    'reviewable'=>$run->current_run_id === $id && in_array($run->state, ['completed', 'error', 'imported_pending'], true)
                        && (!$run->legacy_id || $run->solution),
                    'awaiting_teacher'=>$run->method === 'manual' && $run->state === 'awaiting_review'
                        && $run->current_run_id === $id && $run->preparation_state === 'ready',
                    'text'=>in_array($run->method,['gpt','manual'],true) && $run->state==='completed' ? (string)$run->comment : ''];
                if (($run->first_verdict ?? $run->archive_verdict) === 'compilation_error') {
                    $item['compiler_error'] = $run->first_verdict ? $this->compilerPreview($diagnostics->get($id)) : mb_substr((string)$run->archive_diagnostic,0,1200);
                }
            } elseif ($kind === self::MESSAGE && $note = $notes->get($id)) {
                $item += ['type'=>$note->answer_to ? 'tutor' : 'message','author'=>(int)$note->author_id,
                    'name'=>$note->answer_to ? 'Ростислоп Нейрославович' : $note->name,'text'=>$note->body,
                    'tutor'=>$note->tutor_details ? json_decode($note->tutor_details, true) : null,
                    'attempt'=>$note->submission_id ?? ($note->solution_id ? 'solution-'.$note->solution_id : null)];
            } else continue;
            $events[] = $item;
        }
        $refs = collect($events)->pluck('attempt')->filter()->unique();
        $legacyIds = $refs->filter(fn ($id) => str_starts_with($id, 'solution-'))->map(fn ($id) => substr($id, 9));
        $labels = DB::table('solutions')->where($scope)->whereIntegerInRaw('id', $legacyIds)->pluck('submitted','id')
            ->mapWithKeys(fn ($time, $id) => ['solution-'.$id=>'Посылка № '.$id.' · '.Carbon::parse($time)->format('d.m H:i')]);
        $sources = DB::table('code_submissions')->where($scope)
            ->whereIn('id', $refs->reject(fn ($id) => str_starts_with($id,'solution-')))->get(['id','files','created_at','legacy_id']);
        foreach ($sources as $source) {
            $names = array_column(json_decode($source->files,true) ?? [], 'name');
            $label = count($names) === 1 ? $names[0] : 'Посылка';
            $labels[$source->id] = $label.' · '.Carbon::parse($source->created_at)->format('d.m H:i');
        }
        foreach ($events as &$entry) {
            if (isset($entry['attempt'])) $entry['reference'] = $labels->get($entry['attempt'], 'Посылка');
        }
        unset($entry);
        return ['events'=>$events,'before'=>$page->nextCursor()?->encode()];
    }

    private function compilerPreview(?string $json): string
    {
        $field = json_decode($json ?? 'null', true);
        $text = $field['text'] ?? '';
        if (($field['type'] ?? null) === 'blob') {
            $text = rescue(function () use ($field) {
                $stream = app(Artifacts::class)->readStream($field['blob_id']);
                try { return fread($stream, 4800); } finally { fclose($stream); }
            }, '');
        }
        return mb_substr(mb_scrub((string)$text, 'UTF-8'), 0, 1200);
    }
}
