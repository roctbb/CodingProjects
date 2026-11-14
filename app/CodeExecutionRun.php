<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CodeExecutionRun extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $guarded = [];
    protected $hidden = ['snapshot', 'result_hash'];
    protected $casts = ['snapshot' => 'array', 'legacy_report'=>'array', 'progress' => 'array', 'finished_at' => 'datetime', 'dispatch_lease_until' => 'datetime'];

    public function submission()
    {
        return $this->belongsTo(CodeSubmission::class);
    }
}
