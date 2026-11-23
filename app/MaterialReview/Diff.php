<?php
namespace App\MaterialReview;

class Diff
{
    /** Preserve whole Markdown blocks around the changed region; render both sides with the platform sanitizer. */
    public static function html(?string $before,?string $after): string
    {
        $before=$before??''; $after=$after??'';
        if ($before===$after) return parsedown_math($after);
        $a=preg_split('/\n\s*\n/',$before); $b=preg_split('/\n\s*\n/',$after); $start=0;
        while (isset($a[$start],$b[$start])&&$a[$start]===$b[$start]) $start++;
        $end=0;
        while (count($a)-$end>$start&&count($b)-$end>$start&&$a[count($a)-1-$end]===$b[count($b)-1-$end]) $end++;
        $render=fn ($blocks)=>parsedown_math(implode("\n\n",$blocks));
        $old=array_slice($a,$start,count($a)-$start-$end); $new=array_slice($b,$start,count($b)-$start-$end);
        return $render(array_slice($a,0,$start)).($old?'<del class="mr-removed">'.$render($old).'</del>':'').($new?'<ins class="mr-added">'.$render($new).'</ins>':'').($end?$render(array_slice($b,-$end)):'');
    }
}
