<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ThemeUsing extends Model
{
    //
    public $fillable = [
        "theme_id",
        "user_id"
    ];


    public function theme()
    {
        return $this->belongsTo(\App\Theme::class);
    }
}
