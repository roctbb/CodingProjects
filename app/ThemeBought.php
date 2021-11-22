<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ThemeBought extends Model
{
    protected $fillable = ["user_id", "theme_id"];

    function theme()
    {
        return $this->belongsTo(\App\Theme::class, 'theme_id');
    }   
}
