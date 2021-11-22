<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GameReward extends Model
{
    //
    public static function register($user_id, $game_id, $amount, $comment)
    {
        $reward = new \App\GameReward();
        $reward->user_id = $user_id;
        $reward->game_id = $game_id;
        $reward->amount = $amount;
        $reward->comment = $comment;
        $reward->save();

        \App\CoinTransaction::register($user_id, $amount, "Награда за игру: ".$comment);
        if (!\Auth::user()->is_teacher)
        {
            \App\CoinTransaction::register(\Auth::id(), -$amount, "Наградил игру");
        }
        return $reward;
    }
}
