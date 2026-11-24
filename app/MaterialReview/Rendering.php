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
}
