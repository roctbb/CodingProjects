<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CodeTestReport extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['report', 'payload_hash'];
    protected $casts = ['report' => 'array', 'started_at' => 'integer'];
}
