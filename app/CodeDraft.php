<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class CodeDraft extends Model
{
    protected $guarded = [];
    protected $hidden = ['value'];
    protected $casts = ['value' => 'array'];
}
