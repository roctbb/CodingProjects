<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;

class MarketBid extends Authenticatable
{
    protected $table = 'market_bids';

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function good()
    {
        return $this->belongsTo('App\MarketGood', 'good_id', 'id');
    }
}
