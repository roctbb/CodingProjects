<?php
namespace App\MaterialReview;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** The only bridge from reviewed drafts to published models. All linked lessons commit together. */
class Publishing
{
    public function __construct(private Review $review) {}
    public function publish(User $user,string $id,int $version): array
    {
        $this->review->admin($user);
        return DB::transaction(function () use ($user,$id,$version) {
            DB::table('material_review_drafts')->where('id',$id)->lockForUpdate()->first();
            if ($prior=DB::table('material_review_publications')->where('draft_id',$id)->first()) return ['id'=>$prior->id,'duplicate'=>true];
            $d=$this->review->locked($user,$id,$version);
            if ($d['state']!=='selected') throw new Problem('selection','Сначала выберите редакцию урока.');
            DB::table('programs')->where('id',$d['program_id'])->lockForUpdate()->first();
            foreach ($d['base_documents'] as $l) {
                DB::table('lessons')->where('id',$l['id'])->lockForUpdate()->first();
                $steps=DB::table('program_steps')->where('lesson_id',$l['id'])->lockForUpdate()->pluck('id');
                DB::table('tasks')->whereIn('step_id',$steps)->lockForUpdate()->get();
            }
            $check=$this->review->check($user,$id);
            if (!$check['valid']) throw new Problem('publication_blocked','Публикация заблокирована.',$check['issues'],409);
            if (!empty($d['metadata']['restore_publication'])) $this->assertDeadlineRestore($d['metadata']['restore_publication']);
            $before=array_map(fn ($l)=>$this->review->documents->lesson($l['id']),$d['base_documents']);
            $target=$check['merged']; $idMap=[]; $deadlines=$this->deadlinesBefore($check['changes'],$d['courses']);
            $originalOrder=Documents::flat($before);
            $scoreChanges=[];
            foreach ($target as &$l) {
                $this->write('lessons',$l['id'],$l['fields']);
                foreach ($l['steps'] as $i=>&$s) {
                    $s['id']=$this->identity('program_steps',$s['id'],$idMap,['program_id'=>$d['program_id'],'lesson_id'=>$l['id'],'sort_index'=>$i+1]);
                    $position=($originalOrder['lesson:'.$l['id'].':order']??[])!==array_column($l['steps'],'id')?['sort_index'=>$i+1]:[];
                    $archive=$s['archived']?(DB::table('program_steps')->where('id',$s['id'])->value('review_archived_at')??now()):null;
                    $this->write('program_steps',$s['id'],array_merge($s['fields'],['program_id'=>$d['program_id'],'lesson_id'=>$l['id'],'review_archived_at'=>$archive],$position));
                    foreach ($s['tasks'] as $j=>&$t) {
                        $t['id']=$this->identity('tasks',$t['id'],$idMap,['step_id'=>$s['id'],'sort_index'=>$j+1]);
                        $previous=DB::table('tasks')->find($t['id']);
                        if (isset($t['fields']['max_mark'])&&(int)$t['fields']['max_mark']<(int)($previous->max_mark??0)) $scoreChanges=array_merge($scoreChanges,$this->cap($t['id'],(int)$t['fields']['max_mark']));
                        $position=($originalOrder['step:'.$s['id'].':order']??[])!==array_column($s['tasks'],'id')?['sort_index'=>$j+1]:[];
                        $this->write('tasks',$t['id'],array_merge($t['fields'],['step_id'=>$s['id']],$position));
                    }
                    unset($t);
                }
                unset($s);
            }
            unset($l);
            $this->moveDeadlines($deadlines);
            if (!empty($d['metadata']['restore_publication'])) {
                $scoreChanges=array_merge($scoreChanges,$this->restoreScores($d['metadata']['restore_publication']));
                $this->restoreDeadlines($d['metadata']['restore_publication']);
            }
            foreach ($deadlines as &$entry) $entry['after_policy']=$this->policy($entry['course_id'],$entry['task_id']);
            unset($entry);
            $after=[];
            foreach ($target as $l) { $after[]=$this->review->documents->lesson($l['id']); $this->review->documents->remember(end($after),'publication',$user->id); }
            $publication=(string)Str::uuid();
            DB::table('material_review_publications')->insert(['id'=>$publication,'draft_id'=>$id,'program_id'=>$d['program_id'],'actor_id'=>$user->id,
                'before'=>Documents::encode($before),'after'=>Documents::encode($after),'id_map'=>Documents::encode($idMap),
                'score_changes'=>Documents::encode($scoreChanges),'deadline_changes'=>Documents::encode($deadlines),'created_at'=>now()]);
            DB::table('material_review_drafts')->where('id',$id)->update(['state'=>'published','targets'=>Documents::encode($after),'version'=>$version+1,'updated_at'=>now()]);
            DB::table('material_review_proposals')->where('draft_id',$id)->where('decision','accepted')->update(['published_at'=>now()]);
            $this->review->event($id,'published',['publication_id'=>$publication,'id_map'=>$idMap],$user->id);
            $this->recount($d['courses']);
            return ['id'=>$publication,'duplicate'=>false,'id_map'=>$idMap];
        });
    }
    public function reverse(User $user,string $publication): array
    {
        $this->review->admin($user); $p=DB::table('material_review_publications')->find($publication);
        if (!$p) throw new Problem('not_found','Публикация не найдена.',[],404);
        $before=Documents::decode($p->before); $after=Documents::decode($p->after);
        // New entities are archived/hidden, preserving all attempts created since publication.
        $a=Documents::flat($after); $b=Documents::flat($before); $inverse=$a;
        foreach ($a as $path=>$value) {
            if (array_key_exists($path,$b)) $inverse[$path]=$b[$path];
            else { [$type,$id,$field]=explode(':',$path,3); if ($field==='archived') $inverse[$path]=true; if ($type==='task'&&$field==='is_hidden') $inverse[$path]=true; }
        }
        foreach ($a as $path=>$value) if (str_ends_with($path,':order')) {
            $entity=str_starts_with($path,'lesson:')?'step':'task';
            $newIds=array_filter($value,fn ($id)=>!array_key_exists($entity.':'.rawurlencode((string)$id).':parent',$b));
            $inverse[$path]=array_values(array_unique(array_merge($b[$path]??[],$newIds),SORT_REGULAR));
        }
        $target=Documents::inflate($inverse,$after); $bases=[];
        foreach ($after as $doc) $bases[]=$this->review->documents->remember($doc,'reverse_base',$user->id)['id'];
        $d=$this->review->create($user,['format_version'=>1,'installation_id'=>$this->review->documents->installation(),'program_id'=>(int)$p->program_id,
            'base_revision_ids'=>$bases,'title'=>'Восстановление публикации','reason'=>'Проверяемое обратное изменение публикации '.$publication,'targets'=>$target]);
        $metadata=$d['metadata']; $metadata['restore_publication']=$publication;
        DB::table('material_review_drafts')->where('id',$d['id'])->update(['metadata'=>Documents::encode($metadata)]);
        return $this->review->get($user,$d['id']);
    }
    private function identity(string $table,int|string $id,array &$map,array $parents): int
    {
        if (is_int($id)||ctype_digit((string)$id)) return (int)$id;
        if (!isset($map[$id])) {
            $data=array_merge($parents,['created_at'=>now(),'updated_at'=>now()]);
            $columns=Schema::getColumnListing($table); $data=array_intersect_key($data,array_flip($columns));
            // Required content is supplied in the initial INSERT, not after creating an invalid entity.
            $data['name']='Новый материал';
            if ($table==='tasks') { $data['text']='Новая задача'; $data['max_mark']=1; }
            $map[$id]=DB::table($table)->insertGetId($data);
        }
        return $map[$id];
    }
    private function write(string $table,int $id,array $fields): void
    {
        $columns=Schema::getColumnListing($table); $row=(array)DB::table($table)->find($id); $update=[];
        foreach ($fields as $k=>$v) if (in_array($k,$columns,true)&&($row[$k]??null)!=$v) $update[$k]=$v;
        if ($update) { if (in_array('updated_at',$columns,true)) $update['updated_at']=now(); DB::table($table)->where('id',$id)->update($update); }
    }
    private function cap(int $task,int $maximum): array
    {
        if (!Schema::hasTable('solutions')) return [];
            $columns=Schema::getColumnListing('solutions'); $fields=array_values(array_intersect(['mark','raw_mark','evaluated_mark','material_score_cap','checked'],$columns)); $changes=[];
        foreach (DB::table('solutions')->where('task_id',$task)->lockForUpdate()->get() as $row) {
            $old=[]; $new=[];
            foreach ($fields as $f) { $old[$f]=$row->$f; $new[$f]=$row->$f; }
            $new['mark']=$row->mark===null?null:min((int)$row->mark,$maximum);
            $new['material_score_cap']=$row->material_score_cap===null?$maximum:min((int)$row->material_score_cap,$maximum);
            if ($old!==$new) { DB::table('solutions')->where('id',$row->id)->update($new); $changes[]=['id'=>$row->id,'before'=>$old,'after'=>$new]; }
        }
        return $changes;
    }
    private function restoreScores(string $id): array
    {
        $p=DB::table('material_review_publications')->find($id); $changes=[];
        foreach (Documents::decode($p->score_changes) as $c) {
            $row=DB::table('solutions')->where('id',$c['id'])->lockForUpdate()->first();
            if (!$row) throw new Problem('score_history','Историческая работа отсутствует.',[],409);
            $now=array_intersect_key((array)$row,$c['after']);
            if (Documents::hash($now)!==Documents::hash($c['after'])) throw new Problem('score_conflict','Оценка изменена позже; автоматическое восстановление заблокировано.',['solution_id'=>$c['id']],409);
            DB::table('solutions')->where('id',$c['id'])->update($c['before']);
            $changes[]=['id'=>$c['id'],'before'=>$now,'after'=>$c['before']];
        }
        return $changes;
    }
    private function deadlinesBefore(array $changes,array $courses): array
    {
        $out=[];
        if (!Schema::hasTable('deadline_policies')) return $out;
        foreach ($changes as $path=>$change) {
            [$type,$id,$field]=explode(':',$path,3);
            if ($type!=='task'||$field!=='parent'||!ctype_digit($id)||!$change['exists']) continue;
            $task=\App\Task::find($id);
            foreach ($courses as $course) {
                $cid=is_object($course)?$course->id:$course['id']; $rule=$task->getDeadline($cid);
                $out[]=['task_id'=>(int)$id,'course_id'=>$cid,'before_policy'=>$this->policy($cid,(int)$id),'rule'=>$rule?($rule instanceof \App\Support\DeadlineRule?$rule:\App\Support\DeadlineRule::fromLegacy($rule))->snapshot():null];
            }
        }
        return $out;
    }
    private function moveDeadlines(array $entries): void
    {
        foreach ($entries as $entry) {
            $task=\App\Task::with('step.lesson')->findOrFail($entry['task_id']); $cid=$entry['course_id'];
            app(\App\Services\CourseDeadlineService::class)->forget($cid);
            $rule=app(\App\Services\CourseDeadlineService::class)->forLesson($task->step->lesson,$cid);
            $snapshot=$rule?->snapshot()??$entry['rule'];
            $existing=DB::table('deadline_policies')->where('course_id',$cid)->where('target_type','task')->where('target_id',$task->id)->first();
            $inherit=$rule&&!$task->is_star;
            $values=['mode'=>$inherit?'inherit':($snapshot?'custom':'none'),'timezone'=>$snapshot['timezone']??config('app.timezone'),'legacy'=>$snapshot['legacy']??false,'revision'=>($existing->revision??0)+1,'updated_at'=>now()];
            if ($existing) { $pid=$existing->id; DB::table('deadline_policies')->where('id',$pid)->update($values); DB::table('deadline_stages')->where('policy_id',$pid)->delete(); }
            else $pid=DB::table('deadline_policies')->insertGetId(array_merge($values,['course_id'=>$cid,'target_type'=>'task','target_id'=>$task->id,'created_at'=>now()]));
            foreach ($inherit?[]:($snapshot['stages']??[]) as $i=>$s) DB::table('deadline_stages')->insert(['policy_id'=>$pid,'cutoff_at'=>\Carbon\Carbon::parse($s['at'],'UTC')->format('Y-m-d H:i:s'),'retained_percent'=>$s['percent'],'position'=>$i,'created_at'=>now(),'updated_at'=>now()]);
            app(\App\Services\CourseDeadlineService::class)->forget($cid);
        }
    }
    private function policy(int $course,int $task): ?array
    {
        $p=DB::table('deadline_policies')->where('course_id',$course)->where('target_type','task')->where('target_id',$task)->first();
        if (!$p) return null;
        return ['id'=>$p->id,'mode'=>$p->mode,'timezone'=>$p->timezone,'revision'=>$p->revision,'legacy'=>$p->legacy,
            'stages'=>DB::table('deadline_stages')->where('policy_id',$p->id)->orderBy('position')->get(['cutoff_at','retained_percent','position'])->map(fn ($r)=>(array)$r)->all()];
    }
    private function assertDeadlineRestore(string $id): void
    {
        $p=DB::table('material_review_publications')->find($id);
        foreach (Documents::decode($p->deadline_changes) as $entry) if (array_key_exists('after_policy',$entry)&&Documents::hash($this->policy($entry['course_id'],$entry['task_id']))!==Documents::hash($entry['after_policy'])) throw new Problem('deadline_conflict','Дедлайн задачи изменён после публикации; восстановление требует решения автора.',['task_id'=>$entry['task_id'],'course_id'=>$entry['course_id']],409);
    }
    private function restoreDeadlines(string $id): void
    {
        $p=DB::table('material_review_publications')->find($id);
        foreach (Documents::decode($p->deadline_changes) as $entry) {
            $current=$this->policy($entry['course_id'],$entry['task_id']); $before=$entry['before_policy']??null;
            if ($current) { DB::table('deadline_stages')->where('policy_id',$current['id'])->delete(); DB::table('deadline_policies')->where('id',$current['id'])->delete(); }
            if ($before) {
                $data=array_diff_key($before,['stages'=>true]); $data['course_id']=$entry['course_id']; $data['target_type']='task'; $data['target_id']=$entry['task_id'];
                $data['revision']=max($before['revision'],$current['revision']??0)+1; $data['created_at']=now(); $data['updated_at']=now();
                DB::table('deadline_policies')->insert($data);
                foreach ($before['stages'] as $stage) DB::table('deadline_stages')->insert($stage+['policy_id'=>$before['id'],'created_at'=>now(),'updated_at'=>now()]);
            }
            app(\App\Services\CourseDeadlineService::class)->forget($entry['course_id']);
        }
    }
    private function recount(array $courses): void
    {
        if (!Schema::hasTable('course_student_points')||!Schema::hasTable('lesson_student_stats')) return;
        foreach ($courses as $course) {
            $cid=is_object($course)?$course->id:$course['id'];
            \App\CourseStudentPoints::recalculateForStudents($cid);
            \App\LessonStudentStats::recalculateForStudents($cid,null,false);
            foreach (DB::table('course_students')->where('course_id',$cid)->pluck('user_id') as $id) if ($u=User::find($id)) $u->rescore();
        }
    }
}
