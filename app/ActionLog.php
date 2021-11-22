<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ActionLog extends Authenticatable
{
    use Notifiable;

    protected $table = 'action_log';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */


    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public static function record($user_id, $type, $id)
    {
        $transaction = new ActionLog();
        $transaction->user_id = $user_id;
        $transaction->type = $type;
        $transaction->object_id = $id;
        $transaction->save();
    }

}
