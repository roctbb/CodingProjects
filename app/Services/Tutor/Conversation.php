<?php

namespace App\Services\Tutor;

use App\Course;
use App\Services\CourseAccess;
use App\User;
use Illuminate\Support\Facades\Cache;
use App\Services\CodeChecking\Submissions;

final class Conversation
{
    public function __construct(private CourseAccess $access, private Context $context, private Attempts $attempts,
        private Usage $meter, private Responder $responder) {}

    public function ask(User $user, int $course_id, int $id, array $data, ?array $chatHistory = null): array
    {
        $course = Course::findOrFail($course_id);
        $ownTeachingTask = isset($data['task_id']) && $this->access->manages($user, $course);
        abort_unless(Context::enabledForCourse($course_id), 503, 'Помощник ещё не подключён.');
        abort_unless($ownTeachingTask || ($user->role === 'student' && $course->students()->where('users.id', $user->id)->exists()), 403);
        $step = $this->access->step($course, $id);
        abort_if($step->review_archived_at, 404);
        $this->access->study($user, $course, $step->lesson);
        $task = null;
        if (isset($data['task_id'])) {
            $task = $this->access->task($course, $data['task_id']);
            abort_unless((int) $task->step_id === $id && $task->isVisible($user, $course), 404);
            abort_if($task->isBlocked($user->id, $course_id), 403);
        }
        $digest = hash('sha256', json_encode([$course_id, $id, $data]));
        $question = $data['question'];
        $last = Dialogue::lastAnswer($chatHistory ?? []);
        $followup = $last && Dialogue::continues($question);
        $comparison = null;
        $local = static fn (string $text) => ['answer'=>$text, 'links'=>[], 'guided'=>true, 'recheck'=>null];
        if ($task && ($help = Dialogue::screenHelp($question, $data['layout'] ?? null)) !== null) return $local($help);
        if ($task && $last && !isset($data['run_id'], $data['submission_id']) && empty($data['legacy_solution'])
            && empty($last['recheck']) && preg_match('/^\s*повтори(?:\s+ещ[её]\s+раз)?[.!?\s]*$/iu', $question)) {
            $reply = $local($last['text']);
            if (!empty($last['attempt'])) $reply['discussed'] = $last['attempt'];
            if (!empty($last['next_step'])) $reply['next_step'] = $last['next_step'];
            return $reply;
        }
        if ($task && ($data['action'] ?? null) === 'examples') {
            abort_unless(\App\CodeExecutionRun::where('submission_id',$data['submission_id'])->where('state','completed')
                ->where('snapshot->check_type','tests')->whereKey($data['run_id'])->exists(), 404);
            $record = \App\CodeTestReport::where(['user_id'=>$user->id, 'course_id'=>$course_id,'task_id'=>$task->id])->findOrFail($data['run_id']);
            $examples = app(\App\Services\CodeChecking\Reports::class)->tutorExamples($record, $user);
            return $local($examples ? 'Открой пример, чтобы сравнить результат со своим.' : 'У этой проверки нет доступных бесплатных примеров.')
                + ['test_examples'=>$examples, 'discussed'=>['run_id'=>$data['run_id'],'submission_id'=>$data['submission_id']]];
        }
        if ($task && !isset($data['run_id']) && !isset($data['legacy_solution'])) {
            if (preg_match('~(?<!\d)(\d{1,5})\s*(?:/|из)\s*(\d{1,5})(?!\d)~iu', $question, $score)
                && (Dialogue::aboutGrade($question) || preg_match('~^\s*(?:почему\s+)?\d+\s*(?:/|из)\s*\d+\s*[?!.]*$~iu', $question))) {
                $matches = $this->attempts->matchingGrade($course, $task, $user, (int)$score[1], (int)$score[2]);
                if (count($matches) !== 1) {
                    return $local($matches ? 'Такой балл встречается несколько раз. Выбери проверку по времени.'
                        : 'Среди завершённых проверок этой задачи такой оценки нет. Укажи нужную проверку в истории.')
                        + ['choices'=>$matches, 'choice_question'=>$question];
                }
                $data += $matches[0];
            } elseif ($last && !preg_match('/последн|нов[а-яё]* сдач/iu',$question)) {
                if ((!empty($last['recheck']) && Dialogue::aboutGrade($question))
                    || preg_match('/перепровер|столько же|тот же|тем же|не изменил|не поменял/iu', $question)) {
                    foreach (array_reverse($chatHistory ?? []) as $turn) {
                        if (($turn['role'] ?? null) === 'teacher') break;
                        $active = $last['attempt'] ?? [];
                        if (!empty($turn['attempt']) && $active
                            && (($turn['attempt']['submission_id'] ?? null) !== ($active['submission_id'] ?? null)
                                || ($turn['attempt']['legacy_solution'] ?? null) !== ($active['legacy_solution'] ?? null))) break;
                        if (!empty($turn['recheck']) && in_array($turn['recheck']['status'], ['queued','already_requested'], true)) {
                            $comparison = $this->attempts->recheckResult($course, $task, $user, $turn);
                            break;
                        }
                        if (!empty($turn['attempt']) && $turn['attempt'] !== ($last['attempt'] ?? null)) break;
                    }
                }
                if ($comparison) $data += $comparison['target'];
                elseif ($followup && !empty($last['attempt'])) $data += $last['attempt'];
            }
        }
        $mode = $task ? 'hint' : 'theory';
        $report = null;
        $target = null;
        $basis = null;
        $latestTarget = null;
        $latestLegacy = null;
        abort_if(isset($data['run_id']) && !$task, 422);
        $compact = $followup && !$comparison && !Dialogue::aboutGrade($question) && !isset($data['focus']);
        $readingQuery = $compact ? mb_substr($last['text'], -400).' '.$question : $question;
        $needsWork = !$compact || preg_match('/код|функци|параметр|строк|переменн|программ|файл|ошибк|сдач|[a-z_]{2,}/iu', $readingQuery);
        $focusedReview = $chatHistory !== null && (isset($data['run_id']) || isset($data['legacy_solution']));
        $work = $task ? ['latest_submission'=>null, 'previous_results'=>[]] : [];
        if ($task && $needsWork && !$focusedReview) {
            $work = $this->attempts->forTask($course, $task, $user, $data['submission_id'] ?? null, $latestTarget, $latestLegacy);
        }
        if (isset($data['run_id'])) {
            $mode = 'review';
            $target = ['submission'=>$data['submission_id'], 'run'=>$data['run_id']];
            $selected = $this->attempts->selected($course, $task, $user, $target['submission'], $target['run'],
                $needsWork && ($latestTarget['submission'] ?? null) !== $target['submission']);
            $comparison ??= $selected['comparison'] ?? null;
            $result = $selected['result'];
            $feedback = $this->attempts->feedback($result);
            $report = ['comment'=>mb_substr(implode("\n", $feedback), 0, 1800), 'points'=>$result['points'], 'maximum'=>$result['maximum']];
            $basis = ['feedback'=>$feedback] + array_intersect_key($result, ['tests'=>true]) + array_intersect_key($selected, ['source'=>true]);
        } elseif (isset($data['legacy_solution']) && $task) {
            abort_unless(\App\Solution::where(['user_id'=>$user->id, 'task_id'=>$task->id, 'course_id'=>$course_id])->whereKey($data['legacy_solution'])->exists(), 404);
            $event = app(\App\Services\CodeChecking\History::class)->event($user, $course_id, $task, $data['legacy_solution']);
            $mode = 'review';
            $feedback = (string)($event['comment'] ?? '');
            $sourceKnown = !empty($event['source_available']) && empty($event['original_reference']) && is_string($event['text'] ?? null);
            $report = ['comment'=>$feedback, 'points'=>$event['evaluated_points'] ?? null, 'maximum'=>(int)$task->max_mark];
            $basis = ['feedback'=>$feedback !== '' ? [$feedback] : [], 'source'=>['source_status'=>$sourceKnown ? 'ready' : 'unavailable',
                'text'=>$sourceKnown ? $event['text'] : '', 'files'=>[]]];
        } elseif ($task) {
            $mode = $data['intent'] ?? ($work['latest_submission'] === null ? 'hint' : 'submission');
            abort_if($mode === 'submission' && $work['latest_submission'] === null, 422, 'У этой задачи пока нет твоей сдачи.');
            $result = $work['latest_submission']['result'] ?? [];
            // Students can also raise a grading question while asking for a hint.
            if ($mode === 'hint' && ($result['state'] ?? null) === 'completed'
                && $this->asksAboutGrade($data['question'])) {
                $mode = 'submission';
            }
            if ($mode === 'submission' && ($result['state'] ?? null) === 'completed') {
                $basis = ['feedback'=>$this->attempts->feedback($result)];
                $target = $latestTarget;
            }
        }
        if ($basis !== null && isset($result['assignment'])) $basis['assignment'] = $result['assignment'];
        if (isset($work['latest_submission']['result']['assignment'])) unset($work['latest_submission']['result']['assignment']);
        $assignment = $task ? $task->name."\n".$task->text : '';
        if ($task?->is_quiz && $task->quizAnswerMode() !== 'short') {
            $assignment .= "\n".implode("\n", array_column($task->quiz_choices['options'] ?? [], 'label'));
        }
        abort_if(strlen($assignment) > Context::MAX_BYTES, 413, Context::TOO_LARGE);
        if (!$compact && $report) $readingQuery .= ' '.$report['comment'];
        $materials = $this->context->materials($course, $step, $user, $readingQuery, $assignment, $data['focus'] ?? null, $compact);
        $sources = $materials['sources'];
        unset($materials['sources']);
        $available = array_column($sources, 'id');
        $contextKey = implode(':', [$user->id, $course_id, $id, $task?->id ?? 0, $mode, $data['run_id'] ?? '',
            md5($assignment), hash('sha256', json_encode([$materials['lesson'], $materials['neighbors'], $work, $target, $basis],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))]);
        $thread = 'tutor:thread:'.sha1($contextKey);
        $key = 'tutor:request:'.$user->id.':'.$data['request_id'];
        $lock = Cache::lock($thread.':lock', 80);
        abort_unless($lock->get(), 409, 'Дождитесь ответа на предыдущий вопрос.');
        try {
            if ($previous = Cache::get($key)) {
                abort_unless(hash_equals($previous['digest'], $digest), 409);
                abort_unless(isset($previous['reply']) && !array_diff($previous['sources'], $available), 409, 'Обновите вопрос.');
                return $previous['reply'];
            }
            $history = $chatHistory === null
                ? array_filter(Cache::get($thread, []), fn ($turn) => !array_diff($turn['sources'], $available))
                : [];
            $payload = ['mode' => $mode, 'question' => $data['question'], 'topic' => $step->name,
                'assignment' => $assignment, 'excerpt' => $data['excerpt'] ?? '',
                'review' => $report, 'history' => $chatHistory ?? array_column($history, 'exchange')] + $materials + $work;
            if ($basis !== null) $payload['review_basis'] = $basis;
            if ($comparison) $payload['recheck_result'] = array_diff_key($comparison, ['target'=>true]);
            if ($followup) {
                $payload['followup'] = true;
                $payload['dialogue_focus'] = Dialogue::focus($chatHistory, $last);
            }
            if (isset($data['layout'])) $payload['chat_layout'] = $data['layout'];
            if ($compact) {
                unset($payload['previous_results']);
            }
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            abort_if(strlen($body) > Context::MAX_BYTES, 413, Context::TOO_LARGE);
            $usageId = $this->meter->begin((int) $user->id, $data['request_id']);
            Cache::put($key, ['digest' => $digest], 600);
            try {
                $answer = $this->responder->reply($payload, fn ($usage) => $this->meter->checkpoint($usageId, $usage),
                    fn ($tokens, $previous) => $this->meter->reserve($usageId, $tokens, $previous), (int) $user->id);
            } catch (\Throwable $error) {
                $this->meter->finish($usageId, null);
                throw $error;
            }
            $accounted = $this->meter->finish($usageId, $answer['usage'] ?? null);
            if (!empty($answer['quota_limited'])) abort(429, $answer['quota_limited']);
            abort_unless($accounted && is_string($answer['answer'] ?? null) && is_array($answer['sources'] ?? null), 502);
            $links = [];
            foreach (array_unique($answer['sources']) as $ref) {
                if (!is_string($ref) || !isset($sources[$ref])) continue;
                $part = $sources[$ref];
                $links[$part['id']] = ['title' => $part['title'], 'heading' => $part['heading'], 'preview' => $mode === 'theory' ? mb_substr($part['text'], 0, 180) : null,
                    'url' => url('/insider/courses/'.$course_id.'/steps/'.$part['step']).'#'.$part['anchor']];
            }
            $reply = ['answer' => $answer['answer'], 'links' => array_values($links),
                'good_question' => !$ownTeachingTask && ($answer['guided'] ?? true) === false && ($answer['good_question'] ?? false) === true,
                'reference' => $answer['reference'] ?? null, 'guided' => (bool) ($answer['guided'] ?? false)];
            $discussed = isset($data['legacy_solution']) ? ['legacy_solution'=>$data['legacy_solution']]
                : ($target && !empty($target['run']) ? ['submission_id'=>$target['submission'], 'run_id'=>$target['run']] : []);
            if (!$discussed && $latestLegacy && $mode === 'submission') $discussed = ['legacy_solution'=>$latestLegacy];
            if ($discussed) $reply['discussed'] = $discussed;
            if (!empty($answer['next_step'])) $reply['next_step'] = $answer['next_step'];
            $reply['recheck'] = null;
            $finding = $answer['recheck'] ?? null;
            $source = $basis['source'] ?? $work['latest_submission'] ?? [];
            if (!$comparison && $basis && ($answer['guided'] ?? null) === false && ($location = $this->locateFinding($finding, $basis, $source)) !== null) {
                $status = $target ? app(Submissions::class)->tutorRecheck($user, $course_id, $task, $target, $finding) : 'teacher';
                $reply['recheck'] = ['claim'=>$finding['claim'], 'evidence'=>$finding['evidence'], 'focus'=>$finding['focus'], 'location'=>$location, 'status'=>$status];
            }
            $refs = array_keys($links);
            if ($chatHistory === null) {
                $history[] = ['sources' => $refs, 'exchange' => ['question' => $data['question'], 'answer' => mb_substr($answer['answer'], 0, 750)]];
                Cache::put($thread, array_slice($history, -3), 3600);
            }
            Cache::put($key, ['digest' => $digest, 'sources' => $refs, 'reply' => $reply], 600);
            return $reply;
        } finally {
            $lock->release();
        }
    }

