<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CodeTaskChecker extends Model
{
    protected $primaryKey = 'task_id';
    protected $guarded = [];
    protected $hidden = ['config'];
    protected $casts = ['config' => 'array', 'allows_test_reveals' => 'boolean'];
}
