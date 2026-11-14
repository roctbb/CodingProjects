<?php
namespace App\Services\CodeChecking;

use App\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** Lossless private archive; it never invents ownership or changes grades. */
class LegacyArchive
{
    public function __construct(private Artifacts $artifacts) {}

    public function run(string $root,int $actor,bool $apply=false,?string $environment=null): array
    {
        $root=realpath($root)?:throw new \InvalidArgumentException('Missing archive directory');
        $manifest=json_decode(file_get_contents($root.'/manifest.json'),true,128,JSON_THROW_ON_ERROR);
        if(($manifest['scope']??null)!=='complete')throw new \InvalidArgumentException('Complete source export required');
        if(User::find($actor)?->role!=='admin')throw new \InvalidArgumentException('Administrator required');
        $id=hash_file('sha256',$root.'/manifest.json');
        $files=$manifest['files'];$files['manifest.json']=$id;
        foreach($files as $name=>$hash){
            if(basename($name)!==$name || !is_file($root.'/'.$name) || !hash_equals($hash,hash_file('sha256',$root.'/'.$name)))throw new \InvalidArgumentException('Archive sidecar checksum mismatch: '.$name);
        }
        $blobs=[];$counts=['tasks'=>0,'solutions'=>0,'similarities'=>0,'source_files'=>0];
        $collect=function($value,bool $original=false)use(&$collect,&$blobs){
            if(!is_array($value))return;
            if(isset($value['path'],$value['sha256'],$value['bytes'])){
                $hash=$value['sha256'];
                if(!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash)||$value['path']!=='blobs/'.$hash||!is_int($value['bytes'])||$value['bytes']<0)throw new \InvalidArgumentException('Invalid source descriptor');
                if(isset($blobs[$hash])&&$blobs[$hash]['bytes']!==$value['bytes'])throw new \InvalidArgumentException('Conflicting source descriptor');
                $blobs[$hash]=['bytes'=>$value['bytes'],'original'=>$original||($blobs[$hash]['original']??false)];return;
            }
            foreach($value as $key=>$child)$collect($child,$original||$key==='original_archive');
        };
        foreach(array_keys($counts) as $kind){
            foreach($this->rows($root.'/'.str_replace('_','-',$kind).'.jsonl') as $row){
                $counts[$kind]++;
                $collect($row,$kind==='source_files'&&($row['kind']??null)==='zip_archives');
            }
            if($counts[$kind]!==($manifest['counts'][$kind]??0))throw new \InvalidArgumentException('Source row count mismatch: '.$kind);
        }
        foreach($blobs as $hash=>$metadata){
            $path=$root.'/blobs/'.$hash;
            if(!is_file($path)||is_link($path)||filesize($path)!==$metadata['bytes']||!hash_equals($hash,hash_file('sha256',$path)))throw new \InvalidArgumentException('Source blob checksum mismatch: '.$hash);
        }
        $result=['id'=>$id,'apply'=>$apply,'counts'=>$counts,'blobs'=>count($blobs),'source_data_deleted'=>false];
        if(!$apply)return $result;
        if(DB::table('code_legacy_exports')->where('id',$id)->exists())return $result+['already_archived'=>true];
        $stored=[];$index=['format'=>1,'source_manifest_sha256'=>$id,'files'=>[],'blob_maps'=>[]];
        $mapping=tmpfile();
        try{
            foreach($blobs as $hash=>$metadata){
                $existing=DB::table('code_artifacts')->find($hash);
                $inDatabase=DB::table('code_artifact_contents')->where('id',$hash)->exists();
                if($metadata['original']){
                    if($existing && $existing->storage_disk==='code_originals' && $existing->verified_at && $this->artifacts->exists($hash,$metadata['bytes']))$artifact=$this->descriptor($hash,$metadata['bytes']);
                    else $artifact=$this->artifacts->putFile($root.'/blobs/'.$hash,true);
                }else $artifact=$inDatabase?$this->descriptor($hash,$metadata['bytes']):$this->artifacts->putDatabase(file_get_contents($root.'/blobs/'.$hash));
                fwrite($mapping,json_encode(['source_sha256'=>$hash,'artifact'=>$artifact,'storage'=>$metadata['original']?'original':'database'],JSON_THROW_ON_ERROR)."\n");
            }
            rewind($mapping);
            $index['blob_maps']=$this->parts($mapping,'blob-map',$stored);
        }finally{fclose($mapping);}
        foreach($files as $name=>$hash){
            // Redundant original environment tar and DB dump are downloadable backups.
            if(in_array($name,['environments.tar.gz','database.dump'],true)){
                $stored[$name]=$this->artifacts->putFile($root.'/'.$name,true);
                $index['files'][$name]=['sha256'=>$hash,'bytes'=>filesize($root.'/'.$name),'parts'=>[$name]];
            }else{
                $stream=fopen($root.'/'.$name,'rb');
                try{$parts=$this->parts($stream,str_replace('.','-',$name),$stored);}finally{fclose($stream);}
                $index['files'][$name]=['sha256'=>$hash,'bytes'=>filesize($root.'/'.$name),'parts'=>$parts];
            }
        }
        if($environment){
            // Preserve the key needed to decrypt old AI settings without exposing secrets.
            $stored['runtime-environment.encrypted']=$this->artifacts->putDatabase(Crypt::encryptString(file_get_contents($environment)));
            $index['environment_encryption']='Laravel Crypt; Coding Projects APP_KEY required';
        }
        $stored['archive-index.json']=$this->artifacts->putDatabase(json_encode($index,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        DB::table('code_legacy_exports')->insertOrIgnore(['id'=>$id,'manifest'=>json_encode($manifest,JSON_THROW_ON_ERROR),'files'=>json_encode($stored,JSON_THROW_ON_ERROR),'created_by'=>$actor,'created_at'=>now()]);
        return $result+['download_parts'=>count($stored),'already_archived'=>false];
    }

    private function descriptor(string $hash,int $bytes):array
    {
        return ['type'=>'blob','blob_id'=>$hash,'sha256'=>$hash,'bytes'=>$bytes,'truncated'=>false];
    }

    private function rows(string $path):\Generator
    {
        if(!is_file($path))throw new \InvalidArgumentException('Missing source file: '.basename($path));
        $stream=fopen($path,'rb');
        try{while(($line=fgets($stream))!==false)if(trim($line)!=='')yield json_decode($line,true,128,JSON_THROW_ON_ERROR);}finally{fclose($stream);}
    }

    private function parts($stream,string $prefix,array &$stored):array
    {
        $parts=[];$n=0;
        while(!feof($stream)){
            $bytes=fread($stream,8388608);if($bytes==='')break;
            $name=$prefix.'.'.sprintf('%04d',++$n).'.part';
            $stored[$name]=$this->artifacts->putDatabase($bytes);$parts[]=$name;
        }
        return $parts;
    }
}
