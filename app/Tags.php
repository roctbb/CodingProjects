<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Tags extends Model
{
    protected $table = 'tags';

    public function events()
    {
        return $this->belongsToMany('App\Event', 'event_tags');
    }
}