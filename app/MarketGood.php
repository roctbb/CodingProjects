<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class MarketGood extends Authenticatable
{

    protected $table = 'market_goods';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */


    public function owners()
    {
        return $this->belongsToMany('App\User', 'market_deals', 'good_id', 'user_id');
    }

    public function deals()
    {
        return $this->belongsTo('App\MarketGood', 'good_id', 'id');
    }

    public function buy($user)
    {
        if ($this->number < 1)
            return null;
        if ($user->balance() < $this->price)
            return null;

        $this->number -= 1;
        $this->save();

        $deal = new MarketDeal();
        $deal->user_id = $user->id;
        $deal->good_id = $this->id;
        $deal->shipped = false;
        $deal->save();

        CoinTransaction::register($user->id, -1 * $this->price, "Good #".$this->id);

        return $deal;
    }

}
