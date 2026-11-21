<?php

namespace App\Jobs;

use App\Course;
use App\Task;
use App\User;
use App\Services\TaskLocation;
use App\Services\TelegramBot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotifyTaskQuestionTeacher implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;
    public array $backoff = [15, 60, 180, 300];

    public function __construct(public int $messageId, public int $teacherId)
    {
        $this->onQueue('notifications');
    }

    public function handle(TelegramBot $bot, TaskLocation $links): void
    {
        $message = DB::table('task_chat_messages')->find($this->messageId);
        if (!$message || $message->author_id != $message->student_id) return;
        $course = Course::find($message->course_id);
        $teacher = $course?->teachers()->where('users.id', $this->teacherId)->first();
        if (!$teacher || (int)$teacher->telegram_chat_id <= 0) return;

        $task = Task::find($message->task_id);
        $student = User::find($message->student_id);
        if (!$task || !$student) return;
        $attempt = $message->submission_id ?: ($message->solution_id ? 'solution-'.$message->solution_id : null);
        $url = $links->task($course->id, $task, $student->id, $attempt);
        $text = "{$student->name} задаёт вопрос по задаче «{$task->name}» в курсе «{$course->name}».\n\n"
            .Str::limit($message->body, 600)."\n\nОткрыть чат ученика\n".$url;
        $result = $bot->deliver((string)$teacher->telegram_chat_id, $text);
        if ($result['ok']) return;
        if (($result['error_code'] ?? 0) === 429) {
            $this->release(max(1, (int)($result['retry_after'] ?? 30)));
        } elseif (!in_array($result['error_code'] ?? 0, [400, 403], true)) {
            throw new \RuntimeException('Telegram не подтвердил отправку уведомления.');
        }
    }
}
