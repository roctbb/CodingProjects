<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CourseLabel
{
    public static function get($course)
    {
        if (str_contains(mb_strtolower($course->provider), 'goto'))
        {
            return 'danger';
        }
        else if (str_contains(mb_strtolower($course->provider), 'geekon'))
        {
            return 'success';
        }
        else if (str_contains(mb_strtolower($course->provider), 'геккон'))
        {
            return 'info';
        }
        else if (str_contains(mb_strtolower($course->provider), 'polymus'))
        {
            return 'primary';
        }
        else if (str_contains(mb_strtolower($course->provider), 'алгоритмика'))
        {
            return 'warning';
        }
        return 'secondary';
    }



}
