<?php
require __DIR__.'/bootstrap.php';
[$app,$kernel,$directory,$settings]=executorQaApp(true);
Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode=WAL');
// No artificial deadline penalties or unrelated automatic achievements in the integration fixture.
Illuminate\Support\Facades\DB::table('task_deadlines')->where('task_id',12)->delete();
Illuminate\Support\Facades\DB::table('tasks')->where('id',12)->update(['generates_ai_achievement'=>false]);
$task=App\Task::findOrFail(12);
app(App\Services\CodeChecking\Configuration::class)->save($task,[
    'language'=>'python','allowed_languages'=>['python','cpp'],'submission_modes'=>['files','code','project','repository'],
    'check_type'=>'tests','test_config'=>['time_limit'=>5,'memory_limit'=>512,'process_limit'=>64,'disable_swap'=>true,
        'comparison'=>'float','absolute_tolerance'=>'0.000001','scoring'=>'groups',
        'groups'=>[['id'=>'basic','name'=>'Базовые','points'=>10],['id'=>'hidden','name'=>'Скрытые','points'=>10]],'tests'=>[
        ['input'=>"2 3\n",'output'=>"5\n",'is_public'=>true,'group'=>'basic','comparison'=>'exact','time_limit'=>4,'output_limit'=>4096],['input'=>"17 25\n",'output'=>"42\n",'is_public'=>false,'group'=>'hidden']]]],2);
echo "Disposable Coding Projects fixture ready\n";
