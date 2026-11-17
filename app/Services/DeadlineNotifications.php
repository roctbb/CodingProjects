<?php
namespace App\Services;

use App\Course;
use App\DeadlinePolicy;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Durable schedule/outbox. All notification timestamps are UTC. */
class DeadlineNotifications
{
    public function policyChanged(DeadlinePolicy $policy, bool $created): void
    {
        if (!Schema::hasTable('deadline_notification_events') || $policy->mode !== 'custom') return;
        $stage = $policy->stages->first();
        if (!$stage) return;
        $now = now()->utc();
        $kind = $created ? 'created' : 'updated';
        $id = DB::table('deadline_notification_events')->insertGetId([
            'event_key' => hash('sha256', "$policy->id:$policy->revision:$kind"),
            'course_id' => $policy->course_id, 'policy_id' => $policy->id, 'revision' => $policy->revision,
            'stage_id' => $stage->id, 'kind' => $kind, 'scheduled_at' => $now, 'expires_at' => $now->copy()->addDay(),
            'status' => 'pending', 'created_at' => $now, 'updated_at' => $now,
        ]);
        // Keep the event in the same transaction as the deadline, and publish only after the outermost commit.
        DB::afterCommit(fn () => $this->dispatchEvents($id));
    }

    public function reconcile(?int $courseId = null): void
    {
        if (!Schema::hasTable('deadline_notification_events')) return;
        DB::table('deadline_notification_events')->whereIn('status', ['pending', 'ready'])->whereNotExists(function ($q) {
            $q->selectRaw('1')->from('deadline_policies as p')->whereColumn('p.id', 'deadline_notification_events.policy_id')
                ->whereColumn('p.revision', 'deadline_notification_events.revision')->where('p.mode', 'custom');
        })->update(['status' => 'cancelled', 'updated_at' => now()->utc()]);
        DeadlinePolicy::with('stages')->where('mode', 'custom')->when($courseId, fn ($q) => $q->where('course_id', $courseId))->chunkById(100, function ($policies) {
            foreach ($policies as $policy) foreach ($policy->stages as $stage) {
                $cutoff = Carbon::parse($stage->cutoff_at, 'UTC');
                foreach (['reminder' => $cutoff->copy()->subDay(), 'reached' => $cutoff] as $kind => $at) {
                    // Do not replay historical notifications on migration or a retroactive edit.
                    if ($at->lt($policy->updated_at)) continue;
                    DB::table('deadline_notification_events')->insertOrIgnore([
                        'event_key' => hash('sha256', "$policy->id:$policy->revision:$stage->id:$kind"),
                        'course_id' => $policy->course_id, 'policy_id' => $policy->id, 'revision' => $policy->revision,
                        'stage_id' => $stage->id, 'kind' => $kind, 'scheduled_at' => $at,
                        'expires_at' => $kind === 'reminder' ? $cutoff : $cutoff->copy()->addDay(),
                        'status' => 'pending', 'created_at' => now()->utc(), 'updated_at' => now()->utc(),
                    ]);
                }
            }
        });
    }

    public function dispatchDue(): int
    {
        $this->reconcile();
        return $this->dispatchEvents();
    }

