<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectAward extends Model
{
    //
    public $fillable = ["user_id", "project_id", "amount", "comment"];
    public static function register($user_id, $project_id, $amount, $comment)
    {
        $reward = new \App\ProjectAward();
        $reward->user_id = $user_id;
        $reward->project_id = $project_id;
        $reward->amount = $amount;
        $reward->comment = $comment;
        $reward->save();

        \App\CoinTransaction::register($user_id, $amount, "Награда за проект: ".$comment);
        if (!\Auth::user()->is_teacher)
        {
            \App\CoinTransaction::register(\Auth::id(), -$amount, "Наградил проект");
        }
        return $reward;
    }
}
