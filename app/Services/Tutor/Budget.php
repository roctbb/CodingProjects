<?php

namespace App\Services\Tutor;

use App\Services\Ai\AiSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class Budget
{
    // Call inside the transaction which inserts the reservation, award or recheck.
    public static function lockMonth(string $day): string
    {
        $month = Carbon::parse($day)->startOfMonth()->toDateString();
        $period = DB::table('tutor_budget_periods');
        $period->insertOrIgnore(['month'=>$month]);
        $period->where('month',$month)->lockForUpdate()->first();
        return $month;
    }

    public static function canAwardCoin(int $student): bool
    {
        $day = now()->startOfDay();
        self::lockMonth($day->toDateString());
        $awards = DB::table('coin_transactions')->where('comment','like','Tutor question #%')
            ->where('created_at','>=',$day)->where('created_at','<',$day->copy()->addDay());
        $limits = app(AiSettings::class)->all()['tutor_limits'];
        return (clone $awards)->where('user_id',$student)->count() < $limits['daily_coins']
            && $awards->count() < $limits['platform_daily_coins'];
    }

    public static function canRecheck(int $student): bool
    {
        $day = now()->startOfDay();
        self::lockMonth($day->toDateString());
        $runs = DB::table('code_execution_runs as r')->join('code_submissions as s','s.id','=','r.submission_id')
            ->where('r.snapshot->ai_review->origin','tutor')
            ->where('r.created_at','>=',$day)->where('r.created_at','<',$day->copy()->addDay());
        $limits = app(AiSettings::class)->all()['tutor_limits'];
        return (clone $runs)->where('s.user_id',$student)->count() < $limits['daily_rechecks']
            && $runs->count() < $limits['platform_daily_rechecks'];
    }
}
