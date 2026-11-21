<?php
// Extra cases for the submission workspace, in its disposable database only.
config(['database.connections.sqlite.database'=>$directory.'/submissions.sqlite']);
Illuminate\Support\Facades\DB::purge('sqlite');
$store=app(App\Services\CodeChecking\Artifacts::class);
$db=Illuminate\Support\Facades\DB::class;
$baseTask=App\Task::findOrFail(12);
$quiz = $baseTask->replicate();
$quiz->forceFill(['id'=>18, 'name'=>'Размер одного байта', 'text'=>'Сколько бит в одном байте?',
    'sort_index'=>18, 'is_code'=>false, 'is_quiz'=>true, 'max_attempts'=>2, 'max_mark'=>3,
    'quiz_choices'=>['mode'=>'single', 'options'=>[['label'=>'4','correct'=>false],['label'=>'8','correct'=>true]]]])->save();
foreach ([13=>['Разбор алгоритма','gpt'],14=>['Объяснение решения','manual'],15=>['Архив проекта','tests'],16=>['Много тестов','tests'],17=>['Исследование в тетрадке','gpt']] as $id=>[$name,$type]) {
    $copy=$baseTask->replicate();$copy->id=$id;$copy->name=$name;$copy->text='Объясните работу алгоритма и приложите своё решение.';
    $copy->sort_index=$id;$copy->is_code=$type!=='manual';$copy->save();
    app(App\Services\CodeChecking\Configuration::class)->save($copy,['language'=>$type==='manual'?'text':'python','check_type'=>$type,
        'submission_modes'=>$type==='manual'?['text','files']:['code','project','files'],
        'private_instructions'=>'Оцените корректность объяснения.', 'gpt_model'=>'qa-basic','gpt_rate_limit'=>5,
        'test_config'=>$type==='tests'?['tests'=>array_fill(0,$id===16?23:1,['input'=>'1','output'=>'1','is_public'=>true])]:[]],2);
}
$db::table('tasks')->where('id',13)->update([
    'xp_booster_enabled'=>true,
    'text'=>"Объясните работу алгоритма.\n\n| Ввод (по строкам) | Вывод |\n| --- | --- |\n| `2`<br>`4 7` | `7` |\n| `1`<br>`0` | `0` |",
]);
$make=function(int $task,int $user,int $suffix,string $type,?int $points,?int $solution=null)use($store,$db){
    $id=sprintf('10000000-0000-4000-8000-%012d',$suffix);$run=sprintf('20000000-0000-4000-8000-%012d',$suffix);
    $config=app(App\Services\CodeChecking\Configuration::class)->snapshot(App\Task::find($task),App\CodeTaskChecker::find($task));
    $source=$store->put("def largest(values):\n    return max(values)\n");
    App\CodeSubmission::create(['id'=>$id,'user_id'=>$user,'course_id'=>1,'task_id'=>$task,'mode'=>$type==='manual'?'text':'code','language'=>$type==='manual'?'text':'python',
        'answer_text'=>$type==='manual'?'Я сравниваю каждый элемент с текущим максимумом.':null,
        'files'=>$type==='manual'?[]:[['name'=>'main.py','artifact'=>$source]],'request_id'=>Illuminate\Support\Str::uuid(),'payload_hash'=>str_repeat('a',64),
        'current_run_id'=>$run,'solution_id'=>$solution,'created_at'=>'2026-11-11 10:15:00']);
    App\CodeExecutionRun::create(['id'=>$run,'submission_id'=>$id,'snapshot'=>$config,'state'=>$points===null?'awaiting_review':'completed',
        'points'=>$points,'comment'=>$type==='gpt'?'Проверьте обоснование выбора начального значения.':null,'finished_at'=>$points===null?null:now()]);
    if($solution)$db::table('solutions')->insert(['id'=>$solution,'course_id'=>1,'task_id'=>$task,'user_id'=>$user,'teacher_id'=>2,
        'text'=>url('/insider/code-submissions/'.$id),'mark'=>$points,'raw_mark'=>$points,'evaluated_mark'=>$points,
        'submitted'=>'2026-11-11 10:15:00','checked'=>$points===null?null:'2026-11-11 10:16:00','review_skipped'=>false,
        'recheck_requested'=>$type==='gpt','recheck_comment'=>$type==='gpt'?'Проверьте, пожалуйста, второй критерий.':null]);
    return $id;
};
$ai=$make(13,1,13,'gpt',8,113);$other=$make(13,3,23,'gpt',12,123);$make(14,1,14,'manual',null,114);
$make(14,3,24,'manual',null,124);$make(12,1,31,'tests',6);
$paged=$make(16,1,16,'tests',10);
$notebookId=$make(17,1,17,'gpt',18);
$notebook=['nbformat'=>4,'nbformat_minor'=>5,'metadata'=>['kernelspec'=>['language'=>'python']],
    'cells'=>[['cell_type'=>'markdown','source'=>['# Сравнение моделей',"\n\nПроверяем качество на отложенной выборке."]],
        ['cell_type'=>'code','execution_count'=>1,'source'=>["print('accuracy: 0.83')\n",'# '.str_repeat('long_source_',300)],'outputs'=>[
            ['output_type'=>'stream','name'=>'stdout','text'=>'accuracy: 0.83'],
            ['output_type'=>'display_data','data'=>['text/html'=>'<script>window.notebookExecuted=true</script><table class="dataframe"><tr>'.str_repeat('<th>Accuracy score column</th>',40).'</tr><tr>'.str_repeat('<td>0.83</td>',40).'</tr></table>'],'metadata'=>[]]]],
        ['cell_type'=>'code','source'=>['model.predict(X_val)']]]];
