<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TelegramSetting extends Model
{
    public $incrementing = false;

    protected $casts = ['settings' => 'encrypted:array'];

    protected $hidden = ['settings'];
}
