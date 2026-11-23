<?php
namespace App\MaterialReview;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Canonical, lossless snapshots. Runtime tables remain the published source of truth. */
class Documents
{
    public const FIELDS = [
        'lesson'=>['name','description','image'],
        'step'=>['name','description','theory','notes','video_url','is_notebook','image'],
        'task'=>['name','text','solution','max_mark','is_star','is_hidden','only_class','only_remote','image'],
    ];

    public static function encode(mixed $value): string { return json_encode($value, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    public static function decode(?string $value): mixed { return json_decode($value ?? 'null',true,512,JSON_THROW_ON_ERROR); }
    public static function hash(mixed $value): string
    {
        $sort = function ($v) use (&$sort) {
            if (is_object($v)) $v=get_object_vars($v);
            if (!is_array($v)) return $v;
            if (!array_is_list($v)) ksort($v);
            return array_map($sort,$v);
        };
        return hash('sha256',self::encode($sort($value)));
    }
    public function installed(): bool { return Schema::hasTable('material_review_revisions'); }
    public function installation(): string
    {
        DB::table('material_review_installations')->insertOrIgnore(['id'=>1,'uuid'=>(string)Str::uuid(),'signing_key'=>bin2hex(random_bytes(32))]);
        return DB::table('material_review_installations')->where('id',1)->value('uuid');
    }
    public function sign(array $package): string
    {
        $this->installation(); unset($package['archive_signature']);
        return hash_hmac('sha256',self::hash($package),DB::table('material_review_installations')->where('id',1)->value('signing_key'));
    }
    public function lesson(int $id): array
    {
        $lesson = DB::table('lessons')->find($id);
        if (!$lesson) throw new Problem('not_found','Урок не найден.',[],404);
        $fields = fn ($row) => array_diff_key((array)$row,array_flip(['id','created_at','updated_at','program_id','chapter_id','lesson_id','step_id','sort_index','review_archived_at']));
        $doc=['id'=>$id,'program_id'=>(int)$lesson->program_id,'chapter_id'=>$lesson->chapter_id ?? null,'fields'=>$fields($lesson),'steps'=>[]];
        foreach (DB::table('program_steps')->where('lesson_id',$id)->orderBy('sort_index')->orderBy('id')->get() as $step) {
            $s=['id'=>(int)$step->id,'fields'=>$fields($step),'archived'=>!empty($step->review_archived_at),'tasks'=>[]];
            foreach (DB::table('tasks')->where('step_id',$step->id)->orderBy('sort_index')->orderBy('id')->get() as $task) {
                $t=['id'=>(int)$task->id,'fields'=>$fields($task),'checking'=>[]];
                foreach (['code_tasks','code_task_tests','code_task_checkers','code_task_files'] as $table) if (Schema::hasTable($table)) {
                    $column = Schema::hasColumn($table,'task_id') ? 'task_id' : 'id';
                    $t['checking'][$table]=DB::table($table)->where($column,$task->id)->orderBy($column)->get()->map(fn ($r)=>(array)$r)->all();
                }
                $s['tasks'][]=$t;
            }
            $doc['steps'][]=$s;
        }
        $doc['resources']=$this->resources($doc);
        return $doc;
    }
    private function resources(array $doc): array
    {
        $out=[]; $scan=function (string $entity,int|string $id,array $fields) use (&$out,$doc) {
            foreach ($fields as $field=>$value) {
                if (!is_string($value)) continue;
                $texts=[$value];
                if ($field==='theory'&&!empty($fields['is_notebook'])) {
                    try { $book=self::decode($value); $texts=array_map(fn ($c)=>is_array($c['source']??null)?implode('',$c['source']):($c['source']??''),$book['cells']??[]); } catch (\Throwable) { continue; }
                }
                foreach ($texts as $index=>$text) {
                    preg_match_all('~!\[[^\]]*\]\(([^\s)]+)(?:\s+[^)]*)?\)~u',$text,$matches);
                    foreach (array_unique($matches[1]) as $url) {
                        $resource=app(Resources::class)->capture($doc['program_id'],$url);
                        $out[]=['entity'=>$entity,'id'=>$id,'field'=>$field,'cell_index'=>count($texts)>1?$index:null,'url'=>$url,
                            'resource_id'=>$resource['id']??null,'sha256'=>$resource['sha256']??null,'mime'=>$resource['mime']??null,'captured'=>$resource!==null];
                    }
                }
            }
        };
        $scan('lesson',$doc['id'],$doc['fields']);
        foreach ($doc['steps'] as $s) { $scan('step',$s['id'],$s['fields']); foreach ($s['tasks'] as $t) $scan('task',$t['id'],$t['fields']); }
        return $out;
    }
    public function revision(int $lesson, string $source='snapshot', ?int $actor=null): array
    {
        return $this->remember($this->lesson($lesson),$source,$actor);
    }
    public function remember(array $doc,string $source,?int $actor=null): array
    {
        $hash=self::hash($doc);
        $last=DB::table('material_review_revisions')->where('lesson_id',$doc['id'])->orderByDesc('sequence')->first();
        if ($last && $last->hash===$hash) return $this->read($last->id);
        $id=(string)Str::uuid();
        DB::table('material_review_revisions')->insert(['id'=>$id,'program_id'=>$doc['program_id'],'lesson_id'=>$doc['id'],'base_id'=>$last?->id,
            'hash'=>$hash,'source'=>$source,'actor_id'=>$actor,'snapshot'=>self::encode($doc),'created_at'=>now()]);
        if ($last) DB::table('material_review_progress')->where('program_id',$doc['program_id'])->where('lesson_id',$doc['id'])->update(['reviewed_hash'=>null,'updated_at'=>now()]);
        return $this->read($id);
    }
    public function read(string $id): array
    {
        $r=DB::table('material_review_revisions')->find($id);
        if (!$r) throw new Problem('not_found','Редакция не найдена.',[],404);
        return array_merge((array)$r,['snapshot'=>self::decode($r->snapshot)]);
    }
    /** Flatten identities, ownership and order separately from content, enabling field-level three-way merge. */
    public static function flat(array $docs): array
    {
        $out=[];
        foreach ($docs as $doc) {
            $l=(string)$doc['id'];
            foreach ($doc['fields'] as $k=>$v) $out["lesson:$l:$k"]=$v;
            $out["lesson:$l:order"]=array_column($doc['steps'],'id');
            foreach ($doc['steps'] as $s) {
                $id=rawurlencode((string)$s['id']); $out["step:$id:parent"]=$doc['id']; $out["step:$id:archived"]=$s['archived'];
                foreach ($s['fields'] as $k=>$v) $out["step:$id:$k"]=$v;
                $out["step:$id:order"]=array_column($s['tasks'],'id');
                foreach ($s['tasks'] as $t) {
                    $tid=rawurlencode((string)$t['id']); $out["task:$tid:parent"]=$s['id'];
                    foreach ($t['fields'] as $k=>$v) $out["task:$tid:$k"]=$v;
                    $out["task:$tid:checking"]=$t['checking'];
                }
            }
        }
        ksort($out); return $out;
    }
    public static function changes(array $before,array $after): array
    {
        $a=self::flat($before); $b=self::flat($after); $changes=[];
        foreach ($b as $path=>$value) if (!array_key_exists($path,$a) || self::hash($a[$path])!==self::hash($value)) {
            $changes[$path]=['exists'=>array_key_exists($path,$a),'before'=>$a[$path]??null,'after'=>$value];
        }
        foreach (array_diff_key($a,$b) as $path=>$value) throw new Problem('implicit_removal','Сущность или поле пропущены. Исключение должно быть явным.',['path'=>$path]);
        return $changes;
    }
    public static function inflate(array $flat,array $template): array
    {
        $nodes=['lesson'=>[],'step'=>[],'task'=>[]];
        foreach ($flat as $path=>$value) {
            [$type,$id,$field]=explode(':',$path,3);
            $id=rawurldecode($id);
            $nodes[$type][$id]['id']=ctype_digit((string)$id)?(int)$id:$id;
            if (in_array($field,['parent','order','archived','checking'],true)) $nodes[$type][$id][$field]=$value;
            else $nodes[$type][$id]['fields'][$field]=$value;
        }
        $out=[];
        foreach ($template as $original) {
            $l=$nodes['lesson'][$original['id']]; $doc=array_merge($original,['fields'=>$l['fields'],'steps'=>[]]);
            foreach ($l['order'] as $sid) {
                $s=$nodes['step'][$sid]??null;
                if (!$s || (string)$s['parent']!==(string)$l['id']) throw new Problem('structure','Некорректный состав ступеней.');
                $step=['id'=>$s['id'],'fields'=>$s['fields'],'archived'=>$s['archived'],'tasks'=>[]];
                foreach ($s['order'] as $tid) {
                    $t=$nodes['task'][$tid]??null;
                    if (!$t || (string)$t['parent']!==(string)$s['id']) throw new Problem('structure','Некорректная принадлежность задачи.');
                    $step['tasks'][]=['id'=>$t['id'],'fields'=>$t['fields'],'checking'=>$t['checking']??[]];
                }
                $doc['steps'][]=$step;
            }
            $out[]=$doc;
        }
        if (count($nodes['step'])!==array_sum(array_map(fn ($l)=>count($l['steps']),$out)) || count($nodes['task'])!==array_sum(array_map(fn ($l)=>array_sum(array_map(fn ($s)=>count($s['tasks']),$l['steps'])),$out))) throw new Problem('structure','Сущность потеряна из маршрута.');
        return $out;
    }
    public static function merge(array $base,array $target,array $current): array
    {
        $flat=self::flat($current); $issues=[];
        foreach (self::changes($base,$target) as $path=>$c) {
            $exists=array_key_exists($path,$flat);
            if ($exists && self::hash($flat[$path])===self::hash($c['after'])) continue;
            if ($exists!==$c['exists'] || ($exists && self::hash($flat[$path])!==self::hash($c['before']))) $issues[]=['path'=>$path,'base'=>$c['before'],'current'=>$flat[$path]??null,'proposed'=>$c['after']];
            else $flat[$path]=$c['after'];
        }
        if ($issues) throw new Problem('conflict','Основа изменена. Нужна новая версия предложения.',$issues,409);
        return self::inflate($flat,$current);
    }
}