    private function dispatchEvents(?int $eventId = null): int
    {
        if (!Schema::hasTable('deadline_notification_events')) return 0;
        app(CourseDeadlineService::class)->forget();
        DB::transaction(function () use ($eventId) {
            $now = now()->utc();
            DB::table('deadline_notification_events')->where('status', 'pending')->where('expires_at', '<=', $now)->update(['status' => 'expired']);
            $events = DB::table('deadline_notification_events')->where('status', 'pending')->where('scheduled_at', '<=', $now)
                ->when($eventId !== null, fn ($q) => $q->where('id', $eventId))
                ->orderBy('id')->lockForUpdate()->get();
            $groups = [];
            foreach ($events as $event) {
                $course = Course::find($event->course_id);
                $policy = DeadlinePolicy::find($event->policy_id);
                $tasks = $course ? $this->tasks($event) : collect();
                if (!$course || $course->state === 'draft' || !$policy || $tasks->isEmpty()) {
                    DB::table('deadline_notification_events')->where('id', $event->id)->update(['status' => 'cancelled']); continue;
                }
                $settings = DB::table('course_notification_settings')->where('course_id', $course->id)->first();
                $recipients = [];
                $changed = in_array($event->kind, ['created', 'updated'], true);
                if (!$changed && ($settings?->teachers_enabled ?? true)) foreach ($course->teachers as $teacher) {
                    if ((int) $teacher->telegram_chat_id > 0) $recipients[] = ['teacher', $teacher->id, $teacher->telegram_chat_id, 0];
                }
                if ($settings?->chat_enabled ?? true) foreach (DB::table('course_telegram_chats')->where('course_id', $course->id)->where('bot_key', app(CourseTelegram::class)->botKey())->where('status', 'active')->get() as $chat) {
                    if ($this->tasks($event, null, true)->isNotEmpty()) $recipients[] = ['chat', $chat->id, $chat->chat_id, $chat->thread_id];
                }
                $subscribers = $changed ? collect() : DB::table('course_notification_preferences')->where('course_id', $course->id)->where('enabled', true)->pluck('user_id');
                foreach ($course->students()->whereIn('users.id', $subscribers)->get() as $student) {
                    if ((int) $student->telegram_chat_id > 0 && $this->tasks($event, $student)->isNotEmpty()) $recipients[] = ['student', $student->id, $student->telegram_chat_id, 0];
                }
                $stage = DB::table('deadline_stages')->find($event->stage_id);
                foreach ($recipients as [$type, $id, $chatId, $thread]) {
                    $scope = $changed ? 'policy:'.$policy->id : $tasks->first()->step->lesson_id;
                    $group = implode(':', [$course->id, (int) ($policy->audience_id ?? 0), $scope, $event->kind, $event->scheduled_at, $stage->retained_percent, $type, $id]);
                    $groups[$group] ??= ['course_id' => $course->id, 'recipient_type' => $type, 'recipient_id' => $id, 'chat_id' => (string) $chatId, 'thread_id' => $thread, 'event_ids' => []];
                    $groups[$group]['event_ids'][] = $event->id;
                }
                DB::table('deadline_notification_events')->where('id', $event->id)->update(['status' => 'ready', 'updated_at' => $now]);
            }
            foreach ($groups as $group => $delivery) {
                sort($delivery['event_ids']);
                $delivery['delivery_key'] = hash('sha256', $group.':'.implode(',', $delivery['event_ids']));
                $delivery['event_ids'] = json_encode($delivery['event_ids']);
                DB::table('deadline_notification_deliveries')->insertOrIgnore($delivery + ['bot_key' => app(CourseTelegram::class)->botKey(),
                    'status' => 'pending', 'available_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            }
        });
        // Recover jobs lost between committing the outbox and publishing to the queue.
        DB::table('deadline_notification_deliveries')->where('status', 'queued')->where('reserved_at', '<', now()->utc()->subMinutes(10))->update(['status' => 'pending']);
        // A crashed sender may already have delivered the HTTP request; require a deliberate retry.
        DB::table('deadline_notification_deliveries')->where('status', 'sending')->where('reserved_at', '<', now()->utc()->subMinutes(10))
            ->update(['status' => 'uncertain', 'last_error' => 'worker_interrupted']);
        $count = 0;
        foreach (DB::table('deadline_notification_deliveries')->whereIn('status', ['pending', 'retry'])->where('available_at', '<=', now()->utc())->pluck('id') as $id) {
            $claimed = DB::table('deadline_notification_deliveries')->where('id', $id)->whereIn('status', ['pending', 'retry'])->update(['status' => 'queued', 'reserved_at' => now()->utc()]);
            if ($claimed) { \App\Jobs\SendDeadlineNotification::dispatch($id)->onQueue('notifications')->afterCommit(); $count++; }
        }
        return $count;
    }

    public function tasks(object $event, ?User $student = null, bool $public = false)
    {
        $policy = DeadlinePolicy::find($event->policy_id);
        $course = Course::find($event->course_id);
        if (!$course || !$policy || $policy->mode !== 'custom' || (int) $policy->revision !== (int) $event->revision ||
            in_array($event->status, ['cancelled', 'expired'], true) || Carbon::parse($event->expires_at, 'UTC')->lte(now())) return collect();
        if (($policy->audience_id ?? 0) && !$course->subgroups()->whereKey($policy->audience_id)->exists()) return collect();
        $query = Task::with(['step.lesson.info', 'step.lesson.earlyAccesses', 'deadlines']);
        if ($policy->target_type === 'task') $query->whereKey($policy->target_id);
        else $query->where('is_star', false)->whereHas('step', fn ($q) => $q->where('lesson_id', $policy->target_id));
        if ($student) $query->with(['solutions' => fn ($q) => $q->where('course_id', $course->id)->where('user_id', $student->id)]);
        return $query->get()->filter(function ($task) use ($policy, $course, $student, $public) {
            if ((int) $task->step?->lesson?->program_id !== (int) $course->program_id) return false;
            $service = app(CourseDeadlineService::class);
            $audience = $student ? $service->audience($course->id, $student->id) : (int) ($policy->audience_id ?? 0);
            $rule = $service->forTask($task, $course->id, $audience);
            if (!($rule instanceof \App\Support\DeadlineRule) || $rule->policyId !== $policy->id) return false;
            if ($public && ($task->is_hidden || !$task->step->lesson->isStartedForAudience($course, $audience))) return false;
            if (!$student) return true;
            if (!$task->isVisible($student, $course) || !$task->step->lesson->isAvailableForUser($course, $student)) return false;
            // Do not nag about completed work or a submission awaiting review.
            return !$task->isFullDone($student->id) && !\App\Solution::where('course_id', $course->id)
                ->where('user_id', $student->id)->where('task_id', $task->id)->pendingReview()->exists();
        })->values();
    }
}
