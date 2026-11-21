<?php

namespace App\Jobs;

use App\Task;
use App\User;
use App\Services\Tutor\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AnswerTaskQuestion implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable, InteractsWithQueue;

    public $timeout = 75;
    public $tries = 80;
    public $maxExceptions = 2;
    public $failOnTimeout = true;

    public function __construct(public int $questionId)
    {
        if ($queue = config('tutor.queue')) $this->onQueue($queue);
    }

    public function uniqueId(): string
    {
        return (string)$this->questionId;
    }

    public function middleware(): array
    {
        $student = DB::table('task_chat_messages')->where('id',$this->questionId)->value('student_id');
        return [(new WithoutOverlapping("tutor-student-$student"))->releaseAfter(2)->expireAfter(85)];
    }

    public function handle(Conversation $conversation): void
    {
        $question = DB::table('task_chat_messages')->find($this->questionId);
        if (!$question || $question->automatic || (int)$question->author_id !== (int)$question->student_id
            || DB::table('task_chat_messages')->where('answer_to',$question->id)->exists()) return;
        $student = User::find($question->student_id);
        $task = Task::find($question->task_id);
        if (!$student || !$task || $task->is_quiz) return;

        $input = ['task_id'=>(int)$task->id, 'question'=>$question->body, 'request_id'=>$question->client_id];
        if ($question->solution_id) $input['legacy_solution']=(int)$question->solution_id;
        if ($question->submission_id) {
            $run = DB::table('code_execution_runs')->where('submission_id',$question->submission_id);
            $run = $question->review_run_id ? $run->find($question->review_run_id) :
                $run->where('id',DB::table('code_submissions')->where('id',$question->submission_id)->value('current_run_id'))->first();
            if (!$run || $run->state !== 'completed') {
                $this->save($question,['answer'=>'У этой посылки пока нет завершённой проверки. Когда появится результат, его можно будет разобрать здесь.']);
                return;
            }
            $input += ['submission_id'=>$question->submission_id, 'run_id'=>$run->id];
        }
        try {
            $reply = $conversation->ask($student, $question->course_id, $task->step_id, $input);
        } catch (HttpExceptionInterface $error) {
            $reply = ['answer'=>match ($error->getStatusCode()) {
                429 => 'Сейчас я достиг лимита. Твой вопрос сохранён, преподаватель его видит.',
                413 => 'Материал слишком велик для одного разбора. Преподаватель видит твой вопрос.',
                default => 'Сейчас я не могу разобрать этот вопрос. Он сохранён в чате для преподавателя.',
            }];
        }
        $this->save($question, $reply);
    }

    public function failed(?\Throwable $error): void
    {
        if ($question = DB::table('task_chat_messages')->find($this->questionId)) {
            DB::table('student_tutor_usage')->where(['user_id'=>$question->student_id, 'request_id'=>$question->client_id,
                'state'=>'pending'])->update(['state'=>'unknown','updated_at'=>now()]);
            $this->save($question,['answer'=>'Подготовить ответ не удалось. Вопрос сохранён в чате.']);
        }
    }

    private function save(object $question, array $reply): void
    {
        DB::transaction(function () use ($question, $reply) {
            $text = $reply['answer'];
            $award = ($reply['good_question'] ?? false) === true && random_int(1, 100) <= config('tutor.question_coin_chance')
                && \App\Services\Tutor\Budget::canAwardCoin($question->student_id);
            unset($reply['answer'], $reply['good_question']);
            if ($award) $reply['coins'] = 1;
            $inserted = DB::table('task_chat_messages')->insertOrIgnore([
                'course_id'=>$question->course_id, 'task_id'=>$question->task_id, 'student_id'=>$question->student_id,
                'author_id'=>$question->student_id, 'automatic'=>true, 'answer_to'=>$question->id,
                'submission_id'=>$question->submission_id, 'solution_id'=>$question->solution_id,
                'body'=>$text, 'tutor_details'=>json_encode($reply, JSON_THROW_ON_ERROR),
                'client_id'=>(string)Str::uuid(),'created_at'=>now()->format('Y-m-d H:i:s.u'),
            ]);
            if ($inserted && $award) {
                \App\CoinTransaction::registerOnce($question->student_id, 1, 'Tutor question #'.$question->id);
            }
        });
    }
}
