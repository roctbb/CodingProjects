<?php

namespace Tests\Feature;

use App\Course;
use App\Services\CourseDeadlineService;
use App\Services\CourseSubgroups;
use App\Services\CourseTelegram;
use App\Services\DeadlineNotifications;
use App\Services\DeadlineNotificationSender;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\DeadlineFeatureCase;

class CourseSubgroupTelegramTest extends DeadlineFeatureCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('CREATE UNIQUE INDEX subgroup_tg_course_points ON course_student_points(course_id,student_id)');
        DB::statement('CREATE UNIQUE INDEX subgroup_tg_lesson_points ON lesson_student_stats(course_id,lesson_id,student_id)');
        (require database_path('migrations/2026_11_17_120000_create_course_subgroups.php'))->up();
        app()->forgetInstance(CourseSubgroups::class);
        app()->forgetInstance(CourseDeadlineService::class);
        $course=Course::find(1);
        $course->subgroups()->create(['id'=>1,'name'=>'Подгруппа А']);
        $course->subgroups()->create(['id'=>2,'name'=>'Подгруппа Б']);
        app(CourseSubgroups::class)->assign($course,[1,3],1,2);
        app(CourseSubgroups::class)->assign($course,[4],2,2);
        $this->actingAs(User::find(2));
        Http::fake(['*'=>Http::response(['ok'=>true,'result'=>['message_id'=>123]])]);
    }

    private function chat(): void
    {
        DB::table('course_telegram_chats')->insert(['course_id'=>1,'bot_key'=>app(CourseTelegram::class)->botKey(),
            'chat_id'=>'-1009','thread_id'=>17,'title'=>'Класс Python','status'=>'active','linked_by'=>2,'created_at'=>now(),'updated_at'=>now()]);
    }

    private function scopedPolicy(int $audience, string $date='2026-11-15')
    {
        $service=app(CourseDeadlineService::class);
        $editor=$service->editor(Course::find(1),'task',11,null,$audience);
        return $service->savePolicy(Course::find(1),'task',11,['revision'=>$editor['revision'],'mode'=>'custom','timezone'=>'Europe/Moscow',
            'stages'=>[['deadline_date'=>$date,'retained_percent'=>50]]],2,false,$audience);
    }

    private function deliver(): void
    {
        foreach(DB::table('deadline_notification_deliveries')->where('status','queued')->pluck('id') as $id) app(DeadlineNotificationSender::class)->send($id);
    }

    public function test_creation_and_change_messages_name_the_subgroup_and_link_to_its_deadlines(): void
    {
        $this->chat();
        $this->scopedPolicy(1,'2026-11-18');$this->scopedPolicy(2,'2026-11-19');
        $this->deliver();
        Http::assertSentCount(2);
        Http::assertSent(fn($r)=>str_contains($r['text'],'Установлены сроки')&&str_contains($r['text'],'Подгруппа А')
            &&!str_contains($r['text'],'Подгруппа Б')&&str_contains($r['text'],'18.11.2026 23:59')&&str_contains($r['text'],'/deadlines?subgroup=1'));
        Http::assertSent(fn($r)=>str_contains($r['text'],'Подгруппа Б')&&str_contains($r['text'],'19.11.2026 23:59')&&str_contains($r['text'],'/deadlines?subgroup=2'));
        $this->scopedPolicy(1,'2026-11-20');$this->deliver();
        Http::assertSentCount(3);
        Http::assertSent(fn($r)=>str_contains($r['text'],'Изменены сроки')&&str_contains($r['text'],'Подгруппа А')&&str_contains($r['text'],'20.11.2026 23:59'));
    }

    public function test_simultaneous_group_reminders_and_cutoffs_never_merge_and_personal_messages_use_own_group(): void
    {
        $this->scopedPolicy(1);$this->scopedPolicy(2);
        $this->chat();
        User::find(2)->forceFill(['telegram_chat_id'=>'202'])->save();
        User::find(1)->forceFill(['telegram_chat_id'=>'101'])->save();
        DB::table('course_notification_preferences')->insert(['course_id'=>1,'user_id'=>1,'enabled'=>true]);
        Carbon::setTestNow(Carbon::parse('2026-11-15 00:00:00','Europe/Moscow'));
        $this->assertSame(5,app(DeadlineNotifications::class)->dispatchDue());
        foreach(DB::table('deadline_notification_deliveries')->get() as $delivery) {
            $ids=DB::table('deadline_notification_events')->whereIn('id',json_decode($delivery->event_ids,true))->pluck('policy_id');
            $this->assertCount(1,DB::table('deadline_policies')->whereIn('id',$ids)->pluck('audience_id')->unique());
        }
        $this->deliver();
        Http::assertSentCount(5);
        Http::assertSent(fn($r)=>$r['chat_id']==='101'&&str_contains($r['text'],'Подгруппа А')&&!str_contains($r['text'],'Подгруппа Б'));
        Http::assertSent(fn($r)=>$r['chat_id']==='-1009'&&$r['message_thread_id']===17&&str_contains($r['text'],'Подгруппа Б'));
        Carbon::setTestNow(now()->addDay());
        $this->assertSame(5,app(DeadlineNotifications::class)->dispatchDue());$this->deliver();
        Http::assertSentCount(10);
        Http::assertSent(fn($r)=>str_contains($r['text'],'Наступил срок')&&str_contains($r['text'],'Подгруппа А'));
        Http::assertSent(fn($r)=>str_contains($r['text'],'Наступил срок')&&str_contains($r['text'],'Подгруппа Б'));
    }

    public function test_common_deadline_is_distinguished_from_a_subgroup_deadline(): void
    {
        $this->chat();$this->scopedPolicy(0);$this->deliver();
        Http::assertSentCount(1);
        Http::assertSent(fn($r)=>str_contains($r['text'],'Общий срок курса')&&str_contains($r['text'],'/deadlines?subgroup=all')
            &&!str_contains($r['text'],'Подгруппа А')&&!str_contains($r['text'],'Подгруппа Б'));
    }

    public function test_public_announcement_uses_the_subgroups_lesson_opening_date(): void
    {
        DB::table('lesson_info')->where('course_id',1)->where('lesson_id',1)->update(['start_date'=>'2027-01-01']);
        DB::table('course_subgroup_lessons')->insert(['subgroup_id'=>1,'lesson_id'=>1,'start_date'=>'2026-11-01']);
        app(CourseSubgroups::class)->forget();
        $this->chat();$this->scopedPolicy(1);$this->scopedPolicy(2);$this->deliver();
        Http::assertSentCount(1);
        Http::assertSent(fn($r)=>str_contains($r['text'],'Подгруппа А')&&!str_contains($r['text'],'Подгруппа Б'));
    }
}