    private function asksAboutGrade(string $question): bool
    {
        $grade = '(?:оценк|проверк|замечани|балл|критери|результат)';
        $dispute = '(?:не\s+соглас(?:ен|на|ны)\b|оспор|оспар|пересмотр|перепровер|ошиб)';
        return (bool) preg_match("/$dispute.{0,80}$grade|$grade.{0,80}$dispute|(?:сняли|снизили)\s+(?:балл|оценк)/isu", $question);
    }

    private function locateFinding(mixed $finding, array $basis, array $source): ?array
    {
        if (!is_array($finding) || ($source['source_status'] ?? '') !== 'ready') return null;
        foreach (['claim'=>260, 'evidence'=>200, 'focus'=>350] as $key=>$limit) {
            $value = $finding[$key] ?? null;
            if (!is_string($value) || mb_strlen(trim($value)) < 3 || mb_strlen($value) > $limit || str_contains($value, "\n")) return null;
        }
        if (!collect($basis['feedback'])->contains(fn ($line) => str_contains($line, $finding['claim']))) return null;
        $files = collect($source['files'])->where('kind','text')->prepend(['name'=>null, 'text'=>$source['text']]);
        foreach ($files as $file) {
            $offset = strpos($file['text'], $finding['evidence']);
            if ($offset !== false) return ['file'=>$file['name'], 'line'=>1 + substr_count(substr($file['text'], 0, $offset), "\n")];
        }
        return null;
    }
}
