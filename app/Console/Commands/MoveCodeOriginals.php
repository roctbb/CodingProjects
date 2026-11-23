<?php
namespace App\Console\Commands;

use App\Services\CodeChecking\Artifacts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MoveCodeOriginals extends Command
{
    protected $signature='code-checking:prepare-storage {--apply : Copy and verify; retain legacy objects} {--actor= : Administrator ID}';
    protected $description='Inspect or copy original materials to the private originals disk and text artifacts into the database';

    public function handle(Artifacts $artifacts): int
    {
        if ($this->option('apply') && \App\User::find($this->option('actor'))?->role!=='admin') {$this->error('--actor must identify an administrator.');return self::FAILURE;}
        $originals=[]; $textIds=[];
        $mark=function($value)use(&$originals){if(is_array($value) && isset($value['blob_id']))$originals[$value['blob_id']]=true;};
        foreach (\App\CodeTaskFile::withTrashed()->cursor() as $file) {
            $mark($file->artifact);
            if (preg_match('/\.(py|cpp|cc|c|h|hpp|txt|json|js|html|css|php|sql|java|cs|swift|xml|ipynb)$/iD',$file->path)) $textIds[$file->artifact['blob_id']]=true;
        }
        foreach (\App\CodeSubmission::cursor() as $submission) {
            foreach($submission->files as $file) {
                if(isset($file['original_name']) || in_array($submission->mode,['files','repository'],true)) $mark($file['artifact']);
                if (preg_match('/\.(py|cpp|cc|c|h|hpp|txt|json|js|html|css|php|sql|java|cs|swift|xml|ipynb)$/iD',$file['name'])) $textIds[$file['artifact']['blob_id']]=true;
            }
            $mark($submission->source_metadata['archive']??null);
        }
        foreach (['code_drafts', 'code_submission_drafts'] as $table) {
            if (!\Illuminate\Support\Facades\Schema::hasTable($table)) continue;
            foreach ((new \App\CodeDraft)->setTable($table)->newQuery()->cursor() as $draft) {
                foreach ($draft->value['files'] ?? [] as $file) if (isset($file['original_name'])) $mark($file['artifact']);
            }
        }
        $counts=['apply'=>(bool)$this->option('apply'),'originals'=>0,'text'=>0,'verified'=>0,'missing'=>[],'failed'=>[]];
        foreach(DB::table('code_artifacts')->orderBy('id')->cursor() as $row) {
            $descriptor=['blob_id'=>$row->id,'sha256'=>$row->id,'bytes'=>(int)$row->bytes];
            try {
                if(!$artifacts->exists($row->id,(int)$row->bytes)){$counts['missing'][]=$row->id;continue;}
                $artifacts->withLocalFile($descriptor,function($path)use($row,$artifacts,&$counts,$originals,$textIds){
                    $text=file_get_contents($path); $utf8=mb_check_encoding($text,'UTF-8');
                    $original=isset($originals[$row->id]) || !$utf8;
                    $counts[$original?'originals':'text']++;
                    if($this->option('apply')) {
                        if($original)$artifacts->putFile($path);
                        // Valid text, including an uploaded source's textual copy, stays in DB.
                        if($utf8 && (!$original || isset($textIds[$row->id])))$artifacts->put($text);
                    }
                    $counts['verified']++;
                });
            }catch(\Throwable $e){$counts['failed'][]=['id'=>$row->id,'error'=>$e->getMessage()];}
        }
        $this->line(json_encode($counts,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        return ($counts['missing'] || $counts['failed']) ? self::FAILURE : self::SUCCESS;
    }
}
