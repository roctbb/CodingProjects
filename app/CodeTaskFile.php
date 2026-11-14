<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CodeTaskFile extends Model
{
    use SoftDeletes;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['artifact'];
    protected $casts = ['artifact' => 'array', 'is_public' => 'boolean', 'read_only' => 'boolean', 'include_in_check' => 'boolean'];
}
