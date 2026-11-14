<?php
// Run only against the deployed application, with a new private output directory.
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$root=$argv[1]??throw new RuntimeException('Output directory required');
if (!mkdir($root,0700)) throw new RuntimeException('New directory required');
DB::beginTransaction();
try {
    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    if (DB::selectOne('SHOW transaction_read_only')->transaction_read_only!=='on') throw new RuntimeException('READ ONLY required');
    $queries=[
        'tasks'=>DB::table('tasks')->join('program_steps','program_steps.id','=','tasks.step_id')
            ->select('tasks.id','tasks.text','tasks.name','tasks.max_mark','tasks.is_code','program_steps.program_id')->orderBy('tasks.id'),
        'courses'=>DB::table('courses')->select('id','program_id')->orderBy('id'),
        'users'=>DB::table('users')->select('id','role')->orderBy('id'),
        'solutions'=>DB::table('solutions')->orderBy('id'),
        'checkers'=>DB::table('code_task_checkers')->select('task_id','revision','config')->orderBy('task_id'),
    ];
    $counts=[];$files=[];
    foreach($queries as $name=>$query) {
        $f=fopen($root.'/'.$name.'.jsonl','xb');chmod($root.'/'.$name.'.jsonl',0600);$counts[$name]=0;
        try {foreach($query->cursor() as $row){fwrite($f,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");$counts[$name]++;}}
        finally {fclose($f);}
        $files[$name.'.jsonl']=hash_file('sha256',$root.'/'.$name.'.jsonl');
    }
    $protected=[];
    foreach(['coin_transactions','achievements','reward_notices','code_submissions','code_execution_runs'] as $table) {
        if (!Illuminate\Support\Facades\Schema::hasTable($table))continue;
        $hash=hash_init('sha256');$count=0;
        foreach(DB::table($table)->orderBy('id')->cursor() as $row){hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n");$count++;}
        $protected[$table]=['count'=>$count,'sha256'=>hash_final($hash)];
    }
    file_put_contents($root.'/manifest.json',json_encode(['at'=>gmdate('c'),'counts'=>$counts,'files'=>$files,'protected'=>$protected,'read_only'=>true],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    chmod($root.'/manifest.json',0600);
    echo json_encode($counts,JSON_THROW_ON_ERROR)."\n";
} finally {DB::rollBack();}
