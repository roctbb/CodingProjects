<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GameComments extends Model
{
    protected $fillable = ['user_id', 'game_id', 'comment'];
    //
    public function user()
    {
        return $this->belongsTo('\App\User');
    }

    
}
