<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OidcLinkRequest extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
