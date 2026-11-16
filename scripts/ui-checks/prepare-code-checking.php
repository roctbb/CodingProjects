<?php
// Included by the isolated UI fixture builder; never reads the application DB.
$root = dirname(__DIR__, 2);
copy($directory.'/base.sqlite', $directory.'/submissions.sqlite');
require_once $root.'/vendor/autoload.php';
$application = require $root.'/bootstrap/app.php';
$application->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['app.env'=>'testing', 'database.default'=>'sqlite',
    'database.connections.sqlite.database'=>$directory.'/submissions.sqlite',
    'filesystems.disks.code_checking'=>['driver'=>'local','root'=>$directory.'/code-checking','throw'=>true],
    'cache.default'=>'array', 'code_checking.ai_detection_enabled'=>false]);
Illuminate\Support\Facades\Queue::fake();
Illuminate\Support\Facades\Notification::fake();
foreach (glob($directory.'/*.sqlite') as $fixture) {
    config(['database.connections.sqlite.database' => $fixture]);
    Illuminate\Support\Facades\DB::purge('sqlite');
    Illuminate\Support\Facades\DB::statement('CREATE UNIQUE INDEX native_qa_lesson_stats ON lesson_student_stats(course_id, lesson_id, student_id)');
    foreach (['2026_11_14_120000_create_code_checking_tables.php','2026_11_14_130000_expand_task_submissions.php','2026_11_14_140000_complete_native_submission_storage.php',
        '2026_11_13_180000_create_reward_notices_table.php'] as $migration) (require $root.'/database/migrations/'.$migration)->up();
}
config(['database.connections.sqlite.database' => $directory.'/submissions.sqlite']);
Illuminate\Support\Facades\DB::purge('sqlite');
$task = App\Task::findOrFail(12);
$task->forceFill(['name'=>'Максимум последовательности','text'=>'Напишите функцию largest(values), которая находит наибольшее число в списке. Среди чисел могут быть отрицательные.', 'max_mark'=>20, 'price'=>5])->save();
Illuminate\Support\Facades\DB::table('ranks')->where('id',1)->update(['to'=>10]);
Illuminate\Support\Facades\DB::table('ranks')->insert(['id'=>2,'name'=>'Исследователь алгоритмов','from'=>10,'to'=>1000,'icon'=>'fas fa-medal']);
$configuration = app(App\Services\CodeChecking\Configuration::class);
$configuration->save($task, ['language'=>'python','check_type'=>'tests','submission_modes'=>['files','code','project','repository'],
    'allowed_languages'=>['python','cpp'],'test_config'=>['tests'=>[
        ['input'=>"3\n1 2 3\n",'output'=>"3\n",'is_public'=>true],['input'=>"3\n-8 -2 -5\n",'output'=>"-2\n",'is_public'=>false]]]], 2);
$artifact = app(App\Services\CodeChecking\Artifacts::class)->put("def largest(values):\n    return max(values)\n");
$submissionId = '10000000-0000-4000-8000-000000000001'; $runId = '20000000-0000-4000-8000-000000000001';
App\CodeSubmission::create(['id'=>$submissionId,'user_id'=>1,'course_id'=>1,'task_id'=>12,'mode'=>'files','language'=>'python',
    'files'=>[['name'=>'main.py','artifact'=>$artifact]],'request_id'=>Illuminate\Support\Str::uuid(),'payload_hash'=>str_repeat('a',64),
    'current_run_id'=>$runId,'created_at'=>'2026-11-11 10:00:00']);
App\CodeExecutionRun::create(['id'=>$runId,'submission_id'=>$submissionId,'snapshot'=>$configuration->snapshot($task,App\CodeTaskChecker::find(12)),
    'state'=>'completed','points'=>10,'comment'=>'Проверьте работу с отрицательными числами.','finished_at'=>now(),
    'progress'=>['phase'=>'completed','completed'=>2,'total'=>2]]);
$field = fn ($text) => ['type'=>'inline','text'=>$text,'bytes'=>strlen($text),'sha256'=>hash('sha256',$text),'truncated'=>false];
$rows = [];
foreach ([['input'=>"3\n1 2 3\n",'expected'=>"3\n",'actual'=>"3\n",'public'=>true],['input'=>"3\n-8 -2 -5\n",'expected'=>"-2\n",'actual'=>"0\n",'public'=>false]] as $index=>$row) {
    $rows[] = ['number'=>$index+1,'test_id'=>str_repeat($index ? 'b' : 'a',64),'is_public'=>$row['public'],
        'verdict'=>$index ? 'wrong_answer' : 'passed','input'=>$field($row['input']),'expected'=>$field($row['expected']),
        'actual'=>$field($row['actual']),'error'=>null,'comparison'=>[],'limits'=>[]];
}
app(App\Services\CodeChecking\Reports::class)->accept(['schema_version'=>1,'run_id'=>$runId,'code_id'=>$submissionId,'user_id'=>1,'course_id'=>1,'task_id'=>12,
    'test_mode'=>'io','test_set_version'=>str_repeat('c',64),'started_at'=>time(),'finished_at'=>time(),'total'=>2,'passed'=>1,'tests'=>$rows]);
