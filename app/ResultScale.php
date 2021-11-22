<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ResultScale extends Model
{
    public function results()
    {
        return $this->hasMany('App\EducationalResult', 'scale_id', 'id');
    }
}
