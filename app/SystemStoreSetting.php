<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SystemStoreSetting extends Model
{
    public $incrementing = false;
    protected $guarded = [];
    protected $casts = ['settings' => 'array'];
}
