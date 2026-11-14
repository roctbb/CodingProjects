<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CodeSubmission extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $guarded = [];
    protected $hidden = ['files', 'payload_hash', 'source_metadata', 'answer_text'];
    protected $casts = ['files' => 'array', 'is_public'=>'boolean', 'teacher_viewed'=>'boolean', 'source_metadata' => 'array'];

    public function currentRun()
    {
        return $this->belongsTo(CodeExecutionRun::class, 'current_run_id');
    }
}
