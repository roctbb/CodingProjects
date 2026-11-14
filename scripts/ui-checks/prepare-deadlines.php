<?php
// Applied to disposable UI databases only, after the native submissions fixture.
config(['services.telegram'=>['bot_token'=>'123:qa-fake','bot_username'=>'class_test_bot','api_url'=>'https://telegram.example','timeout'=>10,'proxy'=>null,'webhook_secret'=>'qa-secret']]);
foreach (glob($directory.'/*.sqlite') as $fixture) {
    config(['database.connections.sqlite.database'=>$fixture]);
    Illuminate\Support\Facades\DB::purge('sqlite');
    foreach (['2026_11_14_190000_create_deadline_policies.php','2026_11_14_200000_create_deadline_notifications.php',
        '2026_11_14_210000_create_classroom_bonuses.php'] as $file) (require $root.'/database/migrations/'.$file)->up();
    app()->forgetInstance(App\Services\CourseDeadlineService::class);
    $service=app(App\Services\CourseDeadlineService::class);
    $service->savePolicy(App\Course::find(1),'lesson',1,['revision'=>0,'mode'=>'custom','timezone'=>'Europe/Moscow','apply_to_tasks'=>true,
        'stages'=>[['cutoff_at'=>'2026-11-15T18:00','retained_percent'=>70],['cutoff_at'=>'2026-11-22T18:00','retained_percent'=>0]]],2);
    Illuminate\Support\Facades\DB::table('users')->where('id',2)->update(['telegram_chat_id'=>'202','telegram_user_id'=>'202']);
    Illuminate\Support\Facades\DB::table('course_telegram_chats')->insert(['course_id'=>1,'bot_key'=>app(App\Services\CourseTelegram::class)->botKey(),
        'chat_id'=>'-1009','thread_id'=>17,'title'=>'Учебная группа Python','status'=>'active','linked_by'=>2,'confirmed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
}
