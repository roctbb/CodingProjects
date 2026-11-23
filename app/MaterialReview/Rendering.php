<?php
namespace App\MaterialReview;

class Rendering
{
    public static function versioned(string $html,array $documents): string
    {
        foreach ($documents as $doc) foreach ($doc['resources']??[] as $r) if (!empty($r['resource_id'])) {
            $from=e($r['url']); $to='/material-resources/'.$r['resource_id'];
            $html=str_replace(['src="'.$from.'"','href="'.$from.'"','data-image-preview="'.$from.'"'],['src="'.$to.'"','href="'.$to.'"','data-image-preview="'.$to.'"'],$html);
        }
        return $html;
    }
    public static function label(string $path,array $docs): string
    {
        [$entity,$id,$field]=explode(':',$path,3); $id=rawurldecode($id);
        try { $node=app(Plan::class)->node($docs,$entity,$id); $name=$node['fields']['name']??''; } catch (Problem) { $name='Новый материал'; }
        $labels=['name'=>'Название','description'=>'Описание','theory'=>'Теория','notes'=>'Заметки преподавателя','text'=>'Условие задачи','solution'=>'Авторское решение','max_mark'=>'Максимальный балл','is_hidden'=>'Скрытие задачи','is_star'=>'Обязательность задачи','order'=>'Порядок','parent'=>'Расположение','archived'=>'Исключение из маршрута','checking'=>'Настройки проверки','video_url'=>'Видео','is_notebook'=>'Формат материала','image'=>'Изображение'];
        return ($labels[$field]??'Настройка материала').' · '.$name;
    }
    /** Put each pending change at its Markdown block, retaining one copy of the surrounding lesson. */
    public static function pending(string $text,array $documents,?array $draft,string $entity,int|string $id,string $field): ?string
    {
        if (!$draft||$draft['state']==='published') return null;
        $regions=[];
        foreach ($draft['proposals'] as $proposal) {
            if ($proposal['decision']!=='pending') continue;
            foreach ($proposal['payload']['operations']??[] as $op) {
                if (($op['entity']??'')!==$entity||(string)($op['id']??'')!==(string)$id||($op['field']??'')!==$field) continue;
                if (!in_array($op['op']??'',['fragment','image','replace'],true)) continue;
                try { $next=app(Plan::class)->apply($documents,[$op]); $node=app(Plan::class)->node($next,$entity,$id); }
                catch (Problem) { continue; }
                $start=0; $end=strlen($text);
                if (($op['op']??'')!=='replace') {
                    $at=strpos($text,$op['old']);
                    $previous=strrpos(substr($text,0,$at),"\n\n"); $start=$previous===false?0:$previous+2;
                    $following=strpos($text,"\n\n",$at+strlen($op['old'])); $end=$following===false?strlen($text):$following;
                }
                $old=substr($text,$start,$end-$start);
                $new=($op['op']??'')==='replace'?(string)$node['fields'][$field]:str_replace($op['old'],($op['op']==='image'?'/material-resources/'.$op['resource_id']:$op['new']),$old);
                $region=$start.':'.$end;
                $regions[$region]??=['start'=>$start,'end'=>$end,'html'=>''];
                $regions[$region]['html'].=view('material-review.inline',['proposal'=>$proposal,'operation'=>$op,'before'=>$old,'after'=>$new])->render();
            }
        }
        if (!$regions) return null;
        $regions=array_values($regions); usort($regions,fn ($a,$b)=>$b['start']<=>$a['start']); $replacements=[]; $last=strlen($text)+1;
        foreach ($regions as $i=>$r) {
            if ($r['end']>$last) continue; // Overlapping alternatives remain visible in the proposal panel.
            $token='MATERIALREVIEWBLOCK'.strtoupper(bin2hex(random_bytes(8)));
            $replacements[$token]=$r['html']; $text=substr_replace($text,$token,$r['start'],$r['end']-$r['start']); $last=$r['start'];
        }
        $html=parsedown_math($text);
        foreach ($replacements as $token=>$rendered) $html=str_replace(['<p>'.$token.'</p>',$token],$rendered,$html);
        return $html;
    }
}
