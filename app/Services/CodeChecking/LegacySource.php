<?php
namespace App\Services\CodeChecking;

/** Pure conversion plan, shared by preflight and apply. */
class LegacySource
{
    public function prepare(array $row,array $task,callable $blob): array
    {
        $source=file_get_contents($blob($row['source']));
        if(!mb_check_encoding($source,'UTF-8'))throw new \InvalidArgumentException('Invalid UTF-8 source');
        $legacy=$row['lang'] ?? $task['lang'] ?? 'text';
        $language=match($legacy){'ipynb'=>'python','zip','github','image','xlsx'=>'text',default=>$legacy};
        $warnings=[];$files=[];$metadata=[];$mode='code';
        if(!in_array($language,Configuration::LANGUAGES,true)){$warnings[]='unknown_language';$language='text';}
        $add=function($name,$value,$original=false,$mime=null)use(&$files,&$warnings) {
            $originalName=(string)$name;
            try {$name=Paths::validate($name);} catch(\Throwable) {
                $extension=strtolower(pathinfo($originalName,PATHINFO_EXTENSION));
                $name='legacy-'.count($files).'-'.substr(hash('sha256',$originalName),0,16).(preg_match('/^[a-z0-9]{1,12}$/D',$extension)?'.'.$extension:'');
                $warnings[]='renamed_execution_path';
            }
            if(in_array($name,array_column($files,'name'),true)){$name='legacy-'.count($files).'-'.basename($name);$warnings[]='duplicate_path';}
            $files[]=['name'=>$name,'original_name'=>$originalName,'mime_type'=>$mime,
                'value'=>$value,'original'=>$original];
        };
        if(in_array($legacy,['image','xlsx'],true)) {
            $mode='files';$value=json_decode($source,true);
            $bytes=is_array($value) && is_string($value['data']??null) ? base64_decode($value['data'],true) : false;
            if($bytes===false || $bytes===''){$warnings[]='invalid_original';}
            else {$add($value['filename']??('solution.'.$legacy),$bytes,true,$value['mime_type']??($legacy==='xlsx'?'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':null));}
        } elseif(in_array($legacy,['zip','github'],true)) {
            $mode=$legacy==='github'?'repository':'files';$value=json_decode($source,true);
            if($legacy==='github')$metadata=['provider'=>'github','url'=>$value['repo_url']??null,'ref'=>$value['ref']??null,'commit_unknown'=>true];
            if(!empty($row['original_archive'])) {
                $archive=new \ZipArchive();
                if($archive->open($blob($row['original_archive']))!==true)$warnings[]='invalid_original_archive';else $archive->close();
                $files[]=['name'=>'solution.zip','original_name'=>'solution.zip','mime_type'=>'application/zip',
                    'export_file'=>$row['original_archive'],'original'=>true];
            } else {
                if($legacy==='zip')$warnings[]='missing_original_archive';
                $items=$legacy==='github'?($value['files']??null):$value;
                if(!is_array($items) || !array_is_list($items)){$warnings[]='invalid_container';$items=[];}
                foreach($items as $file) {
                    if(!is_array($file) || !is_string($file['name']??null) || !is_string($file['content']??null)){$warnings[]='invalid_file';continue;}
                    if(($file['is-binary']??false) || preg_match('/^Файл размером \d+ байт\.$/u',trim($file['content']))) {$warnings[]='missing_binary_file';continue;}
                    $add($file['name'],$file['content']);
                }
            }
        } else {
            $extension=['python'=>'py','cpp'=>'cpp','ipynb'=>'ipynb','text'=>'txt'][$legacy]??$legacy;
            $add('solution.'.$extension,$source);
            if($legacy==='ipynb'){$mode='files';if(!is_array(json_decode($source,true)))$warnings[]='invalid_notebook';}
        }
        if(!$files){$add('legacy-source.json',$source);$warnings[]='source_only';}
        if(count($files)>100)$warnings[]='execution_file_limit';
        $total=array_sum(array_map(fn($f)=>isset($f['export_file'])?$f['export_file']['bytes']:strlen($f['value']),$files));
        if($total>52428800)$warnings[]='execution_size_limit';
        return compact('language','mode','files','metadata')+['warnings'=>array_values(array_unique($warnings)),
            'requires_conversion'=>(bool)array_diff($warnings,['missing_original_archive']), 'source'=>$source];
    }
}
