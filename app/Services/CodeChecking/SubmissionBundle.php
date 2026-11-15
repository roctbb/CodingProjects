<?php

namespace App\Services\CodeChecking;

use App\CodeSubmission;

/** Packages the saved bytes without fetching a newer repository or exposing storage locations. */
final class SubmissionBundle
{
    public function __construct(private Artifacts $artifacts) {}

    public function download(CodeSubmission $submission)
    {
        $files=$submission->files ?? [];
        abort_unless(count($files)>1,404);
        abort_if(count($files)>2000 || array_sum(array_column(array_column($files,'artifact'),'bytes'))>268435456,422,'Пакет слишком большой. Скачайте файлы отдельно.');
        $directory=sys_get_temp_dir().'/cp-submission-'.bin2hex(random_bytes(12));
        abort_unless(mkdir($directory,0700),503);
        $archivePath=tempnam(sys_get_temp_dir(),'cp-submission-zip-');
        $zip=new \ZipArchive();$ready=false;$opened=false;
        try {
            abort_unless($zip->open($archivePath,\ZipArchive::OVERWRITE)===true,503,'Не удалось создать архив.');$opened=true;
            $names=[];
            foreach($files as $index=>$file){
                $name=Paths::validate($file['name']);
                abort_if(isset($names[$name]),422,'Имена файлов в архиве повторяются.');$names[$name]=true;
                $path=$directory.'/'.$index;$input=$this->artifacts->readStream($file['artifact']['blob_id']);$output=fopen($path,'wb');
                try {stream_copy_to_stream($input,$output);} finally {fclose($input);fclose($output);}
                abort_unless(filesize($path)===$file['artifact']['bytes'] && hash_equals($file['artifact']['sha256'],hash_file('sha256',$path)),503,'Не удалось прочитать сохранённый файл.');
                abort_unless($zip->addFile($path,$name),503);
            }
            abort_unless($zip->close(),503);$opened=false;$ready=true;
            return response()->download($archivePath,'solution-'.$submission->id.'.zip',['Content-Type'=>'application/zip','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff'])->deleteFileAfterSend(true);
        } finally {
            if($opened)$zip->close();
            foreach(glob($directory.'/*') as $path)@unlink($path);
            @rmdir($directory);if(!$ready)@unlink($archivePath);
        }
    }
}
