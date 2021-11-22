<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Mark
{
    public static function getMark($points, $max_points)
    {
        if ($max_points == 0) return 'A';
        $percent = ceil($points * 100 / $max_points);

        if ($percent==100)
            return 'S';
        if ($percent>=99)
            return 'A+';
        if ($percent>=90)
            return 'A';
        if ($percent>=80)
            return 'A-';
        if ($percent>=75)
            return 'B+';
        if ($percent>=70)
            return 'B';
        if ($percent>=60)
            return 'B-';
        if ($percent>=55)
            return 'C+';
        if ($percent>=50)
            return 'C';
        if ($percent>=40)
            return 'C-';
        return 'D';
    }



}
