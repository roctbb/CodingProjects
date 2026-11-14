<?php
namespace App\Services\CodeChecking;

/** Parse only the old report grammar. No stored HTML is rendered. */
class HistoricalReport
{
    public const NOTICE='Исторический отчёт содержит данные, сохранённые GeekPaste при проверке. GeekPaste не сохранял ввод, ожидаемый ответ и фактический вывод скрытых тестов в отчёте. Настройки и тесты задачи перенесены отдельно; их текущая версия может отличаться от использованной тогда.';

    public function view(?array $report): ?array
    {
        if ($report && ($report['legacy']??false)) $report['notice']=self::NOTICE;
        return $report;
    }

    public function parse(?string $comment): ?array
    {
        $text=(string)$comment;
        if(!preg_match('/(?:\A|\n\n)Пройдено тестов: (\d+) из (\d+)\.\s*/u',$text,$match,PREG_OFFSET_CAPTURE))return null;
        $passed=(int)$match[1][0];$total=(int)$match[2][0];
        if($total<1 || $total>50 || $passed>$total)return null;
        $body=substr($text,$match[0][1]+strlen($match[0][0]));
        $verdicts=['пройден'=>'passed','неверный ответ'=>'wrong_answer','превышено время'=>'time_limit_exceeded',
            'ошибка выполнения'=>'runtime_error','превышен объём вывода'=>'output_limit_exceeded','ошибка компиляции'=>'compilation_error',
            'ошибка проверки'=>'error','ошибка выполнения или превышение лимита'=>'error'];
        $fields=['Ввод:'=>'input','Ожидаемый вывод:'=>'expected','Вывод программы:'=>'actual','Ошибка:'=>'error'];
        $rows=[];$notes=[];$pending=null;$current=null;
        foreach(preg_split('/(<pre>.*?<\/pre>)/s',$body,-1,PREG_SPLIT_DELIM_CAPTURE) as $part) {
            if(str_starts_with($part,'<pre>')) {
                if($current===null || $pending===null)return null;
                if($rows[$current]['is_public']) {
                    $value=html_entity_decode(substr($part,5,-6),ENT_QUOTES|ENT_HTML5,'UTF-8');
                    $rows[$current][$pending]=['type'=>'inline','text'=>$value,'bytes'=>strlen($value),'truncated'=>false];
                }
                $pending=null;continue;
            }
            foreach(preg_split('/\R/u',$part) as $line) {
                $line=trim($line);if($line==='')continue;
                if($pending!==null)return null;
                if(preg_match('/^Тест (\d+) \((публичный|приватный)\): (.+)\.$/u',$line,$header)) {
                    if(!isset($verdicts[$header[3]]))return null;
                    $current=count($rows);$public=$header[2]==='публичный';
                    $rows[]=['number'=>(int)$header[1],'is_public'=>$public,'unlocked'=>$public,
                        'can_purchase'=>false,'verdict'=>$verdicts[$header[3]]];
                } elseif(isset($fields[$line]) && $current!==null) {
                    $pending=$fields[$line];if(isset($rows[$current][$pending]))return null;
                } else {$notes[]=$line;}
            }
        }
        if($pending!==null || array_column($rows,'number')!==range(1,$total)
            || count(array_filter($rows,fn($r)=>$r['verdict']==='passed'))!==$passed)return null;
        return ['legacy'=>true,'incomplete'=>true,'passed'=>$passed,'total'=>$total,'tests'=>$rows,'notes'=>$notes,
            'notice'=>self::NOTICE];
    }
}
