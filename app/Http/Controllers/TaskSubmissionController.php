<?php
namespace App\Http\Controllers;

use App\CodeDraft;
use App\CodeExecutionRun;
use App\CodeSubmission;
use App\CodeTaskFile;
use App\Services\CodeChecking\Access;
use App\Services\CodeChecking\Artifacts;
use App\Services\CodeChecking\Drafts;
use App\Services\CodeChecking\Results;
use App\Services\CodeChecking\Submissions;
use App\Services\CodeChecking\TaskFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskSubmissionController extends Controller
{
    public function taskFiles(Request $request, int $course, int $task, Access $access, TaskFiles $files)
    {
        $access->task($request->user(), $course, $task, $request->isMethod('post'));
        if ($request->isMethod('post')) return $this->json($files->view($files->create($request, $task), $course), 201);
        $query = CodeTaskFile::where('task_id', $task);
        if (!$access->teacher($request->user(), $course)) $query->where('is_public', true);
        return $this->json(['items' => $query->orderBy('path')->get()->map(fn ($file) => $files->view($file, $course))->all()]);
    }

    public function taskFile(Request $request, int $course, int $task, string $attachment, Access $access, Artifacts $artifacts)
    {
        $access->task($request->user(), $course, $task, $request->isMethod('delete'));
        $file = CodeTaskFile::where('task_id', $task)->findOrFail($attachment);
        abort_unless($file->is_public || $access->teacher($request->user(), $course), 403);
        if ($request->isMethod('delete')) { $file->delete(); return $this->json(['deleted' => true]); }
        return $this->download($file->artifact, $file->original_name ?? $file->path, $artifacts);
    }

    public function replaceTaskFile(Request $request,int $course,int $task,string $attachment,Access $access,TaskFiles $files)
    {
        $access->task($request->user(),$course,$task,true);
        return $this->json($files->view($files->replace($request,$task,$attachment),$course),201);
    }

    public function draft(Request $request, int $course, int $task, Access $access, Drafts $drafts)
    {
        $model = $access->task($request->user(), $course, $task);
        if (!$request->isMethod('get')) {
            abort_if($model->isBlocked($request->user()->id, $course), 403);
            if ($request->isMethod('post')) {
                $request->validate(['from_submission' => 'required|uuid']);
                $drafts->startCorrection($request, $course, $task);
            } else {
                $drafts->save($request, $course, $task);
            }
        }
        return $this->json($drafts->get($request, $course, $task));
    }

    public function draftFile(Request $request, int $course, int $task, int $file, Access $access, Artifacts $artifacts)
    {
        $access->task($request->user(), $course, $task);
        $draft = CodeDraft::workspace($request, $course, $task)->firstOrFail();
        $item = $draft->value['files'][$file] ?? null;
        abort_unless($item, 404);
        return $this->download($item['artifact'], $item['original_name']??$item['name'], $artifacts);
    }

    public function recheckLatest(Request $request, int $course, int $task, Submissions $submissions)
    {
        $request->validate(['latest_configuration' => 'sometimes|boolean']);
        return $this->json($submissions->recheckLatest($request->user(), $course, $task, $request->boolean('latest_configuration')), 202);
    }

    public function grade(Request $request, string $submission, Access $access, Results $results)
    {
        $record = CodeSubmission::findOrFail($submission);
        $access->task($request->user(), $record->course_id, $record->task_id, true);
        $input = $request->validate(['points' => 'required|integer|min:0', 'comment' => 'present|nullable|string|max:200000']);
        DB::transaction(function () use ($record, $request, $results, $input) {
            $locked = CodeSubmission::lockForUpdate()->findOrFail($record->id);
            $run = CodeExecutionRun::lockForUpdate()->findOrFail($locked->current_run_id);
            abort_unless($run->snapshot['check_type'] === 'manual' && $locked->preparation_state === 'ready', 409);
            $data = ['execution_id' => $run->id, 'state' => (int) $input['points'] === $run->snapshot['points'] ? 'done' : 'partially done', 'points' => (int) $input['points'], 'comment' => $input['comment'] ?? ''];
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $results->accept($run, $data, $hash);
            $run->refresh();
            if (!$run->graded_by) $run->update(['graded_by' => $request->user()->id]);
        });
        return $this->json(['graded' => true]);
    }

    public function manualReview(Request $request,string $submission,Submissions $submissions)
    {
        return $this->json(['execution_id'=>$submissions->manualReview(CodeSubmission::findOrFail($submission),$request->user())->id],201);
    }

    public function reviewRequest(Request $request,string $submission,Access $access,\App\Services\CodeChecking\SubmissionReview $reviews)
    {
        $record=CodeSubmission::findOrFail($submission);$access->authorOrTeacher($request->user(),$record);
        abort_unless((int)$record->user_id===(int)$request->user()->id,403);
        $input=$request->validate(['comment'=>'required|string|min:10|max:1000']);
        return $this->json($reviews->request($record,$input['comment']));
    }

    public function flags(Request $request,string $submission,Access $access)
    {
        $record=CodeSubmission::findOrFail($submission);$access->authorOrTeacher($request->user(),$record);
        $data=$request->validate(['teacher_viewed'=>'sometimes|boolean','is_public'=>'sometimes|boolean']);
        if (array_key_exists('teacher_viewed',$data)) abort_unless($access->teacher($request->user(),$record->course_id),403);
        if (array_key_exists('is_public',$data)) abort_unless((int)$record->user_id===(int)$request->user()->id
            && (!$data['is_public'] || $access->teacher($request->user(),$record->course_id)),403);
        $record->update($data);
        return $this->json(['teacher_viewed'=>$record->teacher_viewed,'is_public'=>$record->is_public]);
    }

    public function compare(Request $request,string $submission,Access $access,Artifacts $artifacts)
    {
        $record=CodeSubmission::findOrFail($submission);$access->authorOrTeacher($request->user(),$record);
        $data=$request->validate(['with'=>'required|uuid']);
        $other=CodeSubmission::where('user_id',$record->user_id)->where('course_id',$record->course_id)->where('task_id',$record->task_id)->findOrFail($data['with']);
        $access->authorOrTeacher($request->user(),$other);
        $project=function($item)use($artifacts){
            $budget=2097152;$files=[];
            foreach($item->files as $file){
                $value=['name'=>$file['name'],'bytes'=>$file['artifact']['bytes'],'sha256'=>$file['artifact']['sha256']];
                if($value['bytes']<=$budget && preg_match('/\.(py|cpp|cc|c|h|hpp|txt|json|js|html|css|php|sql|java|cs|swift|xml|ipynb)$/iD',$file['name'])){
                    $text=$artifacts->get($file['artifact']['blob_id']);
                    if(mb_check_encoding($text,'UTF-8')){$value['text']=$text;$budget-=strlen($text);}
                }
                $files[]=$value;
            }
            return ['id'=>$item->id,'text'=>$item->answer_text,'files'=>$files];
        };
        return $this->json(['before'=>$project($other),'after'=>$project($record)]);
    }

    public function snapshotFile(Request $request, string $submission, int $file, Access $access, Artifacts $artifacts)
    {
        $record = CodeSubmission::findOrFail($submission);
        $access->authorOrTeacher($request->user(), $record);
        $item = $record->currentRun->snapshot['task_files'][$file] ?? null;
        abort_unless($item, 404);
        abort_unless($item['is_public'] || $access->teacher($request->user(), $record->course_id), 403);
        return $this->download($item['artifact'], $item['original_name']??$item['name'], $artifacts);
    }

    public function repositoryArchive(Request $request, string $submission, Access $access, Artifacts $artifacts)
    {
        $record = CodeSubmission::findOrFail($submission);
        $access->authorOrTeacher($request->user(), $record);
        abort_unless(isset($record->source_metadata['archive']), 404);
        return $this->download($record->source_metadata['archive'], 'repository.zip', $artifacts);
    }

    public function bundle(Request $request,string $submission,Access $access,\App\Services\CodeChecking\SubmissionBundle $bundle)
    {
        $record=CodeSubmission::findOrFail($submission);$access->authorOrTeacher($request->user(),$record);
        return $bundle->download($record);
    }

    public function run(Request $request, string $submission, string $run, Access $access)
    {
        $record = CodeSubmission::findOrFail($submission);
        $access->authorOrTeacher($request->user(), $record);
        $item = CodeExecutionRun::where('submission_id', $record->id)->findOrFail($run);
        $request->validate(['test_data'=>'sometimes|in:metadata,full']);$fields=$request->input('test_data')!=='metadata';
        $native=\App\CodeTestReport::find($run);
        $report=$native?app(\App\Services\CodeChecking\Reports::class)->view($native,$request->user(),$fields)
            :(isset($item->legacy_report['suite_diagnostics'])?null:app(\App\Services\CodeChecking\ArchivedTests::class)->view($item,$request->user(),$fields));
        if($report){$report['run_id']=$run;if(!$fields)$report=app(\App\Services\CodeChecking\Reports::class)->metadata($report);}
        $value = ['id' => $item->id, 'state' => $item->state, 'points' => $item->points,
            'check_type' => $item->snapshot['check_type'] ?? null,
            'review_comment' => $access->teacher($request->user(), $record->course_id) ? ($item->snapshot['review_comment'] ?? null) : null,
            'execution_error' => app(\App\Services\CodeChecking\Submissions::class)->executionError($record, $item),
            'ai_recheck' => app(\App\Services\CodeChecking\Submissions::class)->aiRecheck($record, $item, $request->user()),
            'comment' => $item->state === 'error' || (!$fields&&($report || ($item->snapshot['check_type']??null)==='tests'))?'':$item->comment, 'progress' => $item->progress,
            'text_report_available'=>$item->state !== 'error' && !$fields && !$report && ($item->snapshot['check_type']??null)==='tests' && (bool)($item->comment || isset($item->legacy_report['suite_diagnostics'])),
            'text_report_url'=>url('/insider/code-submissions/'.$record->id.'/runs/'.$run).'?test_data=full',
            'checker_diagnostics'=>$fields && $access->teacher($request->user(),$record->course_id)?($item->legacy_report['suite_diagnostics']??null):null];
        $value['report']=$report;
        $value['files']=[];
        foreach ($item->snapshot['legacy_submission']['files']??[] as $index=>$file) $value['files'][]=['name'=>$file['original_name']??$file['name'],'download_url'=>url('/insider/code-submissions/'.$record->id.'/runs/'.$item->id.'/files/'.$index)];
        if ($fields && $access->teacher($request->user(), $record->course_id)) $value['configuration'] = $item->snapshot;
        return $this->json($value);
    }

    public function runTest(Request $request,string $submission,string $run,int $number,Access $access)
    {
        $record=CodeSubmission::findOrFail($submission);$access->authorOrTeacher($request->user(),$record);
        $item=CodeExecutionRun::where('submission_id',$submission)->findOrFail($run);
        if($native=\App\CodeTestReport::find($run))return $this->json(app(\App\Services\CodeChecking\Reports::class)->test($native,$number,$request->user()));
        return $this->json(app(\App\Services\CodeChecking\ArchivedTests::class)->test($item,$number,$request->user()));
    }

    public function buyArchivedTest(Request $request,string $submission,string $run,int $number,Access $access)
    {
        $record=CodeSubmission::findOrFail($submission);$access->authorOrTeacher($request->user(),$record);
        $item=CodeExecutionRun::where('submission_id',$submission)->findOrFail($run);
        $data=$request->validate(['expected_price'=>'required|integer|min:0']);
        return $this->json(app(\App\Services\CodeChecking\ArchivedTests::class)->buy($item,$number,$request->user(),$data['expected_price']));
    }

    public function archivedTestFile(Request $request,string $submission,string $run,int $number,string $field,Access $access)
    {
        $record=CodeSubmission::findOrFail($submission);$access->authorOrTeacher($request->user(),$record);
        $item=CodeExecutionRun::where('submission_id',$submission)->findOrFail($run);
        return app(\App\Services\CodeChecking\ArchivedTests::class)->download($item,$number,$field,$request->user());
    }

    public function runFile(Request $request,string $submission,string $run,int $file,Access $access,Artifacts $artifacts)
    {
        $record=CodeSubmission::findOrFail($submission);$access->authorOrTeacher($request->user(),$record);
        $item=CodeExecutionRun::where('submission_id',$submission)->findOrFail($run);
        $source=$item->snapshot['legacy_submission']['files'][$file]??null;abort_unless($source,404);
        return $artifacts->download($source['artifact'],$source['original_name']??$source['name']);
    }

    public function legacyExports(Request $request)
    {
        abort_unless($request->user()->role==='admin',403);
        return $this->json(['items'=>DB::table('code_legacy_exports')->orderByDesc('created_at')->get()->map(fn($row)=>[
            'id'=>$row->id,'manifest'=>json_decode($row->manifest,true),'files'=>array_keys(json_decode($row->files,true)),
            'created_at'=>$row->created_at])->all()]);
    }

    public function exportFile(Request $request,string $export,string $file,Artifacts $artifacts)
    {
        abort_unless($request->user()->role==='admin',403);
        $row=DB::table('code_legacy_exports')->find($export);abort_unless($row,404);
        $artifact=json_decode($row->files,true)[$file]??null;abort_unless($artifact,404);
        return $artifacts->download($artifact,$file);
    }

    public function archiveArtifact(Request $request,string $artifact,Artifacts $artifacts)
    {
        abort_unless($request->user()->role==='admin',403);
        return $artifacts->download(['blob_id'=>$artifact],$artifact);
    }

    public function legacySettings(Request $request, int $course, int $task, Access $access)
    {
        $access->task($request->user(), $course, $task, true);
        return $this->json(['items' => DB::table('code_legacy_tasks')->where('task_id', $task)->get()->map(fn ($row) => [
            'legacy_id' => $row->legacy_id, 'definition' => json_decode($row->definition, true),
            'versions'=>DB::table('code_legacy_task_versions')->where('legacy_id',$row->legacy_id)->get()->map(fn($v)=>['hash'=>$v->id,'definition'=>json_decode($v->definition,true)])->all()])->all()]);
    }

    public function reviewIntegrity(Request $request, string $submission, Access $access)
    {
        $record = CodeSubmission::findOrFail($submission);
        $access->task($request->user(), $record->course_id, $record->task_id, true);
        $input = $request->validate(['dismissed' => 'required|boolean', 'comment' => 'nullable|string|max:10000']);
        abort_unless(DB::table('code_integrity_reports')->where('submission_id', $record->id)->exists(), 404);
        DB::transaction(function () use ($record, $input, $request) {
            DB::table('code_integrity_reports')->where('submission_id', $record->id)->update([
                'dismissed_at' => $input['dismissed'] ? now() : null, 'dismissed_by' => $input['dismissed'] ? $request->user()->id : null,
                'review_comment' => $input['comment'] ?? null, 'updated_at' => now()]);
            if ($record->solution_id) {
                DB::table('solutions')->where('id', $record->solution_id)->update([
                    'geekpaste_integrity_dismissed_at' => $input['dismissed'] ? now() : null,
                    'geekpaste_integrity_dismissed_by' => $input['dismissed'] ? $request->user()->id : null]);
                $payload = app(\App\Services\CodeChecking\Integrity::class)->legacyPayload($record);
                DB::table('solutions')->where('id', $record->solution_id)->update([
                    'geekpaste_integrity_dismissed_at' => $input['dismissed'] ? now() : null,
                    'geekpaste_integrity_dismissed_by' => $input['dismissed'] ? $request->user()->id : null,
                    'geekpaste_ai_warning' => $input['dismissed'] ? false : ($payload['ai']['warning'] ?? false),
                    'geekpaste_similarity_warning' => $input['dismissed'] ? false : $payload['similarity']['warning'],
                    'geekpaste_similarity_critical' => $input['dismissed'] ? false : $payload['similarity']['critical']]);
            }
        });
        return $this->json(['reviewed' => true]);
    }

    public function legacySource(Request $request, string $submission, Access $access, Artifacts $artifacts)
    {
        $record = CodeSubmission::findOrFail($submission);
        $access->authorOrTeacher($request->user(), $record);
        abort_unless(isset($record->source_metadata['raw_source_artifact']), 404);
        return $this->download($record->source_metadata['raw_source_artifact'], 'legacy-source.txt', $artifacts);
    }

    public function fixture(Request $request, int $course, int $task, string $artifact, Access $access, Artifacts $artifacts)
    {
        $access->task($request->user(), $course, $task, true);
        abort_if($request->user()->role!=='admin' && app(\App\Services\CodeChecking\LegacySuites::class)->isBundleArtifact($artifact),403);
        $ids = [];
        $visit = function ($value) use (&$visit, &$ids) {
            if (!is_array($value)) return;
            if (($value['type'] ?? null) === 'blob') $ids[] = $value['blob_id'];
            else foreach ($value as $child) $visit($child);
        };
        $visit(\App\CodeTaskChecker::find($task)?->config);
        foreach (DB::table('code_legacy_tasks')->where('task_id', $task)->get() as $row) $visit(json_decode($row->definition, true));
        $legacyIds=DB::table('code_legacy_tasks')->where('task_id',$task)->pluck('legacy_id');
        foreach (DB::table('code_legacy_task_versions')->whereIn('legacy_id',$legacyIds)->cursor() as $version) $visit(json_decode($version->definition,true));
        abort_unless(in_array($artifact, $ids, true) || DB::table('code_fixture_uploads')->where('task_id', $task)->where('artifact_id', $artifact)->exists(), 403);
        abort_unless($artifacts->exists($artifact), 404);
        return $this->download(['blob_id' => $artifact], 'test-data.bin', $artifacts);
    }

    private function download(array $artifact, string $name, Artifacts $artifacts)
    {
        return $artifacts->download($artifact, $name);
    }

    private function json(array $value, int $status = 200)
    {
        return response()->json($value, $status)->header('Cache-Control', 'private, no-store');
    }
}
