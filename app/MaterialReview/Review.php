<?php
namespace App\MaterialReview;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Review
{
    public function __construct(public Documents $documents,public Plan $plan) {}
    public function admin(User $user): void
    { if ($user->role!=='admin') throw new Problem('forbidden','Ревью программ доступно только администраторам.',[],403); }
    public function event(string $id,string $type,array $payload,?int $actor): void
    { DB::table('material_review_events')->insert(['object_id'=>$id,'type'=>$type,'payload'=>Documents::encode($payload),'actor_id'=>$actor,'created_at'=>now()]); }
    public function catalog(User $user): array
    {
        $this->admin($user);
        return DB::table('programs')->orderBy('name')->get()->map(function ($p) {
            return ['id'=>$p->id,'name'=>$p->name,'courses'=>DB::table('courses')->where('program_id',$p->id)->get(['id','name'])->all(),
                'chapters'=>DB::table('program_chapters')->where('program_id',$p->id)->orderBy('sort_index')->orderBy('id')->get()->all(),
                'lessons'=>DB::table('lessons')->leftJoin('program_chapters','program_chapters.id','=','lessons.chapter_id')->where('lessons.program_id',$p->id)
                    ->orderBy('program_chapters.sort_index')->orderBy('program_chapters.id')->orderBy('lessons.sort_index')->orderBy('lessons.id')->get(['lessons.*'])->all(),
                'drafts'=>DB::table('material_review_drafts')->where('program_id',$p->id)->orderByDesc('updated_at')->get(['id','title','state','version','reviewed_hash'])->all(),
                'progress'=>DB::table('material_review_progress')->where('program_id',$p->id)->get()->all()];
        })->all();
    }
    public function create(User $user,array $input): array
    {
        $this->admin($user); $this->envelope($input);
        return DB::transaction(function () use ($user,$input) {
            $bases=[]; $docs=[];
            foreach ($input['base_revision_ids']??[] as $id) {
                $r=$this->documents->read($id);
                if ((int)$r['program_id']!==(int)($input['program_id']??0)) throw new Problem('program','Редакция относится к другой программе.');
                if (isset($bases[$r['lesson_id']])) throw new Problem('identity','Основа урока повторяется.');
                $bases[$r['lesson_id']]=$r['id']; $docs[]=$r['snapshot'];
            }
            if (!$docs) throw new Problem('base','Укажите базовые редакции всех затронутых уроков.');
            $reason=$this->reason($input['reason']??''); $title=$this->title($input['title']??'Редакция урока');
            $target=$input['targets']??$docs; $this->normalize($docs,$target); $initialChanges=$this->plan->validate($docs,$target);
            $groupIds=[]; $groupPaths=[];
            foreach ($input['transfer_groups']??[] as $group) {
                if (!is_array($group)||empty($group['id'])||!is_string($group['id'])||empty($group['paths'])||!is_array($group['paths'])||array_diff($group['paths'],array_keys($initialChanges))) throw new Problem('transfer_groups','Независимая группа должна явно перечислять свои изменения.');
                if (isset($groupIds[$group['id']])||array_intersect($group['paths'],$groupPaths)) throw new Problem('transfer_groups','Пересекающиеся части нужно объединить в одно логическое предложение.');
                $groupIds[$group['id']]=true; $groupPaths=array_merge($groupPaths,$group['paths']);
                $this->title($group['title']??''); $this->reason($group['reason']??'');
            }
            $id=$input['id']??(string)Str::uuid();
            if (!Str::isUuid($id)) throw new Problem('identity','ID черновика должен быть UUID.');
            $fingerprint=Documents::hash($input);
            if ($existing=DB::table('material_review_drafts')->find($id)) {
                if ((Documents::decode($existing->metadata)['import_hash']??'')!==$fingerprint) throw new Problem('identity','Этот ID уже принадлежит другому пакету.',[],409);
                return $this->get($user,$id);
            }
            DB::table('material_review_drafts')->insert(['id'=>$id,'program_id'=>$input['program_id'],'title'=>$title,'reason'=>$reason,'version'=>1,'state'=>'draft','actor_id'=>$user->id,
                'bases'=>Documents::encode($bases),'targets'=>Documents::encode($target),'metadata'=>Documents::encode(['seed'=>$target,'origin'=>$input['origin']??[],'transfer_groups'=>$input['transfer_groups']??[],'import_hash'=>$fingerprint]),'created_at'=>now(),'updated_at'=>now()]);
            $this->event($id,'created',['bases'=>$bases,'targets'=>$target,'reason'=>$reason],$user->id);
            foreach (array_keys($bases) as $lesson) DB::table('material_review_progress')->updateOrInsert(['program_id'=>$input['program_id'],'lesson_id'=>$lesson],['reviewed_hash'=>null,'actor_id'=>$user->id,'created_at'=>now(),'updated_at'=>now()]);
            foreach ($input['proposals']??[] as $p) $this->propose($user,$id,$p,null);
            return $this->get($user,$id);
        });
    }
    public function get(User $user,string $id): array
    {
        $this->admin($user); $r=DB::table('material_review_drafts')->find($id);
        if (!$r) throw new Problem('not_found','Черновик не найден.',[],404);
        $out=(array)$r;
        foreach (['bases','targets','metadata'] as $k) $out[$k]=Documents::decode($out[$k]);
        $out['base_documents']=array_map(fn ($id)=>$this->documents->read($id)['snapshot'],array_values($out['bases']));
        $out['proposals']=DB::table('material_review_proposals')->where('draft_id',$id)->orderBy('sequence')->get()->map(function ($p) { $p=(array)$p; $p['payload']=Documents::decode($p['payload']); return $p; })->all();
        $out['courses']=DB::table('courses')->where('program_id',$r->program_id)->get(['id','name'])->all();
        $out['publication']=DB::table('material_review_publications')->where('draft_id',$id)->first(['id','created_at']);
        $out['history']=DB::table('material_review_events')->where('object_id',$id)->orderBy('id')->get()->map(fn ($e)=>array_merge((array)$e,['payload'=>Documents::decode($e->payload)]))->all();
        return $out;
    }
    public function propose(User $user,string $id,array $input,?int $version): array
    {
        $this->admin($user);
        return DB::transaction(function () use ($user,$id,$input,$version) {
            $d=$this->locked($user,$id,$version,true); $reason=$this->reason($input['reason']??'');
            foreach (['depends_on','alternatives'] as $key) {
                $refs=$input[$key]??[];
                if (!is_array($refs)||!array_is_list($refs)||count($refs)>1000) throw new Problem('references','Некорректный список зависимостей или альтернатив.');
                foreach ($refs as $ref) if (!is_string($ref)||!Str::isUuid($ref)||!DB::table('material_review_proposals')->where('draft_id',$id)->where('id',$ref)->exists()) throw new Problem('references','Зависимость должна ссылаться на предложение этого черновика.');
            }
            if (isset($input['targets'])) {
                $against=$input['against']??$d['base_documents']; $this->normalize($against,$input['targets']); $this->plan->validate($against,$input['targets']);
            }
            $input['scope']=$input['scope']??(isset($input['targets'])?'lesson':(collect($input['operations']??[])->contains(fn ($o)=>in_array($o['op']??'', ['create_step','archive_step','move_step','move_task','create_task','archive_task','reorder_steps','reorder_tasks'],true))?'structure':'local'));
            $input['intent']=$input['intent']??(($input['kind']??'')==='question'?'question':'learning');
            if (!in_array($input['scope'],['lesson','structure','local'],true)||!in_array($input['intent'],['error','learning','style','question'],true)) throw new Problem('classification','Неизвестный уровень или характер предложения.');
            $external=(string)($input['external_id']??$input['id']??Str::uuid());
            if (strlen($external)>255) throw new Problem('identity','Слишком длинный ID предложения.');
            $fingerprint=Documents::hash($input);
            $existing=DB::table('material_review_proposals')->where('draft_id',$id)->where('external_id',$external)->where('fingerprint',$fingerprint)->first();
            if ($existing) return ['proposal_id'=>$existing->id,'version'=>$d['version'],'duplicate'=>true];
            if ($d['state']==='published') throw new Problem('published','Для новых предложений после публикации нужна следующая редакция.',[],409);
            $pid=(string)Str::uuid();
            $operations=$input['operations']??[];
            if (!is_array($operations)||count($operations)>1000) throw new Problem('operations','Некорректный список операций.');
            if (!in_array($input['kind']??'change',['change','manual','question','plan','unsupported'],true)) throw new Problem('kind','Неизвестный тип предложения.');
            if ($operations&&!in_array($input['kind']??'change',['question','plan','unsupported'],true)) {
                try { $candidate=$this->plan->apply($d['targets'],$operations); $this->normalize($d['targets'],$candidate); $this->plan->validate($d['base_documents'],$candidate); }
                catch (Problem $e) { if (!in_array($e->error,['anchor','conflict','cell'],true)&&!($e->error==='target'&&!empty($input['depends_on']))) throw $e; }
            }
            // Plans and questions may be stored; applicability is evaluated independently of the author's decision.
            DB::table('material_review_proposals')->insert(['id'=>$pid,'draft_id'=>$id,'external_id'=>$external,'fingerprint'=>$fingerprint,
                'kind'=>$input['kind']??'change','title'=>$this->title($input['title']??'Предложение'),'reason'=>$reason,
                'decision'=>'pending','payload'=>Documents::encode($input),'created_at'=>now(),'updated_at'=>now()]);
            $this->bump($d); $this->event($id,'proposal_created',['proposal_id'=>$pid,'external_id'=>$external,'payload'=>$input],$user->id);
            return ['proposal_id'=>$pid,'version'=>$d['version']+1,'duplicate'=>false];
        });
    }
    public function decide(User $user,string $id,string $proposal,string $decision,int $version,?string $comment=null): array
    {
        $this->admin($user);
        if (!in_array($decision,['pending','accepted','rejected','deferred'],true)) throw new Problem('decision','Неизвестное решение.');
        return DB::transaction(function () use ($user,$id,$proposal,$decision,$version,$comment) {
            $d=$this->locked($user,$id,$version);
            $p=DB::table('material_review_proposals')->where('draft_id',$id)->where('id',$proposal)->first();
            if (!$p) throw new Problem('not_found','Предложение не найдено.',[],404);
            $payload=Documents::decode($p->payload);
            if ($decision==='accepted' && $p->decision!=='accepted' && (in_array($p->kind,['question','unsupported','plan'],true)||(!($payload['operations']??[])&&!isset($payload['targets'])))) throw new Problem('not_executable','Вопрос или план нельзя применить как готовую правку.');
            DB::table('material_review_proposals')->where('id',$proposal)->update(['decision'=>$decision,'comment'=>$comment,'updated_at'=>now()]);
            $this->event($id,'decision',['proposal_id'=>$proposal,'before'=>$p->decision,'after'=>$decision,'comment'=>$comment],$user->id);
            $this->recompose($user,$d); return $this->get($user,$id);
        });
    }
    public function update(User $user,string $id,array $input,int $version): array
    {
        return DB::transaction(function () use ($user,$id,$input,$version) {
            $d=$this->locked($user,$id,$version);
            $p=['external_id'=>(string)Str::uuid(),'kind'=>'manual','title'=>$input['title']??'Ручная правка','reason'=>$this->reason($input['reason']??''),'operations'=>$input['operations']??[]];
            if (isset($input['targets'])) { $p['against']=$d['targets']; $p['targets']=$input['targets']; $this->normalize($p['against'],$p['targets']); $this->plan->validate($p['against'],$p['targets']); }
            $created=$this->propose($user,$id,$p,$version);
            return $this->decide($user,$id,$created['proposal_id'],'accepted',$created['version']);
        });
    }
    public function select(User $user,string $id,int $version): array
    {
        return DB::transaction(function () use ($user,$id,$version) {
            $d=$this->locked($user,$id,$version);
            DB::table('programs')->where('id',$d['program_id'])->lockForUpdate()->first();
            foreach (DB::table('material_review_drafts')->where('program_id',$d['program_id'])->where('state','selected')->where('id','!=',$id)->get() as $other) {
                if (!array_intersect(array_keys(Documents::decode($other->bases)),array_keys($d['bases']))) continue;
                DB::table('material_review_drafts')->where('id',$other->id)->update(['state'=>'draft','version'=>$other->version+1,'updated_at'=>now()]);
                $this->event($other->id,'deselected',['selected_draft'=>$id],$user->id);
            }
            $this->bump($d,['state'=>'selected']);
            $this->event($id,'selected',[],$user->id); return $this->get($user,$id);
        });
    }
    public function transfer(User $user,string $id,string $sourceId,array $paths,int $version,?int $sourceVersion=null): array
    {
        return DB::transaction(function () use ($user,$id,$sourceId,$paths,$version,$sourceVersion) {
            $d=$this->locked($user,$id,$version); $source=$this->get($user,$sourceId);
            if ($d['program_id']!==$source['program_id']) throw new Problem('program','Редакция другой программы.');
            if ($sourceVersion!==null&&$source['version']!==$sourceVersion) throw new Problem('version','Исходная редакция изменилась. Повторите сравнение.',[],409);
            $groups=$this->transferGroups($source); $chosen=[]; $covered=[];
            foreach ($groups as $group) if (!array_diff($group['paths'],$paths)) { $chosen[$group['id']]=$group; $covered=array_merge($covered,$group['paths']); }
            if (array_diff($paths,$covered)) throw new Problem('linked_change','Часть связанного изменения нельзя переносить отдельно. Выберите самостоятельное предложение целиком.');
            foreach ($chosen as $group) if (array_diff($group['depends_on']??[],array_keys($chosen))||array_intersect($group['alternatives']??[],array_keys($chosen))) throw new Problem('dependency','Выбранные части имеют неразрешённые зависимости или альтернативы.');
            $changes=Documents::changes($source['base_documents'],$source['targets']); $flat=Documents::flat($source['base_documents']);
            if (!$paths) throw new Problem('selection','Выберите изменения для переноса.');
            foreach ($paths as $path) { if (!isset($changes[$path])) throw new Problem('selection','Изменение отсутствует в исходной редакции.',['path'=>$path]); $flat[$path]=$changes[$path]['after']; }
            $target=Documents::inflate($flat,$source['base_documents']);
            $merged=Documents::merge($source['base_documents'],$target,$d['targets']); $this->plan->validate($d['base_documents'],$merged);
            $cursor=Documents::flat($source['base_documents']); $remaining=$chosen; $created=[]; $draftVersion=$version;
            while ($remaining) {
                $progress=false;
                foreach ($remaining as $groupId=>$group) {
                    if (array_diff($group['depends_on']??[],array_keys($created))) continue;
                    $against=Documents::inflate($cursor,$source['base_documents']);
                    foreach ($group['paths'] as $path) $cursor[$path]=$changes[$path]['after'];
                    $next=Documents::inflate($cursor,$source['base_documents']);
                    $p=$this->propose($user,$id,['external_id'=>'transfer:'.hash('sha256',$sourceId.':'.$source['version'].':'.$groupId),'title'=>mb_substr($group['title'],0,255),
                        'reason'=>$group['reason'],'kind'=>'change','scope'=>'structure','against'=>$against,'targets'=>$next,
                        'depends_on'=>array_map(fn ($dep)=>$created[$dep],$group['depends_on']??[]),'transferred_from'=>$sourceId,'transferred_group_id'=>$groupId,'paths'=>$group['paths']],$draftVersion);
                    $created[$groupId]=$p['proposal_id']; $draftVersion=$p['version']; unset($remaining[$groupId]); $progress=true;
                }
                if (!$progress) throw new Problem('dependency','Группы изменений образуют цикл зависимостей.');
            }
            return ['proposal_id'=>reset($created),'proposal_ids'=>array_values($created),'version'=>$draftVersion];
        });
    }
    public function transferGroups(array $source): array
    {
        $changes=Documents::changes($source['base_documents'],$source['targets']); $groups=[];
        $publication=DB::table('material_review_publications')->where('draft_id',$source['id'])->first(); $map=$publication?Documents::decode($publication->id_map):[];
        foreach ($source['metadata']['transfer_groups']??[] as $group) {
            foreach ($map as $temporary=>$permanent) $group['paths']=array_map(fn ($path)=>str_replace(':'.rawurlencode($temporary).':',':'.$permanent.':',$path),$group['paths']);
            if (!is_array($group)||empty($group['id'])||empty($group['paths'])||array_diff($group['paths'],array_keys($changes))) continue;
            $this->title($group['title']??''); $this->reason($group['reason']??''); $groups[]=$group;
        }
        $actual=Documents::flat($source['targets']);
        foreach ($source['proposals'] as $p) {
            if ($p['decision']!=='accepted'||empty($p['payload']['operations'])) continue;
            try {
                $after=$this->plan->apply($source['metadata']['seed'],$p['payload']['operations']);
                $delta=$this->plan->validate($source['metadata']['seed'],$after);
                foreach ($delta as $path=>$c) if (!array_key_exists($path,$actual)||Documents::hash($actual[$path])!==Documents::hash($c['after'])) continue 2;
                $covered=array_merge(...array_map(fn ($g)=>$g['paths'],$groups?:[['paths'=>[]]]));
                if ($delta&&!array_intersect(array_keys($delta),$covered)) $groups[]=['id'=>'proposal:'.$p['id'],'title'=>$p['title'],'reason'=>$p['reason'],'paths'=>array_keys($delta),
                    'depends_on'=>array_map(fn ($id)=>'proposal:'.$id,$p['payload']['depends_on']??[]),'alternatives'=>array_map(fn ($id)=>'proposal:'.$id,$p['payload']['alternatives']??[])];
            } catch (Problem) { /* A dependent transformation remains part of its complete alternative. */ }
        }
        return $groups;
    }
    public function complete(User $user,string $id,int $version): array
    {
        return DB::transaction(function () use ($user,$id,$version) {
            $d=$this->locked($user,$id,$version); $this->bump($d,['reviewed_hash'=>Documents::hash([$d['targets'],$d['proposals']])]);
            foreach (array_keys($d['bases']) as $lesson) DB::table('material_review_progress')->updateOrInsert(['program_id'=>$d['program_id'],'lesson_id'=>$lesson],['reviewed_hash'=>Documents::hash([$d['targets'],$d['proposals']]),'actor_id'=>$user->id,'created_at'=>now(),'updated_at'=>now()]);
            $this->event($id,'review_completed',[],$user->id); return $this->get($user,$id);
        });
    }
    public function check(User $user,string $id): array
    {
        $d=$this->get($user,$id);
        if ($d['state']==='published') return ['valid'=>true,'issues'=>[],'changes'=>[],'targets'=>$d['targets'],'merged'=>$d['targets'],'impact'=>['courses'=>$d['courses'],'tasks'=>[],'deferred'=>0],'published'=>true,'version'=>$d['version']];
        [$target,$issues]=$this->compose($d);
        $changes=[]; $current=[]; $merged=null;
        try {
            $changes=$this->plan->validate($d['base_documents'],$target);
            foreach ($d['base_documents'] as $l) $current[]=$this->documents->lesson($l['id']);
            foreach ($d['base_documents'] as $base) foreach ($base['resources']??[] as $use) {
                $path=$use['entity'].':'.rawurlencode((string)$use['id']).':'.$use['field'];
                if (!isset($changes[$path])||!$use['captured']) continue;
                foreach ($current as $live) foreach ($live['resources']??[] as $now) if ($now['entity']===$use['entity']&&(string)$now['id']===(string)$use['id']&&$now['field']===$use['field']&&$now['url']===$use['url']&&$now['sha256']!==$use['sha256']) throw new Problem('resource_conflict','Исходный файл иллюстрации изменился. Нужна сверка версии.',['url'=>$use['url']],409);
            }
            $merged=Documents::merge($d['base_documents'],$target,$current);
            $this->resources($d['program_id'],$merged);
        } catch (Problem $e) { $issues[]=$e->json(); }
        return ['valid'=>!$issues,'issues'=>$issues,'changes'=>$changes,'targets'=>$target,'merged'=>$merged,'impact'=>$this->impact($d,$changes,$target),
            'published'=>$d['state']==='published','version'=>$d['version']];
    }
    public function compose(array $d): array
    {
        $target=$d['metadata']['seed']; $issues=[]; $done=[]; $pending=[];
        foreach ($d['proposals'] as $p) if ($p['decision']==='accepted') $pending[$p['id']]=$p;
        while ($pending) {
            $progress=false;
            foreach ($pending as $pid=>$p) {
                $deps=$p['payload']['depends_on']??[];
                if (array_diff($deps,array_keys($done))) continue;
                try {
                    if (in_array($p['kind'],['question','plan','unsupported'],true)) throw new Problem('not_executable','Принятое историческое решение пока не содержит поддерживаемого исполняемого изменения.');
                    foreach ($d['proposals'] as $variant) if ($variant['id']!==$pid&&$variant['decision']==='accepted'&&$variant['external_id']===$p['external_id']) throw new Problem('alternative','Приняты две версии одного предложения. Выберите один вариант.');
                    foreach ($p['payload']['alternatives']??[] as $alt) if (isset($pending[$alt])||isset($done[$alt])) throw new Problem('alternative','Приняты несовместимые альтернативы.');
                    if (isset($p['payload']['targets'])) $next=Documents::merge($p['payload']['against']??$d['base_documents'],$p['payload']['targets'],$target);
                    else $next=$this->plan->apply($target,$p['payload']['operations']??[]);
                    $this->normalize($target,$next); $this->plan->validate($d['base_documents'],$next); $target=$next; $done[$pid]=true;
                } catch (Problem $e) { $issues[]=array_merge($e->json(),['proposal_id'=>$pid]); }
                unset($pending[$pid]); $progress=true;
            }
            if (!$progress) { foreach ($pending as $pid=>$p) $issues[]=['error'=>'dependency','message'=>'Зависимость не принята, конфликтует или образует цикл.','proposal_id'=>$pid]; break; }
        }
        return [$target,$issues];
    }
    public function reject(User $user,string $id,int $version): array
    {
        return DB::transaction(function () use ($user,$id,$version) { $d=$this->locked($user,$id,$version); $this->bump($d,['state'=>'rejected']); $this->event($id,'rejected',[],$user->id); return $this->get($user,$id); });
    }
    public function export(User $user,string $id): array
    {
        $d=$this->get($user,$id);
        $encoded=Documents::encode($d); preg_match_all('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i',$encoded,$matches);
        $history=DB::table('material_review_publications')->where('draft_id',$id);
        if (!empty($d['metadata']['restore_publication'])) $history->orWhere('id',$d['metadata']['restore_publication']);
        $sources=array_values(array_unique(array_filter(array_merge([$d['metadata']['origin']['source']??null],array_map(fn ($p)=>$p['payload']['legacy']['source']??null,$d['proposals'])))));
        $package=['format_version'=>1,'installation_id'=>$this->documents->installation(),'snapshot_id'=>Documents::hash($d['base_documents']),
            'program_id'=>$d['program_id'],'draft'=>$d,'resources'=>DB::table('material_review_resources')->where('program_id',$d['program_id'])->whereIn('id',array_unique($matches[0]))->get()->all(),
            'publication_history'=>$history->get()->all(),'revision_history'=>DB::table('material_review_revisions')->where('program_id',$d['program_id'])->whereIn('lesson_id',array_keys($d['bases']))->get()->all(),
            'legacy_history'=>DB::table('material_review_legacy')->whereIn('source',$sources)->get(['source','table_name','row_key','payload'])->all()];
        $package['archive_signature']=$this->documents->sign($package); return $package;
    }
    public function import(User $user,array $package): array
    {
        $this->admin($user); $this->envelope($package);
        if (!hash_equals($this->documents->sign($package),(string)($package['archive_signature']??''))) throw new Problem('archive_signature','Архив изменён или не был экспортирован этой установкой. Новые предложения сохраняйте через create/propose.');
        return DB::transaction(function () use ($user,$package) {
            $d=$package['draft']??[];
            foreach ($d['base_documents']??[] as $doc) {
                $live=$this->documents->lesson($doc['id']);
                if ((int)$live['program_id']!==(int)$package['program_id']) throw new Problem('program','Урок другой программы.');
            }
            // Restore immutable base IDs only when their hashes and identity match the archive.
            foreach ($d['bases']??[] as $lesson=>$rid) {
                $doc=collect($d['base_documents'])->firstWhere('id',(int)$lesson);
                if (!$doc) throw new Problem('base','В пакете отсутствует основа.');
                if ($existing=DB::table('material_review_revisions')->find($rid)) {
                    if ($existing->hash!==Documents::hash($doc)) throw new Problem('identity','ID основы занят другим содержанием.');
                } else DB::table('material_review_revisions')->insert(['id'=>$rid,'program_id'=>$doc['program_id'],'lesson_id'=>$doc['id'],'base_id'=>null,'hash'=>Documents::hash($doc),'source'=>'package','actor_id'=>$user->id,'snapshot'=>Documents::encode($doc),'created_at'=>now()]);
            }
            foreach ($package['resources']??[] as $r) app(Resources::class)->store($user,(int)$package['program_id'],(array)$r);
            foreach ($package['revision_history']??[] as $r) {
                $r=(array)$r; unset($r['sequence']); $snapshot=Documents::decode($r['snapshot']);
                if ((int)$r['program_id']!==(int)$package['program_id']||(int)$snapshot['program_id']!==(int)$package['program_id']||$r['hash']!==Documents::hash($snapshot)) throw new Problem('history','Некорректная историческая редакция.');
                if ($existing=DB::table('material_review_revisions')->find($r['id'])) { if ($existing->hash!==$r['hash']) throw new Problem('identity','Конфликт ID исторической редакции.'); }
                else DB::table('material_review_revisions')->insert(array_intersect_key($r,array_flip(['id','program_id','lesson_id','base_id','hash','source','actor_id','snapshot','created_at'])));
            }
            $input=['format_version'=>1,'installation_id'=>$package['installation_id'],'program_id'=>$package['program_id'],'id'=>$d['id'],
                'base_revision_ids'=>array_values($d['bases']),'title'=>$d['title'],'reason'=>$d['reason'],'targets'=>$d['metadata']['seed'],'origin'=>$d['metadata']['origin']??[]];
            if ($existing=DB::table('material_review_drafts')->find($d['id'])) {
                if ((int)$existing->program_id!==(int)$package['program_id']||Documents::hash(Documents::decode($existing->bases))!==Documents::hash($d['bases'])) throw new Problem('identity','ID черновика занят другой основой.');
                return $this->get($user,$d['id']);
            }
            $this->create($user,$input);
            foreach ($d['proposals']??[] as $p) {
                DB::table('material_review_proposals')->insert(['id'=>$p['id'],'draft_id'=>$d['id'],'external_id'=>$p['external_id'],'fingerprint'=>$p['fingerprint'],
                    'kind'=>$p['kind'],'title'=>$p['title'],'reason'=>$p['reason'],'decision'=>$p['decision'],'payload'=>Documents::encode($p['payload']),
                    'comment'=>$p['comment'],'published_at'=>$p['published_at'],'created_at'=>$p['created_at'],'updated_at'=>$p['updated_at']]);
            }
            $this->recompose($user,$this->get($user,$d['id']));
            foreach ($package['publication_history']??[] as $p) {
                $p=(array)$p;
                if ((int)$p['program_id']!==(int)$package['program_id']) throw new Problem('program','История публикаций другой программы.');
                foreach (['before','after'] as $key) foreach (Documents::decode($p[$key]) as $doc) if ((int)$doc['program_id']!==(int)$package['program_id']) throw new Problem('program','Исторический снимок другой программы.');
                if ($existing=DB::table('material_review_publications')->find($p['id'])) { if ($existing->after!==$p['after']) throw new Problem('identity','Конфликт ID публикации.'); }
                else DB::table('material_review_publications')->insert(array_intersect_key($p,array_flip(['id','draft_id','program_id','actor_id','before','after','id_map','score_changes','deadline_changes','created_at'])));
            }
            foreach ($package['legacy_history']??[] as $row) DB::table('material_review_legacy')->insertOrIgnore(array_intersect_key((array)$row,array_flip(['source','table_name','row_key','payload'])));
            DB::table('material_review_drafts')->where('id',$d['id'])->update(['state'=>$d['state'],'targets'=>Documents::encode($d['targets']),'metadata'=>Documents::encode($d['metadata']),'version'=>$d['version'],'reviewed_hash'=>$d['reviewed_hash']??null]);
            $this->event($d['id'],'package_imported',['origin_state'=>$d['state'],'history'=>$d['history']],$user->id);
            return $this->get($user,$d['id']);
        });
    }
    public function locked(User $user,string $id,?int $version,bool $allowPublished=false): array
    {
        $this->admin($user); DB::table('material_review_drafts')->where('id',$id)->lockForUpdate()->first();
        $d=$this->get($user,$id);
        if ($version!==null && $d['version']!==$version) throw new Problem('version','Черновик изменился. Введённый текст сохраните и повторите сравнение.',['expected'=>$version,'actual'=>$d['version']],409);
        if ($d['state']==='published'&&!$allowPublished) throw new Problem('published','Опубликованную редакцию нельзя изменять. Создайте следующую.',[],409);
        return $d;
    }
    private function bump(array $d,array $values=[]): void
    {
        DB::table('material_review_drafts')->where('id',$d['id'])->update(array_merge(['version'=>$d['version']+1,'reviewed_hash'=>null,'updated_at'=>now()],$values));
        if (!isset($values['reviewed_hash'])) DB::table('material_review_progress')->where('program_id',$d['program_id'])->whereIn('lesson_id',array_keys($d['bases']))->update(['reviewed_hash'=>null,'updated_at'=>now()]);
    }
    private function recompose(User $user,array $d): void
    { $d=$this->get($user,$d['id']); [$target]=$this->compose($d); $this->bump($d,['targets'=>Documents::encode($target)]); }
    private function envelope(array $input): void
    {
        if (($input['format_version']??null)!==1) throw new Problem('format','Поддерживается format_version: 1.');
        if (($input['installation_id']??'')!==$this->documents->installation()) throw new Problem('installation','Пакет относится к другой установке платформы.');
    }
    private function reason(mixed $reason): string
    { if (!is_string($reason)||trim($reason)===''||mb_strlen($reason)>10000) throw new Problem('reason','Нужно короткое обоснование изменения.'); return trim($reason); }
    private function title(mixed $title): string
    { if (!is_string($title)||trim($title)===''||mb_strlen($title)>255) throw new Problem('title','Название должно содержать от 1 до 255 символов.'); return trim($title); }
    private function normalize(array $base,array &$target): void
    {
        foreach ($target as &$l) {
            if (!isset($l['id'],$l['program_id'],$l['fields'],$l['steps'])||!is_array($l['steps'])) throw new Problem('document','Укажите полный состав урока и явные поля его сущностей.');
            $oldLesson=$this->plan->node($base,'lesson',$l['id']); $l['fields']=array_replace($oldLesson['fields'],$l['fields']);
            foreach ($l['steps'] as &$s) {
                if (!isset($s['id'],$s['fields'],$s['tasks'])||!array_key_exists('archived',$s)) throw new Problem('document','Укажите ID, fields, archived и полный состав задач ступени.');
                try { $old=$this->plan->node($base,'step',$s['id']); } catch (Problem) { $old=['fields'=>['notes'=>'','is_notebook'=>false],'tasks'=>[]]; }
                $s['fields']=array_replace($old['fields'],$s['fields']);
                foreach ($s['tasks'] as &$t) {
                    try { $oldTask=$this->plan->node($base,'task',$t['id']); } catch (Problem) { $oldTask=['fields'=>['solution'=>null,'max_mark'=>1,'is_star'=>false,'is_hidden'=>false],'checking'=>[]]; }
                    $t['fields']=array_replace($oldTask['fields'],$t['fields']);
                    if (!array_key_exists('checking',$t)) $t['checking']=$oldTask['checking'];
                }
                unset($t);
                if (!empty($s['fields']['is_notebook']) && (empty($old['fields']['is_notebook']) || $s['fields']['theory'] !== $old['fields']['theory'])) {
                    $s['fields']['theory']=$this->plan->notebook($old['fields']['theory']??'{}',$s['fields']['theory']);
                }
            }
            unset($s);
        }
        unset($l);
    }
    private function resources(int $program,array $docs): void
    {
        preg_match_all('~/material-resources/([a-f0-9-]{36})~i',Documents::encode($docs),$matches);
        foreach (array_unique($matches[1]) as $id) if (!DB::table('material_review_resources')->where('id',$id)->where('program_id',$program)->exists()) throw new Problem('resource','Ресурс не найден в этой программе.',['id'=>$id]);
    }
    private function impact(array $d,array $changes,array $targets): array
    {
        $tasks=[]; $deadlines=[]; $flat=Documents::flat($targets);
        foreach ($changes as $path=>$change) {
            [$type,$id,$field]=explode(':',$path,3);
            if ($type==='task'&&in_array($field,['parent','max_mark','is_hidden'],true)) {
                $tasks[]=['id'=>$id,'field'=>$field,'before'=>$change['before'],'after'=>$change['after'],
                    'attempts'=>ctype_digit($id)&&Schema::hasTable('solutions')?DB::table('solutions')->where('task_id',$id)->count():0];
                if ($field==='parent'&&$change['exists']&&ctype_digit($id)&&Schema::hasTable('deadline_policies')) {
                    $task=\App\Task::find($id); $lesson=$flat['step:'.rawurlencode((string)$change['after']).':parent']??null;
                    if ($lesson) foreach ($d['courses'] as $course) {
                        $before=$task->getDeadline($course->id); $after=app(\App\Services\CourseDeadlineService::class)->forLesson(\App\Lesson::findOrFail($lesson),$course->id)??$before;
                        $describe=function ($rule) {
                            if (!$rule) return 'Без дедлайна';
                            if (!$rule instanceof \App\Support\DeadlineRule) $rule=\App\Support\DeadlineRule::fromLegacy($rule);
                            return implode('; ',array_map(fn ($s)=>$rule->deadlineAt($s)->format('d.m.Y').' — '.$s['percent'].'%',$rule->snapshot()['stages']));
                        };
                        $deadlines[]=['task_id'=>$id,'course'=>$course->name,'before'=>$describe($before),'after'=>$describe($after)];
                    }
                }
            }
        }
        return ['courses'=>$d['courses'],'tasks'=>$tasks,'deadlines'=>$deadlines,'deferred'=>count(array_filter($d['proposals'],fn ($p)=>$p['decision']==='deferred'))];
    }
}
