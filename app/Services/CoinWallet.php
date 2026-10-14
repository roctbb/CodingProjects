<?php

namespace App\Services;

use App\User;
use Illuminate\Support\Facades\DB;

class CoinWallet
{
    // Every purchase must check the balance and save its effects under the same user lock.
    public function transaction(int $userId, callable $purchase): mixed
    {
        return DB::transaction(fn () => $purchase(User::lockForUpdate()->findOrFail($userId)));
    }
}
