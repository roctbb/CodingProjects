<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Idea extends Model
{
    public function author()
    {
        return $this->belongsTo('App\User', 'author_id', 'id');
    }

    public function sdl_node()
    {
        return $this->belongsTo('App\CoreNode', 'sdl_node_id', 'id');
    }
}
