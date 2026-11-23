<?php
namespace App\MaterialReview;

use App\User;
use Illuminate\Support\Facades\DB;

/** Read a consistent SQLite backup; preserve the entire historical store before adapting mapped changes. */
class LegacyImport
{
    public function __construct(private Review $review,private Resources $resources) {}
    public function run(User $user,string $path,?string $mappingPath,bool $apply): array
    {
        $this->review->admin($user);
        if (!is_file($path)||!is_readable($path)) throw new Problem('sqlite','Исходная SQLite-база недоступна.');
        $mapping=$mappingPath?Documents::decode(file_get_contents($mappingPath)):[];
        $source=hash('sha256',$mapping['source_id']??realpath($path));
        $backup=tempnam(sys_get_temp_dir(),'material-review-backup-');
        $original=new \SQLite3($path,SQLITE3_OPEN_READONLY); $copy=new \SQLite3($backup);
        try { if (!$original->backup($copy)) throw new Problem('backup','Не удалось создать согласованную копию SQLite.'); }
        finally { $original->close(); $copy->close(); }
        $db=new \SQLite3($backup,SQLITE3_OPEN_READONLY);
        try {
            $tables=[]; $result=$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            while ($row=$result->fetchArray(SQLITE3_ASSOC)) $tables[]=$row['name'];
            $report=['source'=>$source,'backup_sha256'=>hash_file('sha256',$backup),'apply'=>$apply,'tables'=>[],'proposals'=>[],'resources'=>[],'unmapped'=>[]];
            $archive=function () use ($tables,$db,$source,$apply,&$report) {
                foreach ($tables as $table) {
                    $q='"'.str_replace('"','""',$table).'"'; $rows=$db->query('SELECT rowid AS __rowid__, * FROM '.$q); $count=0;
                    while ($raw=$rows->fetchArray(SQLITE3_ASSOC)) {
                        $row=[];
                        foreach ($raw as $k=>$v) {
                            $i=array_search($k,array_keys($raw),true);
                            $row[$k]=$rows->columnType($i)===SQLITE3_BLOB?['encoding'=>'base64','data'=>base64_encode($v)]:$v;
                        }
                        $rowKey=Documents::hash($row);
                        if ($apply) DB::table('material_review_legacy')->insertOrIgnore(['source'=>$source,'table_name'=>$table,'row_key'=>$rowKey,'payload'=>Documents::encode($row)]);
                        $count++;
                    }
                    $report['tables'][$table]=$count;
                }
            };
            if ($apply) DB::transaction($archive); else $archive();
            $files=[];
            if (in_array('source_files',$tables,true)) {
                $rows=$db->query('SELECT * FROM source_files');
                while ($file=$rows->fetchArray(SQLITE3_ASSOC)) $files[$file['id']]=$file;
            }
            $maps=$mapping['files']??[];
            foreach ($maps as $pathKey=>$target) {
                $this->checkMapping($target);
                if ($apply) DB::table('material_review_mappings')->updateOrInsert(['source'=>$source,'path_hash'=>hash('sha256',$pathKey)],
                    ['source_path'=>$pathKey,'program_id'=>$target['program_id'],'lesson_id'=>$target['lesson_id'],'entity'=>$target['entity'],'entity_id'=>$target['id'],'field'=>$target['field'],'created_at'=>now(),'updated_at'=>now()]);
            }
            $images=[];
            if (in_array('image_variants',$tables,true)) {
                $rows=$db->query('SELECT * FROM image_variants');
                while ($image=$rows->fetchArray(SQLITE3_ASSOC)) {
                    $images[$image['id']]=$image;
                    $target=$maps[$image['file']]??$maps[$files[$image['file']]['path']??'']??null;
                    $linked=$mapping['images'][$image['id']]??$target;
                    if (!$linked) foreach (Documents::decode($image['contexts']??'[]') as $context) {
                        $stage=$context['stage']??''; $linked=$maps[$stage]??$maps[$files[$stage]['path']??'']??null; if ($linked) break;
                    }
                    if (!$linked) { $report['unmapped'][]=['kind'=>'image','id'=>$image['id'],'file'=>$files[$image['file']]['path']??$image['file']]; continue; }
                    foreach (['before_image','after_image'] as $side) if (!empty($image[$side])) {
                        $id=$this->uuid($source.':'.$image['id'].':'.$side);
                        if ($apply) $this->resources->store($user,(int)$linked['program_id'],['id'=>$id,'mime'=>$image['mime'],'name'=>$image['caption']?:'Иллюстрация','data'=>base64_encode($image[$side])]);
                        $report['resources'][]=['legacy_id'=>$image['id'],'side'=>$side,'resource_id'=>$id];
                    }
                    $images[$image['id']]['_target']=$linked;
                }
            }
            $drafts=[];
            if (in_array('proposal_versions',$tables,true)) {
                $rows=$db->query('SELECT * FROM proposal_versions ORDER BY imported, rowid');
                while ($row=$rows->fetchArray(SQLITE3_ASSOC)) {
                    $p=Documents::decode($row['payload']); $choice=null;
                    if (in_array('choices',$tables,true)) {
                        $stmt=$db->prepare('SELECT * FROM choices WHERE id=:id AND fingerprint=:fp ORDER BY sequence DESC LIMIT 1');
                        $stmt->bindValue(':id',$row['id'],SQLITE3_TEXT); $stmt->bindValue(':fp',$row['fingerprint'],SQLITE3_TEXT); $choice=$stmt->execute()->fetchArray(SQLITE3_ASSOC)?:null;
                    }
                    $pairs=$p['pairs']??$p['changes']??[];
                    // Existing author-selected mappings take precedence over report guesses.
                    if ($choice&&!empty(Documents::decode($choice['patches']??'[]'))) $pairs=Documents::decode($choice['patches']);
                    $chosen=[]; foreach ($pairs as $pair) foreach ($pair['image_variants']??[] as $vid) $chosen[$vid]=true;
                    $selectedImages=[];
                    foreach ($images as $vid=>$image) {
                        if ($image['proposal']!==$row['id']||$image['fingerprint']!==$row['fingerprint']) continue;
                        if ($chosen&&!isset($chosen[$vid])) continue;
                        $imageKey=$image['file'].':'.($image['contexts']??'').':'.($image['caption']??''); $selectedImages[$imageKey]=$image;
                    }
                    $ops=[]; $lessons=[]; $program=null; $status='ready'; $historicalFields=[]; $imageViews=[]; $handledFiles=[]; $missingImageUsage=false;
                    foreach ($selectedImages as $image) {
                        $t=$image['_target']??null; if (!$t) { $missingImageUsage=true; continue; }
                        $program=(int)$t['program_id']; $lessons[(int)$t['lesson_id']]=true;
                        $imageViews[]=['before'=>!empty($image['before_image'])?$this->uuid($source.':'.$image['id'].':before_image'):null,
                            'after'=>!empty($image['after_image'])?$this->uuid($source.':'.$image['id'].':after_image'):null,'caption'=>$image['caption']??'Иллюстрация','variant_id'=>$image['id'],'notes'=>$image['notes']??''];
                        if (!empty($image['after_image'])&&$image['action']==='replace') {
                            $uses=$mapping['images'][$image['id']]['uses']??[$t];
                            if (!$uses) $missingImageUsage=true;
                            foreach ($uses as $use) {
                                if (empty($use['old'])) { $missingImageUsage=true; $report['unmapped'][]=['kind'=>'image_usage','id'=>$image['id'],'message'=>'Нужна точная ссылка old и цель использования.']; continue; }
                                $this->checkMapping($use);
                                if ((int)$use['program_id']!==$program) throw new Problem('program','Использования иллюстрации относятся к разным программам.');
                                $lessons[(int)$use['lesson_id']]=true;
                                $ops[]=['op'=>'image','entity'=>$use['entity'],'id'=>(int)$use['id'],'field'=>$use['field'],'old'=>$use['old'],'resource_id'=>$this->uuid($source.':'.$image['id'].':after_image')];
                                $handledFiles[$image['file']]=true;
                            }
                        }
                    }
                    if ($context=$mapping['lessons'][$row['lesson']]??null) {
                        $live=$this->review->documents->lesson((int)$context['lesson_id']);
                        if ($live['program_id']!==(int)$context['program_id']) throw new Problem('mapping','Контекст урока другой программы.');
                        if ($program!==null&&$program!==(int)$context['program_id']) throw new Problem('program','Изображения и урок относятся к разным программам.');
                        $program=(int)$context['program_id']; $lessons[(int)$context['lesson_id']]=true;
                    }
                    // The publication log can confirm accepted changes even when subsequent
                    // changes in the same batch overwrote individual fragments.
                    $publication=$mapping['publications'][$row['id']][$row['fingerprint']]??[];
                    $published=($choice['state']??null)==='accepted' && !empty($publication) && $program!==null;
                    foreach ($publication as $field) {
                        $this->checkMapping($field);
                        if (!is_string($field['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $field['sha256'])) {
                            throw new Problem('mapping','Для подтверждения публикации нужен SHA-256 полного значения поля.');
                        }
                        if ((int)$field['program_id']!==$program) throw new Problem('program','Доказательство публикации относится к другой программе.');
                        $lessons[(int)$field['lesson_id']]=true;
                        $document=[$this->review->documents->lesson((int)$field['lesson_id'])];
                        $node=$this->review->plan->node($document,$field['entity'],(int)$field['id']);
                        $value=$node['fields'][$field['field']]??null;
                        if (!is_string($value)||!hash_equals($field['sha256'],hash('sha256',$value))) $published=false;
                    }
                    foreach ($pairs as $i=>$pair) {
                        if ($published) break;
                        $fileKey=$pair['file']??'';
                        if (isset($handledFiles[$fileKey])) continue;
                        $target=$mapping['versions'][$row['id']][$row['fingerprint']][$i]??$mapping['proposal_targets'][$row['id']][$i]??$maps[$fileKey]??$maps[$files[$fileKey]['path']??'']??null;
                        if (!$target) { $status='mapping_error'; $report['unmapped'][]=['kind'=>'proposal','id'=>$row['id'],'fingerprint'=>$row['fingerprint'],'part'=>$i,'file'=>$files[$fileKey]['path']??$fileKey]; continue; }
                        $this->checkMapping($target);
                        if ($program!==null&&$program!==(int)$target['program_id']) throw new Problem('program','Связанное предложение не может пересекать программы.');
                        $program=(int)$target['program_id']; $lessons[(int)$target['lesson_id']]=true;
                        $op=['op'=>isset($pair['cell'])?'notebook_cell':'fragment','entity'=>$target['entity'],'id'=>(int)$target['id'],'field'=>$target['field'],'old'=>$pair['old']??'','new'=>$pair['new']??''];
                        if (!isset($pair['cell'])&&($pair['old']??null)===''&&!empty($p['author_empty'])) {
                            $contextDocs=[$this->review->documents->lesson((int)$target['lesson_id'])]; $node=$this->review->plan->node($contextDocs,$target['entity'],(int)$target['id']);
                            $actual=$node['fields'][$target['field']]??null;
                            $op=['op'=>'replace','entity'=>$target['entity'],'id'=>(int)$target['id'],'field'=>$target['field'],'expected'=>($actual===null||$actual==='')?$actual:'','value'=>$pair['new']??''];
                        }
                        if (isset($pair['cell'])) { $op['cell_index']=(int)$pair['cell']; $op['value']=$pair['new']??''; }
                        $ops[]=$op;
                        if (isset($files[$fileKey]['content'])) $historicalFields[]=['target'=>$target,'content'=>$files[$fileKey]['content']];
                    }
                    $decision=match($choice['state']??'pending') {'accepted'=>'accepted','rejected'=>'rejected','deferred','postponed'=>'deferred',default=>'pending'};
                    if (!$pairs&&!$ops) $status=!empty($p['requires_clarification'])?'question':'plan';
                    if ($missingImageUsage) $status='image_usage_required';
                    if ($published) { $status='already_applied'; $missingImageUsage=false; $ops=[]; }
                    $item=['id'=>$row['id'],'fingerprint'=>$row['fingerprint'],'decision'=>$decision,'status'=>$status,'title'=>$p['title']??'Импортированное предложение'];
                    if ($program===null) { $report['proposals'][]=$item; continue; }
                    $docs=array_map(fn ($id)=>$this->review->documents->lesson($id),array_keys($lessons));
                    try { if ($status!=='mapping_error') $this->review->plan->apply($docs,$ops); }
                    catch (Problem $e) {
                        $status=$e->error==='target'?'mapping_error':'stale_base';
                        if ($this->alreadyApplied($docs,$ops,$historicalFields)) $status='already_applied';
                        $item['status']=$status; $item['issue']=$e->json();
                    }
                    if ($missingImageUsage) { $status='image_usage_required'; $item['status']=$status; }
                    if ($apply) {
                        $key=$program.':'.implode(',',array_keys($lessons));
                        if (!isset($drafts[$key])) {
                            $draftId=$this->uuid($source.':draft:'.$key);
                            if (DB::table('material_review_drafts')->where('id',$draftId)->exists()) $drafts[$key]=$this->review->get($user,$draftId);
                            else {
                                $baseIds=array_map(fn ($doc)=>$this->review->documents->remember($doc,'legacy_base',$user->id)['id'],$docs);
                                $drafts[$key]=$this->review->create($user,['format_version'=>1,'installation_id'=>$this->review->documents->installation(),'program_id'=>$program,
                                    'id'=>$draftId,'base_revision_ids'=>$baseIds,'title'=>'Перенос накопленного ревью','reason'=>'Сохранение предложений и решений из локальной мастерской.','origin'=>['source'=>$source]]);
                            }
                        }
                        $d=$this->review->get($user,$drafts[$key]['id']);
                        $reason=$p['reason']??null;
                        if (!$reason&&preg_match('/Причина:\s*(.+?)(?:\n\n|$)/su',str_replace('\\n',"\n",$p['body']??''),$m)) $reason=trim($m[1]);
                        $reason=$reason?:'Причина не указана в исходном аудите';
                        $saved=$this->review->propose($user,$d['id'],['external_id'=>$source.':'.$row['id'].':'.$row['fingerprint'],'kind'=>($missingImageUsage||$status==='mapping_error'||$status==='stale_base')?'unsupported':(in_array($status,['question','plan'],true)?$status:'change'),
                            'title'=>mb_substr($item['title'],0,255),'reason'=>mb_substr($reason,0,10000),'operations'=>in_array($status,['already_applied','mapping_error','stale_base'],true)?[]:$ops,'scope'=>'local','intent'=>'learning',
                            'legacy_images'=>$imageViews,'legacy'=>['id'=>$row['id'],'fingerprint'=>$row['fingerprint'],'source'=>$source,'status'=>$status,'missing_image_usage'=>$missingImageUsage,'body'=>$p['body']??'','original_operations'=>$ops,'publication_fields'=>$publication]],$d['version']);
                        if (!$saved['duplicate']) {
                            DB::table('material_review_proposals')->where('id',$saved['proposal_id'])->update(['decision'=>$decision,'comment'=>$choice['note']??null,'published_at'=>$status==='already_applied'&&$decision==='accepted'?now():null]);
                            $this->review->event($d['id'],'legacy_decision',['proposal_id'=>$saved['proposal_id'],'decision'=>$decision,'source'=>$source,'original_updated'=>$choice['updated']??null],$user->id);
                        }
                        $item['draft_id']=$d['id']; $item['proposal_id']=$saved['proposal_id'];
                    }
                    $report['proposals'][]=$item;
                }
            }
            if ($apply) foreach ($drafts as $d) {
                $fresh=$this->review->get($user,$d['id']); [$targets]=$this->review->compose($fresh);
                DB::table('material_review_drafts')->where('id',$d['id'])->update(['targets'=>Documents::encode($targets)]);
            }
            $report['raw_history_preserved']=$apply; $report['requires_mapping_review']=!empty($report['unmapped']);
            return $report;
        } finally { $db->close(); @unlink($backup); }
    }
    private function checkMapping(array $target): void
    {
        foreach (['program_id','lesson_id','entity','id','field'] as $key) if (!isset($target[$key])) throw new Problem('mapping','Неполное явное сопоставление.',['field'=>$key]);
        $doc=$this->review->documents->lesson((int)$target['lesson_id']);
        if ((int)$doc['program_id']!==(int)$target['program_id']) throw new Problem('mapping','Урок относится к другой программе.');
        $docs=[$doc]; $this->review->plan->node($docs,$target['entity'],(int)$target['id']);
        if (!in_array($target['field'],Documents::FIELDS[$target['entity']]??[],true)) throw new Problem('mapping','Поле сопоставления недоступно для изменения.');
    }
    private function uuid(string $key): string
    { $h=hash('sha256',$key); return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-a'.substr($h,17,3).'-'.substr($h,20,12); }
    private function alreadyApplied(array $current,array $ops,array $fields): bool
    {
        if (!$ops||count($fields)!==count($ops)) return false;
        $base=$current;
        try {
            foreach ($fields as $field) {
                $t=$field['target']; $node=&$this->review->plan->node($base,$t['entity'],$t['id']); $node['fields'][$t['field']]=$field['content']; unset($node);
            }
            $expected=$this->review->plan->apply($base,$ops); $changes=Documents::changes($base,$expected); $actual=Documents::flat($current);
            if (!$changes) return false;
            foreach ($changes as $path=>$c) if (!array_key_exists($path,$actual)||Documents::hash($actual[$path])!==Documents::hash($c['after'])) return false;
            return true;
        } catch (\Throwable) { return false; }
    }
}
