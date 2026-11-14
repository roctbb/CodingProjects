<?php
/** Verify a complete archive without writing application data. */
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Services\CodeChecking\Artifacts;

$root=$argv[1]??throw new RuntimeException('Source archive directory required');
$id=hash_file('sha256',$root.'/manifest.json');$artifacts=app(Artifacts::class);
DB::beginTransaction();
try{
    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    $row=DB::table('code_legacy_exports')->find($id)??throw new RuntimeException('Archive not registered');
    $files=json_decode($row->files,true,128,JSON_THROW_ON_ERROR);
    $index=json_decode($artifacts->get($files['archive-index.json']['blob_id']),true,128,JSON_THROW_ON_ERROR);
    $failures=[];$verifiedFiles=0;$verifiedBlobs=0;$originals=0;$database=0;
    foreach($index['files'] as $name=>$source){
        $hash=hash_init('sha256');$size=0;
        foreach($source['parts'] as $part){
            $stream=$artifacts->readStream($files[$part]['blob_id']);
            try{$size+=hash_update_stream($hash,$stream);}finally{fclose($stream);}
        }
        if($size!==$source['bytes']||hash_final($hash)!==$source['sha256'])$failures[]='sidecar:'.$name;
        $verifiedFiles++;
    }
    $carry='';$seen=[];
    $verify=function(string $line)use(&$seen,&$verifiedBlobs,&$originals,&$database,&$failures,$artifacts){
        if($line==='')return;
        $entry=json_decode($line,true,64,JSON_THROW_ON_ERROR);$a=$entry['artifact'];$id=$entry['source_sha256'];
        if(isset($seen[$id]))$failures[]='duplicate:'.$id;$seen[$id]=true;
        $row=DB::table('code_artifacts')->find($id);
        if(!$row||(int)$row->bytes!==$a['bytes']||$a['sha256']!==$id||$a['blob_id']!==$id)$failures[]='descriptor:'.$id;
        if($entry['storage']==='database'){
            $stored=DB::table('code_artifact_contents')->where('id',$id)->first();
            $bytes=$stored?($stored->encoding==='base64'?base64_decode($stored->content,true):$stored->content):false;
            if(!is_string($bytes)||strlen($bytes)!==$a['bytes']||hash('sha256',$bytes)!==$id)$failures[]='database:'.$id;
            $database++;
        }else{
            if(!$row||$row->storage_disk!=='code_originals'||!$row->verified_at||!$artifacts->exists($id,$a['bytes']))$failures[]='original:'.$id;
            $originals++;
        }
        $verifiedBlobs++;
    };
    foreach($index['blob_maps'] as $part){
        $carry.=$artifacts->get($files[$part]['blob_id']);$lines=explode("\n",$carry);$carry=array_pop($lines);
        foreach($lines as $line)$verify($line);
    }
    if($carry!=='')$verify($carry);
    $sourceBlobs=count(glob($root.'/blobs/*'));
    if($sourceBlobs!==$verifiedBlobs)$failures[]='blob_count:'.$sourceBlobs.'/'.$verifiedBlobs;
    $out=['read_only'=>true,'id'=>$id,'passed'=>!$failures,'source_counts'=>json_decode($row->manifest,true)['counts'],
        'files_verified'=>$verifiedFiles,'blobs_verified'=>$verifiedBlobs,'database_blobs'=>$database,'original_blobs'=>$originals,
        'original_integrity'=>'SHA-256 verified at upload; presence rechecked','failures'=>$failures,'verified_at'=>gmdate('c')];
    echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
}finally{DB::rollBack();}
exit($failures?1:0);
