<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GameVotes extends Model
{
    protected $fillable = ['user_id', 'game_id', 'amount'];
    protected $table = 'game_votes';
}
