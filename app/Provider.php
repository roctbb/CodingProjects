<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Provider extends Model
{
    protected $table = "providers";


    public function courses()
    {
        return $this->hasMany('App\Course', 'provider_id', 'id')->orderBy('id');
    }

}
