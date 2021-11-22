<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class MarketDeal extends Authenticatable
{

    protected $table = 'market_deals';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function good()
    {
        return $this->belongsTo('App\MarketGood', 'good_id', 'id');
    }


}
