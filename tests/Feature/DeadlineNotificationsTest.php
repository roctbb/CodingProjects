<?php
namespace Tests\Feature;

use App\Course;
use App\Services\CourseTelegram;
use App\Services\DeadlineNotifications;
use App\Services\DeadlineNotificationSender;
use App\Services\TelegramBindService;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DeadlineFeatureCase;

class DeadlineNotificationsTest extends DeadlineFeatureCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-15 00:00:00', 'Europe/Moscow'));
    }

    private function teacher(): User
    {
        $user=User::find(2); $user->forceFill(['telegram_chat_id'=>'202','telegram_user_id'=>'202'])->save(); return $user;
    }
    private function chat(string $status='active'): int
    {
        return DB::table('course_telegram_chats')->insertGetId(['course_id'=>1,'bot_key'=>app(CourseTelegram::class)->botKey(),
            'chat_id'=>'-1009','thread_id'=>17,'title'=>'Класс Python','status'=>$status,'linked_by'=>2,'created_at'=>now(),'updated_at'=>now()]);
    }
    private function deliverAll(): void
    {
        foreach(DB::table('deadline_notification_deliveries')->where('status','queued')->pluck('id') as $id) app(DeadlineNotificationSender::class)->send($id);
    }
    public function test_one_time_group_token_checks_identity_course_rights_topic_and_website_confirmation(): void
    {
        $teacher=$this->teacher(); $link=app(CourseTelegram::class)->link(Course::find(1),$teacher);
        $update=['update_id'=>1,'message'=>['text'=>$link['command'],'chat'=>['id'=>-1009,'type'=>'supergroup','title'=>'Класс Python'],
            'from'=>['id'=>999],'message_thread_id'=>17]];
        $this->postJson('/telegram/webhook',$update)->assertForbidden();
        $headers=['X-Telegram-Bot-Api-Secret-Token'=>'test-webhook-secret'];
        $this->postJson('/telegram/webhook',$update,$headers)->assertOk();
        $this->assertDatabaseCount('course_telegram_chats',0);
        $update['message']['from']['id']=202;
        $this->postJson('/telegram/webhook',$update,$headers)->assertOk();
        $chat=DB::table('course_telegram_chats')->first();
        $this->assertSame('pending',$chat->status); $this->assertEquals(17,$chat->thread_id);
        $update['message']['chat']['id']=-2009;
        $this->postJson('/telegram/webhook',$update,$headers)->assertOk();
        $this->assertDatabaseCount('course_telegram_chats',1);
        $this->actingAs(User::find(1))->post('/insider/courses/1/notifications/chats/'.$chat->id,['action'=>'confirm'])->assertForbidden();
        $this->actingAs($teacher)->post('/insider/courses/1/notifications/chats/'.$chat->id,['action'=>'confirm'])->assertRedirect();
        $this->assertDatabaseHas('course_telegram_chats',['id'=>$chat->id,'status'=>'active']);
        Http::assertNothingSent();
    }
    public function test_expired_tokens_and_revoked_teacher_cannot_bind_and_group_cannot_bind_personal_profile(): void
    {
        $teacher=$this->teacher(); $link=app(CourseTelegram::class)->link(Course::find(1),$teacher);
        $message=['text'=>$link['command'],'chat'=>['id'=>-1009,'type'=>'supergroup','title'=>'Group'],'from'=>['id'=>202]];
        Carbon::setTestNow(now()->addMinutes(31));
        app(TelegramBindService::class)->handleUpdate(['message'=>$message]);
        $this->assertDatabaseCount('course_telegram_chats',0);
        $link=app(CourseTelegram::class)->link(Course::find(1),$teacher); $message['text']=$link['command'];
        DB::table('course_teachers')->delete();
        app(TelegramBindService::class)->handleUpdate(['message'=>$message]);
        $teacher->forceFill(['telegram_link_token'=>'personal','telegram_link_token_expires_at'=>now()->addMinutes(30)])->save();
        $message['text']='/start bind_personal';
        $this->assertFalse(app(TelegramBindService::class)->handleUpdate(['message'=>$message]));
        $this->assertSame('202',$teacher->fresh()->telegram_chat_id);
        $this->assertDatabaseCount('course_telegram_chats',0); Http::assertNothingSent();
    }
    public function test_delivery_is_idempotent_topics_and_opted_in_students_are_supported(): void
    {
        $this->teacher(); $this->policy(); $this->chat();
        User::find(1)->forceFill(['telegram_chat_id'=>'101'])->save();
        DB::table('course_notification_preferences')->insert(['course_id'=>1,'user_id'=>1,'enabled'=>true]);
        $this->assertSame(3,app(DeadlineNotifications::class)->dispatchDue());
        $this->assertSame(0,app(DeadlineNotifications::class)->dispatchDue());
        Queue::assertPushed(\App\Jobs\SendDeadlineNotification::class,3);
        Http::fake(['*'=>Http::response(['ok'=>true,'result'=>['message_id'=>123]])]);
        $this->deliverAll(); $this->deliverAll();
        $this->assertEquals(3,DB::table('deadline_notification_deliveries')->where('status','sent')->count());
        Http::assertSentCount(3);
        Http::assertSent(fn($r)=>$r['chat_id']==='-1009' && $r['message_thread_id']===17 && str_contains($r['text'],'70%')
            && str_contains($r['text'],'До 15.11.2026 23:59 (Europe/Moscow)'));
        foreach(DB::table('deadline_notification_deliveries')->get() as $delivery) $this->assertSame('123',$delivery->message_id);
    }
    public function test_shared_lesson_and_legacy_task_policies_aggregate_in_one_group_message(): void
    {
        $this->teacher(); $this->policy('task',11); $this->policy('task',12); $this->chat();
        app(DeadlineNotifications::class)->dispatchDue();
        $this->assertDatabaseCount('deadline_notification_deliveries',2);
        foreach(DB::table('deadline_notification_deliveries')->get() as $delivery) $this->assertCount(2,json_decode($delivery->event_ids,true));
        Http::fake(['*'=>Http::response(['ok'=>true,'result'=>['message_id'=>1]])]); $this->deliverAll(); Http::assertSentCount(2);
    }
    public function test_edit_membership_and_unlinked_chat_cancel_queued_messages(): void
    {
        $this->teacher(); $this->policy(); $chat=$this->chat(); app(DeadlineNotifications::class)->dispatchDue();
        $this->policy(extra:['mode'=>'none','stages'=>[]]); app(DeadlineNotifications::class)->reconcile();
        $this->deliverAll(); Http::assertNothingSent();
        $this->assertEquals(2,DB::table('deadline_notification_deliveries')->where('status','cancelled')->count());
        $this->policy(); app(DeadlineNotifications::class)->dispatchDue();
        DB::table('course_teachers')->delete(); DB::table('course_telegram_chats')->where('id',$chat)->update(['status'=>'disabled']);
        $this->deliverAll(); Http::assertNothingSent();
    }
    public function test_rate_limit_retry_obeys_delay_and_does_not_repeat_success(): void
    {
        $this->teacher(); $this->policy(); app(DeadlineNotifications::class)->dispatchDue();
        Http::fakeSequence()->push(['ok'=>false,'error_code'=>429,'parameters'=>['retry_after'=>120]],429)
            ->push(['ok'=>true,'result'=>['message_id'=>7]]);
        $this->deliverAll(); $this->assertSame('retry',DB::table('deadline_notification_deliveries')->value('status'));
        $this->assertSame(0,app(DeadlineNotifications::class)->dispatchDue());
        Carbon::setTestNow(now()->addSeconds(121)); app(DeadlineNotifications::class)->dispatchDue(); $this->deliverAll();
        $this->assertSame('sent',DB::table('deadline_notification_deliveries')->value('status'));
        $this->assertEquals(2,DB::table('deadline_notification_deliveries')->value('attempts')); Http::assertSentCount(2);
    }
    public function test_reached_event_is_due_at_boundary_and_stale_reminders_are_not_sent_after_outage(): void
    {
        $this->teacher(); $this->policy(); app(DeadlineNotifications::class)->reconcile();
        Carbon::setTestNow(Carbon::parse('2026-11-16 00:00:00','Europe/Moscow'));
        $this->assertSame(1,app(DeadlineNotifications::class)->dispatchDue());
        $delivery=DB::table('deadline_notification_deliveries')->first();
        $event=DB::table('deadline_notification_events')->find(json_decode($delivery->event_ids,true)[0]);
        $this->assertSame('reached',$event->kind);
        $this->assertDatabaseHas('deadline_notification_events',['kind'=>'reminder','status'=>'expired']);
    }
    public function test_teacher_receives_reached_notification_at_the_cutoff_once(): void
    {
        $this->teacher();$this->policy();app(DeadlineNotifications::class)->reconcile();
        $event=DB::table('deadline_notification_events')->where('kind','reached')->orderBy('scheduled_at')->first();
        $cutoff=Carbon::parse($event->scheduled_at,'UTC');
        Carbon::setTestNow($cutoff->copy()->subSecond());
        app(DeadlineNotifications::class)->dispatchDue();
        $reached=fn()=>DB::table('deadline_notification_deliveries')->where('recipient_type','teacher')->get()
            ->filter(fn($delivery)=>in_array($event->id,json_decode($delivery->event_ids,true),true));
        $this->assertCount(0,$reached());
        Carbon::setTestNow($cutoff);
        app(DeadlineNotifications::class)->dispatchDue();
        $this->assertCount(1,$reached());
        Http::fake(['*'=>Http::response(['ok'=>true,'result'=>['message_id'=>777]])]);
        $delivery=$reached()->first();app(DeadlineNotificationSender::class)->send($delivery->id);
        app(DeadlineNotifications::class)->dispatchDue();app(DeadlineNotificationSender::class)->send($delivery->id);
        Http::assertSentCount(1);
        Http::assertSent(fn($request)=>$request['chat_id']==='202' && str_contains($request['text'],'Наступил срок сдачи')
            && str_contains($request['text'],'До 15.11.2026 23:59 (Europe/Moscow)'));
        $this->assertDatabaseHas('deadline_notification_deliveries',['id'=>$delivery->id,'status'=>'sent','message_id'=>'777']);
    }
    public function test_hidden_work_never_leaks_to_group_or_student_and_pending_review_is_quiet(): void
    {
        $this->teacher(); $this->policy(); $this->chat();
        User::find(1)->forceFill(['telegram_chat_id'=>'101'])->save();
        DB::table('course_notification_preferences')->insert(['course_id'=>1,'user_id'=>1,'enabled'=>true]);
        DB::table('tasks')->where('id',11)->update(['is_hidden'=>true]);
        $this->assertSame(1,app(DeadlineNotifications::class)->dispatchDue());
        $this->assertSame('teacher',DB::table('deadline_notification_deliveries')->value('recipient_type'));
        DB::table('tasks')->where('id',11)->update(['is_hidden'=>false]);
        $this->solution(); $this->policy(); app(DeadlineNotifications::class)->dispatchDue();
        $this->assertEquals(0,DB::table('deadline_notification_deliveries')->where('recipient_type','student')->count());
    }
    public function test_settings_log_and_preferences_require_membership_and_webhook_requires_secret(): void
    {
        $this->actingAs(User::find(2))->get('/insider/courses/1/notifications')->assertOk()->assertSee('Чаты курса');
        $this->post('/insider/courses/1/notifications',['teachers_enabled'=>0,'chat_enabled'=>1])->assertRedirect();
        $this->assertDatabaseHas('course_notification_settings',['teachers_enabled'=>0,'chat_enabled'=>1]);
        $this->actingAs(User::find(1))->get('/insider/courses/1/notifications')->assertForbidden();
        $this->post('/insider/courses/1/notifications/preference',['enabled'=>1])->assertRedirect();
        $this->assertDatabaseHas('course_notification_preferences',['user_id'=>1,'enabled'=>1]);
        config(['services.telegram.webhook_secret'=>null]);
        $this->postJson('/telegram/webhook',['message'=>[]])->assertForbidden(); Http::assertNothingSent();
    }

    public function test_network_unknown_result_requires_manual_retry_and_forbidden_disables_group(): void
    {
        $this->teacher(); $this->policy(); $this->chat(); app(DeadlineNotifications::class)->dispatchDue();
        $calls=0;
        Http::fake(function () use (&$calls) {
            if (++$calls === 1) throw new \Illuminate\Http\Client\ConnectionException('do-not-log-bot-token');
            return Http::response(['ok'=>false,'error_code'=>403],403);
        });
        $private=DB::table('deadline_notification_deliveries')->where('recipient_type','teacher')->first();
        app(DeadlineNotificationSender::class)->send($private->id);
        $this->assertDatabaseHas('deadline_notification_deliveries',['id'=>$private->id,'status'=>'uncertain']);
        app(DeadlineNotifications::class)->dispatchDue();
        $this->assertDatabaseHas('deadline_notification_deliveries',['id'=>$private->id,'status'=>'uncertain']);
        $this->actingAs(User::find(1))->post('/insider/courses/1/notifications/deliveries/'.$private->id.'/retry')->assertForbidden();
        $chat=DB::table('deadline_notification_deliveries')->where('recipient_type','chat')->first();
        app(DeadlineNotificationSender::class)->send($chat->id);
        $this->assertDatabaseHas('course_telegram_chats',['status'=>'disabled']);
        $this->assertDatabaseHas('deadline_notification_deliveries',['id'=>$chat->id,'status'=>'failed']);
    }

    public function test_group_migration_retries_new_destination_and_removed_bot_stops_future_sends(): void
    {
        $this->teacher(); $this->policy(); $chat=$this->chat(); app(DeadlineNotifications::class)->dispatchDue();
        $delivery=DB::table('deadline_notification_deliveries')->where('recipient_type','chat')->first();
        Http::fakeSequence()->push(['ok'=>false,'error_code'=>400,'parameters'=>['migrate_to_chat_id'=>-10012345]],400)
            ->push(['ok'=>true,'result'=>['message_id'=>22]]);
        app(DeadlineNotificationSender::class)->send($delivery->id);
        $this->assertDatabaseHas('course_telegram_chats',['id'=>$chat,'chat_id'=>'-10012345']);
        Carbon::setTestNow(now()->addMinutes(1)); app(DeadlineNotifications::class)->dispatchDue();
        app(DeadlineNotificationSender::class)->send($delivery->id);
        $this->assertDatabaseHas('deadline_notification_deliveries',['id'=>$delivery->id,'status'=>'sent']);
        app(TelegramBindService::class)->handleUpdate(['my_chat_member'=>['chat'=>['id'=>-10012345],'new_chat_member'=>['status'=>'left']]]);
        $this->assertDatabaseHas('course_telegram_chats',['id'=>$chat,'status'=>'disabled']);
    }

    public function test_superseded_and_skipped_pending_solutions_do_not_hide_personal_reminders(): void
    {
        $this->policy();User::find(1)->forceFill(['telegram_chat_id'=>'101'])->save();
        DB::table('course_notification_preferences')->insert(['course_id'=>1,'user_id'=>1,'enabled'=>true]);
        $old=$this->solution('2026-11-14 08:00:00');$old->review_skipped=true;$old->save();
        $this->solution('2026-11-14 09:00:00',3);
        $this->assertEquals(0,\App\Solution::pendingReview()->count());
        app(DeadlineNotifications::class)->dispatchDue();
        $this->assertEquals(1,DB::table('deadline_notification_deliveries')->where('recipient_type','student')->count());
        Http::fake(['*'=>Http::response(['ok'=>true,'result'=>['message_id'=>1]])]);$this->deliverAll();Http::assertSentCount(1);
    }

    public function test_late_reminder_reports_actual_time_remaining_instead_of_twenty_four_hours(): void
    {
        $this->teacher();$this->policy();app(DeadlineNotifications::class)->reconcile();
        Carbon::setTestNow(Carbon::parse('2026-11-15 23:50:00','Europe/Moscow'));
        Http::fake(['*'=>Http::response(['ok'=>true,'result'=>['message_id'=>1]])]);
        app(DeadlineNotifications::class)->dispatchDue();$this->deliverAll();
        Http::assertSent(fn ($r)=>str_contains($r['text'],'До срока сдачи: 10 мин') && !str_contains($r['text'],'через сутки'));
    }
}
