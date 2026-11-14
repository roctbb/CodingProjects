<?php
/** Verify a completed migration in an enforced read-only transaction. */
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Services\CodeChecking\LegacyImport;
use Carbon\Carbon;

$root=$argv[1]??throw new RuntimeException('Private audit root required');
$failures=[];$examples=[];
$check=function(bool $ok,string $kind,mixed $id=null)use(&$failures,&$examples){
    if($ok)return;$failures[$kind]=($failures[$kind]??0)+1;
    if(count($examples[$kind]??[])<10)$examples[$kind][]=$id;
};
$rows=function(string $path):Generator{
    $f=fopen($path,'rb');try{while(($line=fgets($f))!==false)if(trim($line)!=='')yield json_decode($line,true,128,JSON_THROW_ON_ERROR);}finally{fclose($f);}
};
$map=json_decode(file_get_contents($root.'/map.json'),true);
$taskPlan=json_decode(file_get_contents($root.'/task-plan.json'),true)['tasks'];
$manifest=json_decode(file_get_contents($root.'/ready/manifest.json'),true);
$before=json_decode(file_get_contents($root.'/before-apply/manifest.json'),true);
$expected=[];$uuidToLegacy=[];
foreach($rows($root.'/ready/solutions.jsonl') as $r){
    $id=LegacyImport::id('submission',$r['id']);$uuidToLegacy[$id]=$r['id'];
    $expected[$r['id']]=['id'=>$id,'task'=>$map['tasks'][(string)$r['task_id']],'course'=>$r['course_id'],'user'=>$r['user_id'],
        'source'=>$r['source'],'created'=>Carbon::parse($r['created_at'],'UTC')->timestamp,
        // PostgreSQL timestamp(0) rounds; created_at retains microseconds.
        'finished'=>(int)round((float)Carbon::parse($r['checked_at']??$r['created_at'],'UTC')->format('U.u')),
        'points'=>$r['check_points']??null,'comment'=>hash('sha256',$r['check_comments']??''),'archive'=>$r['original_archive']??null,
        'public'=>(bool)($r['available_without_auth']??$r['public']??$r['is_public']??false),
        'viewed'=>(bool)($r['viewed_by_teacher']??$r['viewed']??false)];
}
DB::beginTransaction();
try{
    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    if(DB::selectOne('SHOW transaction_read_only')->transaction_read_only!=='on')throw new RuntimeException('READ ONLY required');
    $counts=[];
    foreach(['code_submissions','code_execution_runs','code_task_checkers','code_legacy_tasks','code_legacy_task_versions','code_legacy_exports','code_artifacts','code_artifact_contents','code_fingerprints','code_integrity_reports'] as $t)$counts[$t]=DB::table($t)->count();
    $seen=0;$originals=0;$rawInDatabase=0;
    foreach(array_chunk(array_keys($expected),100) as $batch){
        $records=App\CodeSubmission::whereIn('legacy_id',$batch)->get()->keyBy('legacy_id');
        $runIds=array_map(fn($legacy)=>LegacyImport::id('run',$legacy),$batch);
        $runs=App\CodeExecutionRun::whereIn('id',$runIds)->get(['id','submission_id','points','comment','created_at','finished_at'])->keyBy('id');
        $digests=array_column(array_intersect_key($expected,array_flip($batch)),'source');
        $contents=DB::table('code_artifact_contents')->whereIn('id',array_column($digests,'sha256'))->pluck('id')->all();
        foreach($batch as $legacy){
            $e=$expected[$legacy];$s=$records->get($legacy);$check((bool)$s,'missing_submission',$legacy);if(!$s)continue;$seen++;
            $check($s->id===$e['id']&&(int)$s->task_id===$e['task']&&(int)$s->course_id===$e['course']&&(int)$s->user_id===$e['user'],'wrong_context',$legacy);
            $raw=$s->source_metadata['raw_source_artifact']??[];
            $check(($raw['sha256']??null)===$e['source']['sha256']&&($raw['bytes']??null)===$e['source']['bytes'],'raw_source_descriptor',$legacy);
            $stored=in_array($e['source']['sha256'],$contents,true);$check($stored,'raw_source_not_in_database',$legacy);$rawInDatabase+=(int)$stored;
            $check($s->created_at->timestamp===$e['created'],'submission_time_shift',$legacy);
            $check($s->is_public===$e['public']&&$s->teacher_viewed===$e['viewed'],'visibility_or_review_flag_changed',$legacy);
            $run=$runs->get(LegacyImport::id('run',$legacy));$check((bool)$run,'missing_historical_run',$legacy);
            if($run){
                $check($run->created_at->timestamp===$e['created']&&$run->finished_at->timestamp===$e['finished'],'run_time_shift',$legacy);
                $check($run->points===$e['points']&&hash('sha256',$run->comment??'')===$e['comment'],'historical_result_changed',$legacy);
            }
            if($e['archive']){
                $originals++;$files=$s->files;$check(in_array($e['archive']['sha256'],array_column(array_column($files,'artifact'),'sha256'),true),'original_archive_not_linked',$legacy);
            }
        }
    }
    $check($seen===$manifest['counts']['solutions'],'submission_count',$seen);
    $checkerTypes=[];$suiteIds=[];
    foreach($taskPlan as $legacy=>$p){
        $task=App\Task::find($p['task_id']);$check((bool)$task,'missing_task',$legacy);if(!$task)continue;
        $textMatches=isset($p['set_public_text'])?$task->text===$p['set_public_text']:hash('sha256',$task->text??'')===$p['target_public_text_sha256'];
        $check($textMatches,'public_text_changed',$legacy);$check((int)$task->max_mark===($p['set_max_mark']??$p['target_points']),'maximum_changed',$legacy);
        $checker=App\CodeTaskChecker::find($p['task_id']);
        if($p['action']==='archive_only'){
            if($p['preserve_existing_checker']??false)$check($checker && $checker->revision===$p['expected_checker_revision'],'existing_checker_changed',$legacy);
            else $check(!$checker,'invented_checker_for_archive',$legacy);
            continue;
        }
        $check((bool)$checker,'missing_checker',$legacy);if(!$checker)continue;$c=$checker->config;
        $check(!($c['requires_conversion']??false),'active_checker_needs_conversion',$legacy);
        $check(($c['private_instructions']??'')===($p['private_instructions']??''),'private_instructions_changed',$legacy);
        $kind=$c['check_type']==='gpt'?'ai':$c['test_mode'];$checkerTypes[$kind]=($checkerTypes[$kind]??0)+1;
        if($kind==='suite'){$suiteIds[]=$c['test_config']['bundle']['blob_id'];$check(!$checker->allows_test_reveals,'suite_test_purchase_enabled',$legacy);}
    }
    foreach(array_unique($suiteIds) as $id)$check(DB::table('code_artifact_contents')->where('id',$id)->exists(),'suite_bundle_not_in_database',$id);
    $expectedMatches=[];
    foreach($rows($root.'/ready/similarities.jsonl') as $edge){
        $expectedMatches[$edge['code_id']][$edge['code_id2']]=(float)$edge['percent'];
        $expectedMatches[$edge['code_id2']][$edge['code_id']]=(float)$edge['percent'];
    }
    $similarityLinks=0;
    DB::table('code_integrity_reports')->orderBy('submission_id')->chunkById(250,function($batch)use(&$expectedMatches,$uuidToLegacy,&$similarityLinks,$check){
        foreach($batch as $row){
            $legacy=$uuidToLegacy[$row->submission_id]??null;if($legacy===null)continue;
            $value=json_decode($row->plagiarism,true);$found=[];
            foreach($value['matches']??[] as $m){
                $other=$uuidToLegacy[$m['submission_id']]??null;
                $check($other!==null&&isset($expectedMatches[$legacy][$other])&&abs((float)$m['percent']-$expectedMatches[$legacy][$other])<1e-9,'similarity_link_changed',[$legacy,$other]);
                if($other!==null)$found[$other]=true;$similarityLinks++;
            }
            $check(count($found)===count($expectedMatches[$legacy]??[]),'missing_similarity_link',$legacy);
        }
    },'submission_id');
    $textChecks=0;
    // Small batches also cover unusually large notebooks without buffering the table.
    DB::table('code_artifact_contents')->orderBy('id')->chunkById(5,function($batch)use(&$textChecks,$check){
        foreach($batch as $r){$bytes=$r->encoding==='base64'?base64_decode($r->content,true):$r->content;$check(is_string($bytes)&&hash_equals($r->id,hash('sha256',$bytes)),'text_artifact_checksum',$r->id);$textChecks++;}
    });
    $oldSolutions=[];
    foreach($rows($root.'/before-apply/solutions.jsonl') as $s)$oldSolutions[$s['id']]=$s;
    $linked=0;$protectedSolutionRows=0;$concurrentReviews=[];
    $importStarted=trim(file_get_contents($root.'/apply-started'));
    DB::table('solutions')->orderBy('id')->chunkById(500,function($batch)use(&$oldSolutions,$expected,&$linked,&$protectedSolutionRows,&$concurrentReviews,$importStarted,$check){
        foreach($batch as $new){
            $old=$oldSolutions[$new->id]??null;if(!$old)continue;$protectedSolutionRows++;
            $legacy=$old['geekpaste_code_id']??null;
            if(!$legacy&&preg_match('/(?:\?|&|&amp;)id=([A-Za-z0-9_-]+)/',$old['text']??'',$m))$legacy=$m[1];
            $e=$expected[$legacy??'']??null;
            $concurrent=!$e && $new->updated_at!==$old['updated_at'] && Carbon::parse($new->updated_at,config('app.timezone'))->greaterThan(Carbon::parse($importStarted));
            foreach($old as $key=>$value)if($key!=='text' && $new->$key!==$value){
                if($concurrent && in_array($key,['updated_at','mark','checked','comment','teacher_id','raw_mark'],true))$concurrentReviews[$new->id][]=$key;
                else $check(false,'existing_solution_field_changed',[$new->id,$key]);
            }
            if($e&&(int)$old['task_id']===$e['task']&&(int)$old['course_id']===$e['course']&&(int)$old['user_id']===$e['user']){
                $check(str_ends_with($new->text??'','/insider/code-submissions/'.$e['id']),'solution_link_not_replaced',$new->id);$linked++;
            }else $check($new->text===$old['text'],'unrelated_solution_link_changed',$new->id);
        }
    });
    $check($protectedSolutionRows===count($oldSolutions),'existing_solution_deleted',$protectedSolutionRows);
    $unchanged=[];$newRewardRows=[];
    foreach(['coin_transactions','achievements','reward_notices'] as $table)if(isset($before['protected'][$table])){
        $hash=hash_init('sha256');$n=0;$originalCount=$before['protected'][$table]['count'];
        foreach(DB::table($table)->orderBy('id')->cursor() as $row){if($n<$originalCount)hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n");$n++;}
        $same=$n>=$originalCount&&hash_equals($before['protected'][$table]['sha256'],hash_final($hash));
        $newRewardRows[$table]=max(0,$n-$originalCount);
        $unchanged[$table]=$same;$check($same,'reward_table_changed',$table);
    }
    $originalMetadata=DB::table('code_artifacts')->where('storage_disk','code_originals')->count();
    $check(DB::table('code_artifacts')->where('storage_disk','code_originals')->whereNull('verified_at')->count()===0,'unverified_originals');
    $report=['read_only'=>true,'verified_at'=>gmdate('c'),'passed'=>!$failures,'counts'=>$counts,'checker_types'=>$checkerTypes,
        'source_records_verified'=>$seen,'raw_sources_in_database'=>$rawInDatabase,'text_checksums_verified'=>$textChecks,'similarity_links_verified'=>$similarityLinks,
        'zip_original_links_verified'=>$originals,'original_metadata_records'=>$originalMetadata,'original_storage_driver'=>config('filesystems.disks.code_originals.driver'),
        'existing_solution_rows_preserved'=>$protectedSolutionRows,'solution_links_updated'=>$linked,'existing_reward_rows_unchanged'=>$unchanged,'new_reward_rows_during_import'=>$newRewardRows,
        'timestamp_precision'=>'created_at microseconds; finished_at PostgreSQL timestamp(0) rounding; complete original strings retained in metadata',
        'concurrent_unrelated_teacher_reviews'=>$concurrentReviews,'failures'=>$failures,'examples'=>$examples];
    file_put_contents($root.'/verification.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));chmod($root.'/verification.json',0600);
    echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
}finally{DB::rollBack();}
exit($failures?1:0);
