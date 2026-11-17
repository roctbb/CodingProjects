<?php
// Included after the standard synthetic fixtures; no application database is used.
foreach (['subgroups-desktop', 'subgroups-phone'] as $scenario) {
    copy($directory.'/base.sqlite', $directory.'/'.$scenario.'.sqlite');
    config(['database.connections.sqlite.database'=>$directory.'/'.$scenario.'.sqlite']);
    Illuminate\Support\Facades\DB::purge('sqlite');
    Illuminate\Support\Facades\DB::statement('CREATE UNIQUE INDEX subgroup_qa_course_points ON course_student_points(course_id, student_id)');
    (require $root.'/database/migrations/2026_11_17_120000_create_course_subgroups.php')->up();
    app()->forgetInstance(App\Services\CourseSubgroups::class);
    app()->forgetInstance(App\Services\CourseDeadlineService::class);
    Carbon\Carbon::setTestNow('2026-11-01 12:00:00 Europe/Moscow');
    $course=App\Course::findOrFail(1);
    $a=$course->subgroups()->create(['name'=>'Подгруппа А · дневная','current_lesson_id'=>1,'current_lesson_override'=>true]);
    $b=$course->subgroups()->create(['name'=>'Подгруппа Б · вечерняя','current_lesson_id'=>2,'current_lesson_override'=>true]);
    Illuminate\Support\Facades\DB::table('course_students')->insert(['course_id'=>1,'user_id'=>6,'is_remote'=>0,'hidden_from_stats'=>0]);
    app(App\Services\CourseSubgroups::class)->assign($course,[1,3],$a->id,2);
    app(App\Services\CourseSubgroups::class)->assign($course,[4],$b->id,2);
    $service=app(App\CoursePlanner\Services\PlannerService::class);
    $actor=App\User::findOrFail(2);
    foreach ([[$a,'2026-11-11','15:00','Практика А'],[$a,'2026-11-13','15:00','Циклы А'],
        [$b,'2026-11-12','16:00','Практика Б'],[$b,'2026-11-13','16:45','Циклы Б']] as [$group,$day,$time,$title]) {
        $planner=$service->planner($course,$group->id);
        $result=$service->command($course,$actor,'add_slot',['subgroup'=>(string)$group->id,'date'=>$day,'start_time'=>$time,
            'duration_minutes'=>90,'title'=>$title,'revision'=>$planner->fresh()->revision,'operation_id'=>(string)Illuminate\Support\Str::uuid()]);
        $service->command($course,$actor,'attach',['subgroup'=>(string)$group->id,'session_id'=>$result['result']['session_id'],
            'provider_key'=>'codingprojects','resource_type'=>'step','external_id'=>$title==='Практика А'?'1':'2','layer'=>'plan',
            'revision'=>$planner->fresh()->revision,'operation_id'=>(string)Illuminate\Support\Str::uuid()]);
    }
    foreach ([[$a,'2026-11-18'],[$b,'2026-11-19']] as [$group,$date]) app(App\Services\CourseDeadlineService::class)->savePolicy($course,'lesson',1,
        ['revision'=>0,'mode'=>'custom','timezone'=>'Europe/Moscow','stages'=>[['deadline_date'=>$date,'retained_percent'=>50]]],2,false,$group->id);
    App\CourseStudentPoints::recalculateForStudents(1);
    App\LessonStudentStats::recalculateForStudents(1,null,false);
}

foreach (['desktop', 'phone'] as $device) {
    $scenario = 'subgroups-large-'.$device;
    copy($directory.'/subgroups-'.$device.'.sqlite', $directory.'/'.$scenario.'.sqlite');
    config(['database.connections.sqlite.database'=>$directory.'/'.$scenario.'.sqlite']);
    Illuminate\Support\Facades\DB::purge('sqlite');
    app()->forgetInstance(App\Services\CourseSubgroups::class);
    $course = App\Course::findOrFail(1);
    foreach (range(7,32) as $id) {
        Illuminate\Support\Facades\DB::table('users')->insertOrIgnore(['id'=>$id,'name'=>'Ученик '.$id,'role'=>'student','email'=>'subgroups-'.$id.'@example.test','email_verified_at'=>'2026-09-01']);
        Illuminate\Support\Facades\DB::table('course_students')->insert(['course_id'=>1,'user_id'=>$id,'is_remote'=>0,'hidden_from_stats'=>0]);
    }
    app(App\Services\CourseSubgroups::class)->assign($course,range(7,16),1,2);
    app(App\Services\CourseSubgroups::class)->assign($course,range(17,26),2,2);
    Illuminate\Support\Facades\DB::table('users')->where('id',31)->update(['name'=>'Фёдоров Артём']);
    Illuminate\Support\Facades\DB::table('users')->where('id',32)->update(['name'=>'Александрова-Михайлова Елизавета Константиновна']);
    Illuminate\Support\Facades\DB::table('course_subgroups')->where('id',2)->update(['name'=>'Подгруппа Б · вечерняя — подготовка к олимпиаде по программированию']);
}
