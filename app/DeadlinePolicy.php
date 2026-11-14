<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class DeadlinePolicy extends Model
{
    protected $guarded = [];
    protected $casts = ['revision' => 'integer', 'legacy' => 'boolean'];
    public function stages() { return $this->hasMany(DeadlineStage::class, 'policy_id')->orderBy('cutoff_at')->orderBy('id'); }
}
