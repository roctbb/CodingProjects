<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FeedbackRecord extends Model
{
    protected $table = 'feedback';
    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

}
