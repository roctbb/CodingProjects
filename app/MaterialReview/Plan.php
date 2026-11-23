<?php
namespace App\MaterialReview;

/** Typed, deterministic operations. No fuzzy matching, model calls or executable instructions. */
class Plan
{
    public function apply(array $docs,array $operations): array
    {
        foreach ($operations as $op) {
            if (!is_array($op)) throw new Problem('operation','Операция должна быть объектом.');
            foreach (['id','lesson_id','step_id'] as $key) if (isset($op[$key])&&(!is_int($op[$key])||$op[$key]<1)&&(!is_string($op[$key])||!preg_match('/^new:[a-zA-Z0-9_-]{1,80}$/D',$op[$key]))) throw new Problem('identity','Некорректный ID сущности.',['field'=>$key]);
            $kind=$op['op']??'';
            if (in_array($kind,['question','unsupported'],true)) throw new Problem('not_executable','Для предложения нет готового исполняемого изменения.');
            if (in_array($kind,['fragment','replace','image','notebook_cell'],true)) {
                $entity=$op['entity']??''; $field=$op['field']??'';
                if (!in_array($field,Documents::FIELDS[$entity]??[],true)) throw new Problem('field','Изменение этого поля не разрешено.',['field'=>$field]);
                $node=&$this->node($docs,$entity,$op['id']??'');
                $old=$node['fields'][$field]??null;
                if (array_key_exists('expected',$op) && Documents::hash($old)!==Documents::hash($op['expected'])) throw new Problem('conflict','Поле изменено после подготовки правки.',[],409);
                if ($kind==='replace') {
                    if (!array_key_exists('value',$op)) throw new Problem('missing_value','Для замены нужно явное поле value, включая null.');
                    $new=$op['value'];
                }
                elseif ($kind==='notebook_cell') $new=$this->cell((string)$old,$op);
                else {
                    $needle=$op['old']??null;
                    if (!is_string($old)||!is_string($needle)||$needle===''||substr_count($old,$needle)!==1) throw new Problem('anchor','Точный фрагмент должен встречаться ровно один раз.',['entity'=>$entity,'id'=>$op['id'],'field'=>$field]);
                    if ($kind==='fragment'&&!array_key_exists('new',$op)) throw new Problem('missing_value','Для удаления передайте new: "" явно.');
                    if ($kind==='image'&&!\Illuminate\Support\Str::isUuid($op['resource_id']??'')) throw new Problem('resource','Нужен ID версии ресурса.');
                    if ($kind==='image'&&!Resources::safeUrl($needle)) throw new Problem('resource','Недопустимый адрес исходного изображения.');
                    $replacement=$kind==='image' ? '/material-resources/'.$op['resource_id'] : $op['new'];
                    if (!is_string($replacement)) throw new Problem('field','Новый текст должен быть строкой.');
                    $new=str_replace($needle,$replacement,$old);
                }
                $this->field($entity,$field,$new);
                if ($field==='theory' && !empty($node['fields']['is_notebook'])) $new=$this->notebook((string)$old,(string)$new);
                $node['fields'][$field]=$new;
                unset($node);
            } elseif ($kind==='create_step') {
                $lesson=&$this->node($docs,'lesson',$op['lesson_id']??'');
                $id=$this->temporary($op['key']??'');
                if ($this->exists($docs,'step',$id)) throw new Problem('identity','Временный ключ уже используется.');
                $fields=array_merge(['name'=>'','theory'=>'','notes'=>'','is_notebook'=>false],$op['fields']??[]);
                $this->fields('step',$fields);
                if (trim($fields['name'])===''||trim($fields['theory'])==='') throw new Problem('incomplete','Новой ступени нужны название и готовое содержание.');
                $step=['id'=>$id,'fields'=>$fields,'archived'=>false,'tasks'=>[]];
                $this->insert($lesson['steps'],$step,$op['after']??null);
                unset($lesson);
            } elseif ($kind==='create_task') {
                $step=&$this->node($docs,'step',$op['step_id']??''); $id=$this->temporary($op['key']??'');
                if ($this->exists($docs,'task',$id)) throw new Problem('identity','Временный ключ уже используется.');
                $fields=array_merge(['name'=>'','text'=>'','solution'=>null,'max_mark'=>1,'is_hidden'=>false,'is_star'=>false],$op['fields']??[]);
                $this->fields('task',$fields);
                if (trim($fields['name'])===''||trim($fields['text'])==='') throw new Problem('incomplete','Новой задаче нужны название и условие.');
                $this->insert($step['tasks'],['id'=>$id,'fields'=>$fields,'checking'=>[]],$op['after']??null);
                unset($step);
            } elseif ($kind==='archive_task') {
                $task=&$this->node($docs,'task',$op['id']??''); $task['fields']['is_hidden']=true; unset($task);
            } elseif ($kind==='archive_step') {
                $step=&$this->node($docs,'step',$op['id']??'');
                if (!array_key_exists('tasks',$op)) throw new Problem('task_fate','Укажите судьбу каждой задачи: hide или move.');
                foreach ($step['tasks'] as $task) if (($op['tasks'][(string)$task['id']]??null)!=='hide' && ($op['tasks'][(string)$task['id']]??null)!=='moved') throw new Problem('task_fate','Не указана судьба задачи.',['task_id'=>$task['id']]);
                foreach ($step['tasks'] as &$task) {
                    if ($op['tasks'][(string)$task['id']]==='moved') throw new Problem('task_fate','Сначала выполните перенос задачи из исключаемой ступени.');
                    $task['fields']['is_hidden']=true;
                }
                unset($task); $step['archived']=true; unset($step);
            } elseif (in_array($kind,['move_step','move_task'],true)) {
                $entity=$kind==='move_step'?'step':'task'; $collection=$entity==='step'?'steps':'tasks';
                $moved=null;
                foreach ($docs as &$lesson) {
                    if ($entity==='step') $this->take($lesson['steps'],$op['id']??'', $moved);
                    else foreach ($lesson['steps'] as &$step) $this->take($step['tasks'],$op['id']??'', $moved);
                    unset($step);
                }
                unset($lesson);
                if (!$moved) throw new Problem('target','Цель переноса не найдена.');
                $parent=&$this->node($docs,$entity==='step'?'lesson':'step',$op[$entity==='step'?'lesson_id':'step_id']??'');
                $this->insert($parent[$collection],$moved,$op['after']??null); unset($parent);
            } elseif (in_array($kind,['reorder_steps','reorder_tasks'],true)) {
                $parent=&$this->node($docs,$kind==='reorder_steps'?'lesson':'step',$op['id']??'');
                $list=$kind==='reorder_steps'?'steps':'tasks'; $order=$op['order']??[];
                $existing=array_map('strval',array_column($parent[$list],'id')); $given=array_map('strval',$order);
                $a=$existing; $b=$given; sort($a); sort($b);
                if ($a!==$b || count(array_unique($given))!==count($given)) throw new Problem('order','Порядок должен содержать все существующие ID ровно по одному разу.');
                $nodes=array_column($parent[$list],null,'id'); $parent[$list]=array_map(fn ($id)=>$nodes[$id],$order); unset($parent);
            } else throw new Problem('operation','Неизвестная операция.',['op'=>$kind]);
        }
        return $docs;
    }
    public function validate(array $base,array $target): array
    {
        if (array_map('strval',array_column($base,'id'))!==array_map('strval',array_column($target,'id'))) throw new Problem('lessons','Состав уроков пакета должен совпадать с основой.');
        $seen=['lesson'=>[],'step'=>[],'task'=>[]]; $temporaryKeys=[];
        foreach ($target as $l) {
            $original=$this->node($base,'lesson',$l['id']);
            if ($original['program_id']!==$l['program_id'] || ($original['chapter_id']??null)!==($l['chapter_id']??null)) throw new Problem('program','Недопустима смена программы или главы.');
            $nodes=[['lesson',$l]];
            foreach ($l['steps'] as $s) {
                $nodes[]=['step',$s];
                if (!is_bool($s['archived']??null)) throw new Problem('archive','Укажите archived явно.');
                foreach ($s['tasks'] as $t) $nodes[]=['task',$t];
            }
            foreach ($nodes as [$entity,$node]) {
                if ((!is_int($node['id'])||$node['id']<1)&&(!is_string($node['id'])||!preg_match('/^new:[a-zA-Z0-9_-]{1,80}$/D',$node['id']))) throw new Problem('identity','ID сущности должен быть положительным целым или временным ключом.');
                $key=(string)$node['id'];
                if (isset($seen[$entity][$key])) throw new Problem('identity','ID сущности повторяется.',['id'=>$key]);
                $seen[$entity][$key]=true;
                if (!$this->exists($base,$entity,$key)) {
                    if (isset($temporaryKeys[$key])) throw new Problem('identity','Временный ключ должен быть уникален во всём пакете.',['id'=>$key]);
                    $temporaryKeys[$key]=true;
                    $this->temporary($key); $this->fields($entity,$node['fields']);
                    if (empty($node['fields']['name'])) throw new Problem('incomplete','Новой сущности нужно название.');
                    if ($entity==='step' && empty($node['fields']['theory'])) throw new Problem('incomplete','Новой ступени нужно содержание.');
                    if ($entity==='task' && (empty($node['fields']['text']) || !empty($node['checking']))) throw new Problem('checking','Новая задача должна содержать условие; проверку настраивают отдельным поддерживаемым предложением.');
                }
            }
        }
        $changes=Documents::changes($base,$target);
        foreach ($changes as $path=>$change) {
            [$entity,$id,$field]=explode(':',$path,3);
            if (in_array($field,['parent','order','archived'],true)) continue;
            if ($field==='checking') {
                if (!empty($change['after'])) throw new Problem('unsupported','Изменение конфигурации проверки требует отдельной валидации.',['path'=>$path]);
                continue;
            }
            if (!in_array($field,Documents::FIELDS[$entity]??[],true)) throw new Problem('field','Поле доступно только для чтения.',['path'=>$path]);
            $this->field($entity,$field,$change['after']);
        }
        return $changes;
    }
    public function &node(array &$docs,string $entity,int|string $id): array
    {
        foreach ($docs as &$lesson) {
            if ($entity==='lesson' && (string)$lesson['id']===(string)$id) return $lesson;
            foreach ($lesson['steps'] as &$step) {
                if ($entity==='step' && (string)$step['id']===(string)$id) return $step;
                foreach ($step['tasks'] as &$task) if ($entity==='task' && (string)$task['id']===(string)$id) return $task;
            }
        }
        throw new Problem('target','Цель отсутствует в снимке программы.',['entity'=>$entity,'id'=>$id]);
    }
    private function exists(array $docs,string $entity,int|string $id): bool
    { try { $this->node($docs,$entity,$id); return true; } catch (Problem) { return false; } }
    private function fields(string $entity,array $fields): void
    { foreach ($fields as $k=>$v) { if (!in_array($k,Documents::FIELDS[$entity]??[],true)) throw new Problem('field','Неизвестное изменяемое поле.',['field'=>$k]); $this->field($entity,$k,$v); } }
    private function field(string $entity,string $field,mixed $value): void
    {
        if ($field==='max_mark') { if (!is_int($value)||$value<0||$value>1000) throw new Problem('points','Максимальный балл должен быть целым от 0 до 1000.'); }
        elseif (str_starts_with($field,'is_')||str_starts_with($field,'only_')) { if (!is_bool($value)&&!in_array($value,[0,1],true)) throw new Problem('boolean','Ожидается логическое значение.'); }
        elseif ($value!==null && !is_string($value)) throw new Problem('text','Ожидается строка или null.',['field'=>$field]);
        if ($field==='name' && (!is_string($value)||trim($value)===''||mb_strlen($value)>255)) throw new Problem('name','Название должно содержать от 1 до 255 символов.');
    }
    private function temporary(string $key): string
    { if (!preg_match('/^new:[a-zA-Z0-9_-]{1,80}$/D',$key)) throw new Problem('identity','Новой сущности нужен ключ new:имя.'); return $key; }
    private function insert(array &$list,array $node,int|string|null $after): void
    {
        if ($after===null) { $list[]=$node; return; }
        if ($after==='start') { array_unshift($list,$node); return; }
        $i=array_search((string)$after,array_map('strval',array_column($list,'id')),true);
        if ($i===false) throw new Problem('position','Опорная сущность отсутствует.');
        array_splice($list,$i+1,0,[$node]);
    }
    private function take(array &$list,int|string $id,?array &$found): void
    { foreach ($list as $i=>$node) if ((string)$node['id']===(string)$id) { $found=$node; array_splice($list,$i,1); return; } }
    public function cell(string $json,array $op): string
    {
        if (!array_key_exists('value',$op)||!is_string($op['value'])) throw new Problem('missing_value','Нужно полное новое содержимое ячейки value.');
        try { $book=json_decode($json,false,512,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new Problem('cell','В текущей версии материала нет ячеек Jupyter.'); }
        $index=null;
        foreach ($book->cells??[] as $i=>$cell) if (isset($op['cell_id']) && ($cell->id??null)===$op['cell_id']) { if ($index!==null) throw new Problem('cell','ID ячейки неоднозначен.'); $index=$i; }
        if (!isset($op['cell_id'])) $index=$op['cell_index']??null;
        if (!is_int($index)||!isset($book->cells[$index])) throw new Problem('cell','Ячейка не найдена.');
        $cell=$book->cells[$index]; $source=is_array($cell->source)?implode('',$cell->source):$cell->source;
        if (array_key_exists('old',$op) && $op['old']!==$source) throw new Problem('cell','Основа ячейки изменилась.');
        $cell->source=$op['value'];
        return Documents::encode($book);
    }
    public function notebook(string $old,string $new): string
    {
        try { $a=json_decode($old,false,512,JSON_THROW_ON_ERROR); $b=json_decode($new,false,512,JSON_THROW_ON_ERROR); } catch (\Throwable) { throw new Problem('notebook','Некорректный JSON Jupyter.'); }
        if (($b->nbformat??null)!==4 || !isset($b->cells) || !is_array($b->cells)) throw new Problem('notebook','Ожидается Jupyter Notebook версии 4.');
        $ids=[];
        foreach ($b->cells as $i=>$cell) {
            if (!is_object($cell)||!in_array($cell->cell_type??'', ['markdown','raw','code'],true)||!isset($cell->source)||(!is_string($cell->source)&&!is_array($cell->source))) throw new Problem('notebook','Некорректная ячейка.');
            if (isset($cell->id)) { if (isset($ids[$cell->id])) throw new Problem('notebook','ID ячейки повторяется.'); $ids[$cell->id]=true; }
            $previous=isset($cell->id) ? collect($a->cells??[])->firstWhere('id',$cell->id) : ($a->cells[$i]??null);
            $text=fn ($c)=>is_array($c->source??null)?implode('',$c->source):($c->source??'');
            if ($cell->cell_type==='code' && (!$previous||$text($previous)!==$text($cell))) { $cell->outputs=[]; $cell->execution_count=null; }
        }
        unset($cell); return Documents::encode($b);
    }
}
