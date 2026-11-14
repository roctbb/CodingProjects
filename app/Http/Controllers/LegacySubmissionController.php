<?php
namespace App\Http\Controllers;

use App\CodeSubmission;
use App\Services\CodeChecking\Artifacts;
use App\Services\CodeChecking\LegacyImport;
use Illuminate\Http\Request;

class LegacySubmissionController extends Controller
{
    public function resolve(Request $request,string $legacy,string $action='view')
    {
        $record=CodeSubmission::find(LegacyImport::id('submission',$legacy));
        abort_unless($record,404,'Это решение ещё не перенесено в Coding Projects.');
        if ($record->is_public) return redirect('/shared/code-submissions/'.$record->id.($action==='view'?'':'/'.$action))->header('Cache-Control','private, no-store');
        $suffix=$action==='raw'?'/legacy-source':'';
        if (in_array($action,['zip','image','xlsx'],true)) {
            foreach ($record->files as $i=>$file) if ($action!=='zip' || str_ends_with($file['name'],'.zip')) {$suffix='/files/'.$i;break;}
        }
        return redirect('/insider/code-submissions/'.$record->id.$suffix)->header('Cache-Control','private, no-store');
    }

    public function show(Request $request,string $submission,Artifacts $artifacts,string $action='view')
    {
        $record=CodeSubmission::where('is_public',true)->findOrFail($submission);
        if ($action==='raw') {
            $raw=$record->source_metadata['raw_source_artifact']??null;abort_unless($raw,404);
            return $artifacts->download($raw,'legacy-source.txt');
        }
        if (in_array($action,['zip','image','xlsx'],true)) {
            foreach ($record->files as $file) if ($action!=='zip' || str_ends_with($file['name'],'.zip')) return $artifacts->download($file['artifact'],$file['original_name']??$file['name']);
            abort(404,'Оригинал архива не сохранился.');
        }
        $budget=2097152;$files=[];
        foreach($record->files as $i=>$file) {
            $item=['name'=>$file['original_name']??$file['name'],'url'=>url('/shared/code-submissions/'.$record->id.'/files/'.$i)];
            if ($file['artifact']['bytes']<=$budget && preg_match('/\.(py|cpp|cc|c|h|hpp|txt|json|js|html|css|php|sql|java|cs|swift|xml|ipynb)$/iD',$file['name'])) {
                $text=$artifacts->get($file['artifact']['blob_id']);if(mb_check_encoding($text,'UTF-8')){$item['text']=$text;$budget-=strlen($text);}
            }
            $files[]=$item;
        }
        // Public sharing exposes only submitted content. Tests, prompts, grades and integrity remain private.
        return response()->view('steps.shared-submission',['files'=>$files,'text'=>$record->answer_text])->header('Cache-Control','private, no-store')->header('X-Robots-Tag','noindex');
    }

    public function file(Request $request,string $submission,int $file,Artifacts $artifacts)
    {
        $record=CodeSubmission::where('is_public',true)->findOrFail($submission);
        $item=$record->files[$file]??null;abort_unless($item,404);
        return $artifacts->download($item['artifact'],$item['original_name']??$item['name']);
    }
}
