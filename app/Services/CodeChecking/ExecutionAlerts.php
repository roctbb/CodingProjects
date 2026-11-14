<?php

namespace App\Services\CodeChecking;

use App\CodeExecutionRun;
use App\CodeSubmission;
use App\Course;
use App\Jobs\SendCodeExecutionAlert;
use App\Services\CourseTelegram;
use App\Services\TaskLocation;
use App\Services\TelegramBot;
use App\Services\TelegramSettings;
use App\Task;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Teacher-only alerts. The outbox and queue follow the deadline notification delivery policy. */
class ExecutionAlerts
{
    public function technicalError(CodeExecutionRun $run): void
    {
        if ($run->state === 'error' && $run->finished_at) $this->enqueue($run, 'technical_error');
    }

    public function plagiarism(CodeExecutionRun $run): void
    {
        if (!Schema::hasTable('code_execution_alerts')) return;
        $submission = $run->submission;
        if ($submission && $this->isStudentWork($submission) && $this->critical($submission)) {
            $this->enqueue($run, 'plagiarism');
        }
    }

    private function enqueue(CodeExecutionRun $run, string $kind): void
    {
        if (!Schema::hasTable('code_execution_alerts') || !filled(app(TelegramSettings::class)->all()['bot_token'] ?? null)) return;
        $submission = $run->submission;
        $course = $submission ? Course::find($submission->course_id) : null;
        if (!$submission || !$course || !$this->taskInCourse($submission, $course)) return;
        $now = now()->utc();
        $botKey = app(CourseTelegram::class)->botKey();
        foreach ($course->teachers as $teacher) {
            if ((int) $teacher->telegram_chat_id <= 0) continue;
            DB::table('code_execution_alerts')->insertOrIgnore([
                'execution_run_id' => $run->id, 'submission_id' => $submission->id, 'course_id' => $course->id,
                'recipient_user_id' => $teacher->id, 'kind' => $kind, 'bot_key' => $botKey,
                'chat_id' => (string) $teacher->telegram_chat_id, 'status' => 'pending',
                'available_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        // Rows survive a queue-publish failure. The scheduler recovers pending or lost queued jobs.
        $this->dispatchDue($run->id);
    }

    public function dispatchDue(?string $runId = null): int
    {
        if (!Schema::hasTable('code_execution_alerts')) return 0;
        $now = now()->utc();
        DB::table('code_execution_alerts')->where('status', 'queued')->where('reserved_at', '<', $now->copy()->subMinutes(10))
            ->update(['status' => 'pending', 'updated_at' => $now]);
        // A worker could have delivered before crashing. Do not blindly send the same alert again.
        DB::table('code_execution_alerts')->where('status', 'sending')->where('reserved_at', '<', $now->copy()->subMinutes(10))
            ->update(['status' => 'uncertain', 'last_error' => 'worker_interrupted', 'updated_at' => $now]);
        $ids = DB::table('code_execution_alerts')->whereIn('status', ['pending', 'retry'])->where('available_at', '<=', $now)
            ->when($runId, fn ($query) => $query->where('execution_run_id', $runId))->orderBy('id')->limit(500)->pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            $claimed = DB::table('code_execution_alerts')->where('id', $id)->whereIn('status', ['pending', 'retry'])
                ->where('available_at', '<=', $now)->update(['status' => 'queued', 'reserved_at' => $now, 'updated_at' => $now]);
            if ($claimed) {
                SendCodeExecutionAlert::dispatch((int) $id)->afterCommit();
                $count++;
            }
        }
        return $count;
    }

    public function send(int $id): void
    {
        $claimed = DB::table('code_execution_alerts')->where('id', $id)->where('status', 'queued')
            ->update(['status' => 'sending', 'reserved_at' => now()->utc(), 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()->utc()]);
        if (!$claimed) return;
        $delivery = DB::table('code_execution_alerts')->find($id);
        $run = CodeExecutionRun::find($delivery->execution_run_id);
        $submission = $run?->submission;
        $course = Course::find($delivery->course_id);
        $teacher = User::find($delivery->recipient_user_id);
        $valid = $run && $submission && $course && $teacher
            && $submission->id === $delivery->submission_id && (int) $submission->course_id === (int) $course->id
            && (string) $teacher->telegram_chat_id === $delivery->chat_id && (int) $teacher->telegram_chat_id > 0
            && $course->teachers()->where('users.id', $teacher->id)->exists()
            && hash_equals($delivery->bot_key, app(CourseTelegram::class)->botKey())
            && $this->taskInCourse($submission, $course);
        if ($valid && $delivery->kind === 'technical_error') {
            $valid = $run->state === 'error' && $run->finished_at && $submission->current_run_id === $run->id;
        }
        elseif ($valid && $delivery->kind === 'plagiarism') $valid = $this->isStudentWork($submission) && $this->critical($submission);
        else $valid = false;
        if (!$valid) { $this->finish($id, 'cancelled'); return; }

        $author = User::find($submission->user_id);
        $task = Task::find($submission->task_id);
        if (!$author || !$task) { $this->finish($id, 'cancelled'); return; }
        $maximum = $delivery->kind === 'plagiarism' ? $this->maximum($submission) : null;
        $similarity = $maximum === null ? 'Обнаружено высокое сходство кода.' : 'Обнаружено совпадение до '.$maximum.'%.';
        $text = $delivery->kind === 'technical_error'
            ? "⚙️ Техническая ошибка проверки\nСервис проверки не смог завершить запуск. Требуется повторная проверка; оценка не снижена.\n"
            : "🔎 Высокое сходство кода\n".$similarity." Проверьте обстоятельства перед выводами; оценка автоматически не меняется.\n";
        $text .= 'Автор: '.$this->label($author->name)."\nЗадача: ".$this->label($task->name)
            ."\nКурс: ".$this->label($course->name)."\n".app(TaskLocation::class)->submission($submission);
        $result = app(TelegramBot::class)->deliver($delivery->chat_id, $text);
        if ($result['ok'] ?? false) {
            $this->finish($id, 'sent', ['message_id' => (string) ($result['result']['message_id'] ?? ''), 'sent_at' => now()->utc()]);
        } elseif (!empty($result['uncertain'])) {
            $this->finish($id, 'uncertain', ['last_error' => 'transport_result_unknown']);
        } else {
            $code = (int) ($result['error_code'] ?? 500);
            if (($code === 429 || $code >= 500) && $delivery->attempts < 8) {
                $seconds = max(5, (int) ($result['retry_after'] ?? 0), min(3600, 15 * 2 ** ($delivery->attempts - 1)));
                $this->finish($id, 'retry', ['last_error' => 'telegram_'.$code, 'available_at' => now()->utc()->addSeconds($seconds)]);
            } else $this->finish($id, 'failed', ['last_error' => 'telegram_'.$code]);
        }
    }

    private function isStudentWork(CodeSubmission $submission): bool
    {
        return CodeSubmission::forCourseStudents((int) $submission->course_id)->whereKey($submission->id)->exists()
            && User::whereKey($submission->user_id)->where('role', '!=', 'admin')->exists();
    }

    private function critical(CodeSubmission $submission): bool
    {
        $payload = app(Integrity::class)->legacyPayload($submission);
        return ($payload['similarity']['checked'] ?? false) && ($payload['similarity']['critical'] ?? false);
    }

    private function maximum(CodeSubmission $submission): ?int
    {
        $maximum = app(Integrity::class)->legacyPayload($submission)['similarity']['max_percent'] ?? null;
        return $maximum === null ? null : max(0, min(100, (int) $maximum));
    }

    private function taskInCourse(CodeSubmission $submission, Course $course): bool
    {
        return Task::whereKey($submission->task_id)->whereHas('step.lesson', fn ($query) => $query->where('program_id', $course->program_id))->exists();
    }

    private function label(?string $text): string
    {
        return mb_substr(trim(preg_replace('/[\p{C}\s]+/u', ' ', strip_tags($text ?? ''))), 0, 180);
    }

    private function finish(int $id, string $status, array $values = []): void
    {
        DB::table('code_execution_alerts')->where('id', $id)->update($values + ['status' => $status, 'updated_at' => now()->utc()]);
    }
}
