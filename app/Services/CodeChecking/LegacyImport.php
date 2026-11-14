<?php
namespace App\Services\CodeChecking;

use App\CodeExecutionRun;
use App\CodeSubmission;
use App\CodeTaskChecker;
use App\Course;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;

class LegacyImport
{
    private string $root;
    private array $map;
    private array $tasks = [];
    private string $timezone;
    private bool $refresh;
    private array $records = [];
    private array $taskPlan = [];

    public function __construct(private Artifacts $artifacts, private Integrity $integrity) {}

    public static function id(string $kind, mixed $id): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'GeekPasteV2/'.$kind.'/'.$id)->toString();
    }

    public function run(string $directory, array $map, bool $apply = false, ?int $actor = null, string $timezone = 'UTC', bool $refreshChanged = false, ?string $report = null, ?array $taskPlan = null): array
    {
        $this->root = realpath($directory) ?: throw new \InvalidArgumentException('Export directory does not exist.');
        $this->map = $map; $this->tasks = []; $this->records = []; $this->timezone = $timezone; $this->refresh = $refreshChanged;
        new \DateTimeZone($timezone);
        $this->taskPlan = $taskPlan['tasks'] ?? [];
        if ($taskPlan !== null && (($taskPlan['schema_version'] ?? null) !== 1
            || !hash_equals($taskPlan['source_manifest_sha256'] ?? '', hash_file('sha256', $this->root.'/manifest.json')))) {
            throw new \InvalidArgumentException('Task plan does not match this export.');
        }
        $manifest = json_decode(file_get_contents($this->root.'/manifest.json'), true, 64, JSON_THROW_ON_ERROR);
        if (!in_array($manifest['schema_version'] ?? null, [1,2], true) || ($manifest['source'] ?? null) !== 'GeekPasteV2') throw new \InvalidArgumentException('Unsupported export.');
        foreach (array_unique(array_merge(['tasks.jsonl','solutions.jsonl','similarities.jsonl'],array_keys($manifest['files']??[]))) as $name) {
            if (!preg_match('/^[a-zA-Z0-9._-]+$/D',$name)) throw new \InvalidArgumentException('Invalid export sidecar path.');
            if (!hash_equals($manifest['files'][$name] ?? '', hash_file('sha256', $this->root.'/'.$name))) throw new \InvalidArgumentException('Export checksum mismatch: '.$name);
        }
        $summary = ['apply'=>$apply,'tasks'=>0,'solutions'=>0,'already_imported'=>0,'changed'=>0,'needs_conversion'=>0,
            'missing_original_archives'=>$manifest['counts']['missing_original_archives'] ?? 0,'warnings'=>0,'issues'=>[]];
        foreach ($this->rows('tasks.jsonl') as $row) {
            $id = (string) $row['id']; $issues=[]; $warnings=[];
            $effective=$this->settings($row);
            $target = $map['tasks'][$id] ?? null;
            if (!$target || !Task::find($target)) $issues[]='missing_task_mapping';
            if (isset($this->tasks[$id])) $issues[]='duplicate_task_id';
            try {
                foreach ($row['test_config']['tests'] ?? [] as $test) foreach (['input','output'] as $field) if (is_array($test[$field] ?? null)) $this->blob($test[$field]);
                if ($taskPlan !== null) $effective=$this->plannedSettings($row, $target);
            } catch (\Throwable $e) {$issues[]=$e->getMessage();}
            $existing=DB::table('code_legacy_tasks')->where('legacy_id',$id)->first();
            if ($existing && ((int)$existing->task_id !== (int)$target || (!$refreshChanged && $existing->definition_hash !== $this->fingerprint($row)))) $issues[]='conflicting_imported_task';
            $this->tasks[$id]=$row; $summary['tasks']++;
            if ($effective['requires_conversion']) {$summary['needs_conversion']++;$warnings[]='checker_requires_conversion';}
            $this->record($summary,'task',$id,$issues,$warnings);
        }
        $ids=[];
        foreach ($this->rows('solutions.jsonl') as $row) {
            $id=(string)$row['id']; $issues=[]; $warnings=[];
            if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$id)) $issues[]='invalid_legacy_id';
            if (isset($ids[$id])) $issues[]='duplicate_solution_id';
            $ids[$id]=true;
            try {
                if (!isset($this->tasks[(string)$row['task_id']])) throw new \InvalidArgumentException('missing_exported_task');
                $plan=$this->plan($row); $warnings=$plan['warnings'];
                $task=Task::with('step')->find($map['tasks'][(string)$row['task_id']] ?? 0);
                $course=Course::find($this->mapped('courses',$row['course_id'] ?? null));
                $user=User::find($this->mapped('users',$row['user_id'] ?? null));
                if (!$course || !$task?->step || (int)$task->step->program_id !== (int)$course->program_id || !$user) $issues[]='unresolved_owner_course_task';
                foreach (['created_at','checked_at'] as $field) {
                    if (empty($row[$field])) $warnings[]='unknown_'.$field; else $this->date($row[$field]);
                }
                if (isset($row['check_points']) && (!is_numeric($row['check_points']) || $row['check_points'] < 0)) $issues[]='invalid_grade';
                $existing=CodeSubmission::find(self::id('submission',$id));
                if ($existing) {
                    $summary['already_imported']++;
                    if ((int)$existing->task_id !== (int)$task?->id || (int)$existing->course_id !== (int)$course?->id || (int)$existing->user_id !== (int)$user?->id) $issues[]='changed_owner_course_task';
                    if (($existing->source_metadata['import_fingerprint'] ?? null) !== $this->fingerprint($row)) {
                        $summary['changed']++;
                        if (!$refreshChanged) $issues[]='changed_imported_solution';
                        if (!($existing->currentRun?->snapshot['legacy'] ?? false) || $existing->currentRun?->graded_by) $issues[]='native_review_must_not_be_overwritten';
                    }
                }
                if ($task && $course && $user && !$this->linkedSolutions($row,$task->id,$course->id,$user->id)) $warnings[]='archive_without_grade_link';
                if ($plan['requires_conversion']) $warnings[]='source_requires_conversion';
            } catch (\Throwable $e) {$issues[]=$e->getMessage();}
            $summary['solutions']++; $this->record($summary,'solution',$id,$issues,$warnings);
        }
        $edges=[];
        foreach ($this->rows('similarities.jsonl') as $row) {
            $id=($row['code_id']??'?').'/'.($row['code_id2']??'?'); $issues=[];
            if (!isset($ids[(string)($row['code_id']??'')],$ids[(string)($row['code_id2']??'')])) $issues[]='unresolved_similarity_endpoint';
            if (!is_numeric($row['percent']??null) || $row['percent']<0 || $row['percent']>100) $issues[]='invalid_similarity_percent';
            if (($row['code_id']??null)===($row['code_id2']??null)) $issues[]='self_similarity';
            $pair=[(string)($row['code_id']??''),(string)($row['code_id2']??'')];sort($pair,SORT_STRING);$edge=implode('/',$pair);
            if (isset($edges[$edge]) && $edges[$edge]!==$row['percent']) $issues[]='conflicting_similarity';
            $edges[$edge]=$row['percent']??null;
            $this->record($summary,'similarity',$id,$issues,[]);
        }
        if ($report) {
            $handle=fopen($report,'xb'); if ($handle) chmod($report,0600); if (!$handle) throw new \InvalidArgumentException('Report already exists or cannot be written.');
            try {foreach ($this->records as $record) fwrite($handle,json_encode($record,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");} finally {fclose($handle);}
        }
        if (!$apply) return $summary;
        if ($summary['issues']) throw new \InvalidArgumentException('Resolve preflight issues before import: '.implode('; ',array_slice($summary['issues'],0,20)));
        if (!$actor || User::find($actor)?->role !== 'admin') throw new \InvalidArgumentException('--actor must identify an administrator.');
        $sidecars=[];
        foreach (['environments.tar.gz','service-state.json','user-profiles.jsonl','rate-state.jsonl','deferred-records.jsonl','source-manifest.json','migration-plan.json'] as $name) if (isset($manifest['files'][$name])) $sidecars[$name]=$this->artifacts->putFile($this->root.'/'.$name,!in_array($name,['deferred-records.jsonl','source-manifest.json','migration-plan.json'],true));
        DB::table('code_legacy_exports')->insertOrIgnore(['id'=>hash_file('sha256',$this->root.'/manifest.json'),'manifest'=>json_encode($manifest,JSON_THROW_ON_ERROR),
            'files'=>json_encode($sidecars,JSON_THROW_ON_ERROR),'created_by'=>$actor,'created_at'=>now()]);
        foreach ($this->tasks as $legacyId=>$row) DB::transaction(function () use ($legacyId,$row,$actor) {
            $taskId=$this->map['tasks'][(string)$legacyId]; $definition=$row;
            $planned=$this->taskPlan ? $this->plannedSettings($row,$taskId) : null;
            $changes=array_intersect_key($this->taskPlan[(string)$legacyId]??[],array_flip(['set_public_text','set_max_mark']));
            if ($changes) Task::findOrFail($taskId)->update(array_combine(array_map(fn($k)=>$k==='set_public_text'?'text':'max_mark',array_keys($changes)),array_values($changes)));
            foreach ($definition['test_config']['tests'] ?? [] as $i=>$test) foreach (['input','output'] as $field) {
                if (is_string($test[$field]??null)) $definition['test_config']['tests'][$i][$field]=$this->artifacts->put(str_replace(["\r\n","\r"],"\n",$test[$field]));
                elseif (is_array($test[$field]??null)) $definition['test_config']['tests'][$i][$field]=$this->artifacts->putFile($this->blob($test[$field]),false);
            }
            $hash=$this->fingerprint($row);
            DB::table('code_legacy_task_versions')->insertOrIgnore(['id'=>$hash,'legacy_id'=>(string)$legacyId,'definition'=>json_encode($definition,JSON_THROW_ON_ERROR),'created_at'=>now()]);
            DB::table('code_legacy_tasks')->updateOrInsert(['legacy_id'=>(string)$legacyId],['task_id'=>$taskId,'definition_hash'=>$hash,'definition'=>json_encode($definition,JSON_THROW_ON_ERROR),'created_at'=>now()]);
            // Never replace a teacher's current settings with a historical definition.
            if (!CodeTaskChecker::find($taskId) && ($this->taskPlan[(string)$legacyId]['action'] ?? 'configure') !== 'archive_only') {
                $config=$planned ?? $this->settings($definition);
                foreach ($config['test_config']['tests'] ?? [] as $i=>$test) foreach (['input','output'] as $field) {
                    if (is_string($test[$field]??null)) $config['test_config']['tests'][$i][$field]=$this->artifacts->put(str_replace(["\r\n","\r"],"\n",$test[$field]));
                    elseif (isset($test[$field]['path'])) $config['test_config']['tests'][$i][$field]=$this->artifacts->putFile($this->blob($test[$field]),false);
                }
                if (($config['test_mode']??null)==='suite') $config['test_config']['bundle']=$this->artifacts->putFile($this->blob($config['test_config']['bundle']),false);
                CodeTaskChecker::create(['task_id'=>$taskId,'config'=>$config,'revision'=>$this->fingerprint($config),'updated_by'=>$actor,'allows_test_reveals'=>($config['test_mode']??null)!=='suite']);
            }
        });
        foreach ($this->rows('solutions.jsonl') as $row) DB::transaction(fn()=> $this->solution($row));
        foreach ($this->rows('similarities.jsonl') as $row) foreach ([[$row['code_id'],$row['code_id2']],[$row['code_id2'],$row['code_id']]] as [$source,$other]) {
            $id=self::id('submission',$source); $otherId=self::id('submission',$other);
            $record=DB::table('code_integrity_reports')->where('submission_id',$id)->first();
            $value=json_decode($record->plagiarism,true);
            if (!in_array($otherId,array_column($value['matches'],'submission_id'),true)) $value['matches'][]=['submission_id'=>$otherId,'percent'=>$row['percent']];
            DB::table('code_integrity_reports')->where('submission_id',$id)->update(['plagiarism'=>json_encode($value,JSON_THROW_ON_ERROR)]);
        }
        return $summary;
    }

    private function record(array &$summary,string $kind,string $id,array $issues,array $warnings): void
    {
        foreach ($issues as $issue) $summary['issues'][]=$kind.' '.$id.': '.$issue;
        $summary['warnings']+=count(array_unique($warnings));
        $this->records[]=['kind'=>$kind,'id'=>$id,'issues'=>$issues,'warnings'=>array_values(array_unique($warnings))];
    }

    private function settings(array $row): array
    {
        $format=LegacySubmissionFormat::settings($row['lang']??null);
        $language=$format['language']??'text';
        $type=($row['check_type']??'tests')==='gpt'?'gpt':'tests';
        $io=$row['test_config']??null; $invalidIo=false;
        if ($type==='tests' && ($row['test_mode']??'environment')==='io') {
            try {$io=app(Configuration::class)->io($io??[],(int)($row['points']??0),function($value){
                if (is_string($value)) return ['type'=>'blob','blob_id'=>hash('sha256',$value),'sha256'=>hash('sha256',$value),'bytes'=>strlen($value),'truncated'=>false];
                if (!is_array($value) || !is_int($value['bytes']??null) || $value['bytes']<0 || $value['bytes']>Artifacts::MAX_TEST_BYTES || !preg_match('/^[a-f0-9]{64}$/D',$value['sha256']??'')) throw new \InvalidArgumentException('Invalid test field');
                return ['type'=>'blob','blob_id'=>$value['sha256'],'sha256'=>$value['sha256'],'bytes'=>$value['bytes'],'truncated'=>false];
            });} catch (\Throwable) {$invalidIo=true;}
        }
        return ['language'=>$language,'allowed_languages'=>[$language],'check_type'=>$type,
            'test_mode'=>$row['test_mode']??'environment','test_config'=>$io,
            'private_instructions'=>$row['text']??'','gpt_model'=>$row['gpt_model']??null,'gpt_rate_limit'=>$row['gpt_rate_limit']??3,
            ...($format??['submission_modes'=>[],'allowed_extensions'=>[],'max_files'=>1]),
            'max_file_bytes'=>52428800,'max_total_bytes'=>52428800,
            'bypass_similarity_check'=>(bool)($row['bypass_similarity_check']??false),'ai_authorship_check'=>true,
            'requires_conversion'=>!$format || !empty($row['migration_archive_only']) || ($type==='tests' && ($invalidIo || ($row['test_mode']??'environment')!=='io' || !in_array($language,['python','cpp'],true)))];
    }

    private function solution(array $row): void
    {
        $id=self::id('submission',$row['id']); $fingerprint=$this->fingerprint($row);
        $existing=CodeSubmission::whereKey($id)->lockForUpdate()->first();
        if ($existing && ($existing->source_metadata['import_fingerprint']??null)===$fingerprint) return;
        $taskId=$this->map['tasks'][(string)$row['task_id']]; $courseId=$this->mapped('courses',$row['course_id']); $userId=$this->mapped('users',$row['user_id']);
        $plan=$this->plan($row); $raw=$this->artifacts->putFile($this->blob($row['source']),false);
        $files=[];
        foreach ($plan['files'] as $file) {
            $artifact=isset($file['export_file']) ? $this->artifacts->putFile($this->blob($file['export_file'])) :
                ($file['original'] ? $this->artifacts->putOriginal($file['value']) : $this->artifacts->put($file['value']));
            $files[]=['name'=>$file['name'],'original_name'=>$file['original_name'],'mime_type'=>$file['mime_type'],'artifact'=>$artifact];
        }
        $legacyTask=json_decode(DB::table('code_legacy_tasks')->where('legacy_id',(string)$row['task_id'])->value('definition'),true);
        $settings=$this->settings($legacyTask); $settings['language']=$plan['language'];
        $created=$this->date($row['created_at']??null); $finished=!empty($row['checked_at'])?$this->date($row['checked_at']):$created;
        $runId=$existing ? self::id('run',$row['id'].'/'.$fingerprint) : self::id('run',$row['id']);
        $metadata=$plan['metadata']+['legacy_id'=>$row['id'],'raw_source_artifact'=>$raw,'legacy_metadata'=>array_diff_key($row,['source'=>true]),
            'requires_conversion'=>$plan['requires_conversion'],'migration_warnings'=>$plan['warnings'],'import_fingerprint'=>$fingerprint];
        $values=['user_id'=>$userId,'course_id'=>$courseId,'task_id'=>$taskId,'legacy_id'=>(string)$row['id'],
            'request_id'=>self::id('request',$row['id']),'payload_hash'=>$row['source']['sha256'],'language'=>$plan['language'],
            'mode'=>$plan['mode'],'answer_text'=>'','files'=>$files,'source_metadata'=>$metadata,'preparation_state'=>'ready','current_run_id'=>$runId,
            'teacher_viewed'=>(bool)($row['viewed_by_teacher']??$row['viewed']??false),'is_public'=>(bool)($row['available_without_auth']??$row['public']??$row['is_public']??false),'updated_at'=>$created];
        if ($existing && !isset($existing->currentRun->snapshot['legacy_submission'])) {
            $previous=$existing->currentRun; $oldSnapshot=$previous->snapshot;
            $oldSnapshot['legacy_submission']=['files'=>$existing->files,'raw_source_artifact'=>$existing->source_metadata['raw_source_artifact']??null,'language'=>$existing->language];
            $previous->update(['snapshot'=>$oldSnapshot]);
        }
        $submission=$existing ?: new CodeSubmission(['id'=>$id,'created_at'=>$created]);
        $submission->forceFill($values)->save();
        $snapshot=array_merge($settings,['task_id'=>$taskId,'name'=>$legacyTask['name']??'','public_text'=>Task::findOrFail($taskId)->text,
            'points'=>(int)($legacyTask['points']??Task::findOrFail($taskId)->max_mark),'task_files'=>[],'legacy'=>true,'legacy_submission'=>['files'=>$files,'raw_source_artifact'=>$raw,'language'=>$plan['language']], 'legacy_definition_hash'=>$this->fingerprint($this->tasks[(string)$row['task_id']])]);
        CodeExecutionRun::create(['id'=>$runId,'submission_id'=>$id,'snapshot'=>$snapshot,
            'state'=>($row['check_state']??'')==='execution error'?'error':(in_array($row['check_state']??'', ['done','partially done'],true)?'completed':'imported_pending'),
            'points'=>$row['check_points']??null,'result_hash'=>hash('sha256','legacy:'.$fingerprint),'comment'=>$row['check_comments']??'',
            'legacy_report'=>app(HistoricalReport::class)->parse($row['check_comments']??null),'finished_at'=>$finished,'dispatched_at'=>$created,'created_at'=>$created,'updated_at'=>$created]);
        DB::table('code_integrity_reports')->updateOrInsert(['submission_id'=>$id],['state'=>'completed',
            'plagiarism'=>json_encode(['state'=>($row['similarity_checked']??false)?'done':'skipped','warning'=>(bool)($row['has_similarity_warning']??false),'matches'=>[],
                'legacy_critical'=>(bool)($row['has_critical_similarity_warning']??false)],JSON_THROW_ON_ERROR),
            'ai'=>json_encode(['state'=>'imported','warning'=>(bool)($row['has_ai_warning']??false),'confidence'=>$row['ai_confidence']??null,
                'reasons'=>empty($row['ai_warning_reasons'])?[]:[$row['ai_warning_reasons']],'llm_probability'=>$row['gpt_llm_probability']??null],JSON_THROW_ON_ERROR),
            'created_at'=>$created,'updated_at'=>$created]);
        DB::table('code_fingerprints')->where('submission_id',$id)->delete();
        if (!$settings['bypass_similarity_check'] && ($text=$this->integrity->source($submission))) foreach (array_chunk($this->integrity->fingerprints($text),200) as $batch) {
            DB::table('code_fingerprints')->insertOrIgnore(array_map(fn($fp)=>['submission_id'=>$id,'user_id'=>$userId,'task_id'=>$taskId,'fingerprint'=>$fp],$batch));
        }
        // Relink every matching row. Import never changes grades, timestamps, XP or coins.
        $links=$this->linkedSolutions($row,$taskId,$courseId,$userId);
        foreach ($links as $solution) DB::table('solutions')->where('id',$solution->id)->update(['text'=>url('/insider/code-submissions/'.$id)]);
        if ($links) $submission->update(['solution_id'=>$links[0]->id]);
    }

    private function linkedSolutions(array $row,int $task,int $course,int $user): array
    {
        if (!Schema::hasTable('solutions')) return [];
        return DB::table('solutions')->where('course_id',$course)->where('task_id',$task)->where('user_id',$user)->orderBy('id')->get()->filter(function($solution)use($row){
            preg_match('/(?:\?|&|&amp;)id=([A-Za-z0-9_-]+)/',$solution->text??'',$match);
            return ($solution->geekpaste_code_id??null)===(string)$row['id'] || ($match[1]??null)===(string)$row['id']
                || str_ends_with($solution->text??'','/code-submissions/'.self::id('submission',$row['id']));
        })->values()->all();
    }

    private function plan(array $row): array
    {
        if (!empty($row['original_archive'])) $this->blob($row['original_archive']);
        return app(LegacySource::class)->prepare($row,$this->tasks[(string)$row['task_id']],fn($value)=>$this->blob($value));
    }
    private function date(?string $value): Carbon
    {
        if ($value && !preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/D',$value)) throw new \InvalidArgumentException('invalid_timestamp');
        // Eloquent stores a naive timestamp and hydrates it in app.timezone.
        // Convert before serialization so the original instant survives that round trip.
        return ($value ? Carbon::parse($value,$this->timezone) : Carbon::createFromTimestampUTC(0))
            ->setTimezone(config('app.timezone', 'UTC'));
    }

    private function plannedSettings(array $row, mixed $target): array
    {
        $plan = $this->taskPlan[(string)$row['id']] ?? null;
        $task = Task::find($target);
        $previous=DB::table('code_legacy_tasks')->where('legacy_id',(string)$row['id'])->first();
        $sameImport=$previous && (int)$previous->task_id===(int)$target && hash_equals($previous->definition_hash,$this->fingerprint($row));
        $publicMatches=$plan && $task && (hash_equals($plan['target_public_text_sha256']??'',hash('sha256',$task->text??''))
            || ($sameImport && isset($plan['set_public_text']) && $task->text===$plan['set_public_text']));
        $pointsMatch=$plan && $task && (($plan['target_points']??null)===(int)$task->max_mark
            || ($sameImport && isset($plan['set_max_mark']) && $plan['set_max_mark']===(int)$task->max_mark));
        if (!$plan || !$task || (int)($plan['task_id'] ?? 0) !== (int)$target
            || !hash_equals($plan['source_definition_sha256'] ?? '', $this->fingerprint($row))
            || !$publicMatches || !$pointsMatch
            || !in_array($plan['action'] ?? null, ['configure','archive_only'], true)) {
            throw new \InvalidArgumentException('stale_or_invalid_task_plan');
        }
        $config = $this->settings($row);
        foreach (['private_instructions','reference_answer'] as $key) if (array_key_exists($key,$plan)) {
            if (!is_string($plan[$key]) || strlen($plan[$key]) > 800000 || !mb_check_encoding($plan[$key],'UTF-8')) {
                throw new \InvalidArgumentException('invalid_private_task_plan');
            }
            $config[$key] = $plan[$key];
        }
        if (isset($plan['set_public_text']) && (!is_string($plan['set_public_text']) || strlen($plan['set_public_text'])>800000 || !mb_check_encoding($plan['set_public_text'],'UTF-8'))) throw new \InvalidArgumentException('invalid_public_text_plan');
        $maximum=$plan['set_max_mark']??(int)$task->max_mark;
        if (!is_int($maximum) || $maximum<1 || $maximum>1000000) throw new \InvalidArgumentException('invalid_maximum_plan');
        if (isset($plan['check_type'])) {
            if (!in_array($plan['check_type'],['gpt','tests'],true)) throw new \InvalidArgumentException('invalid_check_type_plan');
            $config['check_type']=$plan['check_type'];
        }
        if (isset($plan['test_mode'])) {
            if ($config['check_type']!=='tests') throw new \InvalidArgumentException('invalid_test_mode_plan');
            $config['test_mode']=$plan['test_mode'];
            if ($plan['test_mode']==='suite') {
                $config['test_config']=app(LegacySuites::class)->validate($plan['test_config']??[],$config['language'],$maximum);
                $this->blob($config['test_config']['bundle']);
            } elseif ($plan['test_mode']==='io') {
                $input=$plan['test_config']??[];
                $config['test_config']=app(Configuration::class)->io($input,$maximum,function($value) {
                    if (is_string($value)) return ['type'=>'blob','blob_id'=>hash('sha256',$value),'sha256'=>hash('sha256',$value),'bytes'=>strlen($value),'truncated'=>false];
                    $this->blob($value);return ['type'=>'blob','blob_id'=>$value['sha256'],'sha256'=>$value['sha256'],'bytes'=>$value['bytes'],'truncated'=>false];
                });
                foreach ($config['test_config']['tests'] as $i=>$test) foreach (['input','output'] as $field) {
                    $config['test_config']['tests'][$i][$field]=$input['tests'][$i][$field];
                }
            } else throw new \InvalidArgumentException('invalid_test_mode_plan');
            $config['requires_conversion']=false;
        }
        return $config;
    }
    private function mapped(string $kind,mixed $id): mixed {return $this->map[$kind][(string)$id]??$id;}
    private function fingerprint(array $row): string
    {
        $canonical=function($value)use(&$canonical){if(!is_array($value))return $value;if(!array_is_list($value))ksort($value,SORT_STRING);return array_map($canonical,$value);};
        return hash('sha256',json_encode($canonical($row),JSON_THROW_ON_ERROR));
    }
    private function rows(string $name): \Generator
    {
        $handle=fopen($this->root.'/'.$name,'rb');
        try {while (($line=fgets($handle))!==false) if(trim($line)!=='') yield json_decode($line,true,128,JSON_THROW_ON_ERROR);}
        finally {fclose($handle);}
    }
    private function blob(array $value): string
    {
        $digest=$value['sha256']??'';
        if (!preg_match('/^[a-f0-9]{64}$/D',$digest) || ($value['path']??null)!=='blobs/'.$digest) throw new \InvalidArgumentException('Invalid export blob.');
        $path=realpath($this->root.'/blobs/'.$digest);
        if (!$path || !str_starts_with($path,$this->root.'/blobs/') || filesize($path)!==($value['bytes']??null) || !hash_equals($digest,hash_file('sha256',$path))) throw new \InvalidArgumentException('Blob checksum mismatch: '.$digest);
        return $path;
    }
}
