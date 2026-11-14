<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class DeadlineStage extends Model
{
    protected $guarded = [];
    public function getRetainedPercentAttribute($value) { return (float) $value == (int) $value ? (int) $value : (float) $value; }
    public function policy() { return $this->belongsTo(DeadlinePolicy::class, 'policy_id'); }
}
