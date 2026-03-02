<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Rank extends Model
{
    protected $table = "ranks";

    protected $fillable = ['name', 'from', 'to', 'icon'];

    public static function getRanksListHTML($current_rank)
    {
        $ranks = Rank::orderBy('from', 'DESC')->get();

        $result = '<ul>';

        foreach ($ranks as $rank)
            if ($current_rank == $rank)
                $result.='<li><strong>'.$rank->name.' ('.$rank->from.')</strong></li>';
            else
                $result.='<li>'.$rank->name.' ('.$rank->from.')</li>';
        $result.='</ul>';
        return $result;
    }



}
