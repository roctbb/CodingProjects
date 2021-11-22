<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EventLikes extends Model
{
    //
    protected $table = 'event_likes';

    protected $fillable = ['amount', 'event_id', 'user_id'];

}