$notebookSource=$store->put(json_encode($notebook,JSON_UNESCAPED_UNICODE));
App\CodeSubmission::find($notebookId)->update(['mode'=>'files','files'=>[['name'=>str_repeat('long_name_',15).'titanic.ipynb','artifact'=>$notebookSource]]]);
$largeNotebook=$make(17,1,18,'gpt',16);
$notebook['metadata']['extra_padding']=str_repeat('x',2100000);
App\CodeSubmission::find($largeNotebook)->update(['mode'=>'files','files'=>[['name'=>'large-notebook.ipynb','artifact'=>$store->put(json_encode($notebook,JSON_UNESCAPED_UNICODE))]]]);
$pagedRun=App\CodeSubmission::find($paged)->current_run_id;
$field=fn($text)=>['type'=>'inline','text'=>$text,'bytes'=>strlen($text),'sha256'=>hash('sha256',$text),'truncated'=>false];
$testRows=[];
for($number=1;$number<=23;$number++)$testRows[]=['number'=>$number,'test_id'=>hash('sha256','page-test-'.$number),'is_public'=>true,
    'verdict'=>$number<=10?'passed':'wrong_answer','input'=>$field('1'),'expected'=>$field('1'),'actual'=>$field($number<=10?'1':'0'),'error'=>null,'comparison'=>[],'limits'=>[]];
app(App\Services\CodeChecking\Reports::class)->accept(['schema_version'=>1,'run_id'=>$pagedRun,'code_id'=>$paged,'user_id'=>1,'course_id'=>1,'task_id'=>16,
    'test_mode'=>'io','test_set_version'=>str_repeat('d',64),'started_at'=>time(),'finished_at'=>time(),'total'=>23,'passed'=>10,'tests'=>$testRows]);
$legacy=$make(15,1,15,'tests',10);
$db::table('code_integrity_reports')->insert(['submission_id'=>$ai,'state'=>'completed',
    'plagiarism'=>json_encode(['state'=>'done','warning'=>true,'method'=>'token_shingles_v1','matches'=>[['submission_id'=>$other,'percent'=>88]]]),
    'ai'=>json_encode(['state'=>'done','warning'=>true,'confidence'=>'medium','reasons'=>['Нужен устный разбор объяснения алгоритма.']]),
    'created_at'=>now(),'updated_at'=>now()]);
$path=$directory.'/workspace.zip';$zip=new ZipArchive();$zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);
$zip->addFromString('src/main.py',"print(42)\n");$zip->addFromString('src/helper.py',"def answer():\n    return 42\n");$zip->close();
$original=$store->putDatabase(file_get_contents($path));
$raw=$store->putDatabase(json_encode([['name'=>'src/main.py','content'=>"print(42)\n",'is-binary'=>false],['name'=>'src/helper.py','content'=>"def answer():\n    return 42\n",'is-binary'=>false]]));
App\CodeSubmission::find($legacy)->update(['legacy_id'=>'UIZIP','language'=>'text','mode'=>'files','files'=>[['name'=>'solution.zip','artifact'=>$original]],
    'source_metadata'=>['legacy_id'=>'UIZIP','legacy_metadata'=>['lang'=>'zip'],'raw_source_artifact'=>$raw]]);
foreach([115=>6,116=>10] as $id=>$mark)$db::table('solutions')->insert(['id'=>$id,'course_id'=>1,'task_id'=>15,'user_id'=>1,'teacher_id'=>2,
    'text'=>'https://paste.geekclass.ru/code?id=UIZIP','geekpaste_code_id'=>'UIZIP','mark'=>$mark,'raw_mark'=>$mark,'evaluated_mark'=>$mark,
    'submitted'=>'2026-11-10 10:'.($id===115?'00':'30').':00','review_skipped'=>false]);
$db::table('lessons')->where('id',1)->update(['sticker'=>'/images/silaeder-logo.png']);
Illuminate\Support\Facades\DB::disconnect('sqlite');
foreach (['submissions-desktop','submissions-phone'] as $variant) copy($directory.'/submissions.sqlite',$directory.'/'.$variant.'.sqlite');
