<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CoreEdge extends Model
{
    protected $table = 'core_edges';
    public $timestamps = false;

    public function from()
    {
        return $this->hasOne('App\CoreNode', 'id', 'from_id');
    }


    public function to()
    {
        return $this->hasOne('App\CoreNode', 'id', 'to_id');
    }

}
