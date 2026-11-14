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
    protected $casts = ['files' => 'array', 'is_public'=>'boolean', 'teacher_viewed'=>'boolean', 'source_metadata' => 'array', 'deadline_rule_snapshot' => 'array', 'class_bonus_snapshot' => 'array'];

    protected static function booted(): void
    {
        static::creating(function (self $submission) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('code_submissions', 'class_bonus_snapshot')) $submission->class_bonus_snapshot = app(\App\Services\ClassroomBonus::class)->at(
                Task::findOrFail($submission->task_id), $submission->course_id, $submission->user_id, $submission->created_at ?? now());
            if (!\Illuminate\Support\Facades\Schema::hasColumn('code_submissions', 'deadline_rule_snapshot')) return;
            $rule = Task::findOrFail($submission->task_id)->getDeadline($submission->course_id);
            $submission->deadline_rule_snapshot = $rule
                ? ($rule instanceof \App\Support\DeadlineRule ? $rule : \App\Support\DeadlineRule::fromLegacy($rule))->snapshot() : ['none' => true];
        });
    }

    public function currentRun()
    {
        return $this->belongsTo(CodeExecutionRun::class, 'current_run_id');
    }
}
