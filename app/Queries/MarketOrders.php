<?php

namespace App\Queries;

use App\MarketDeal;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MarketOrders
{
    public function paginate(bool $shipped, string $search, ?string $fragment = null): LengthAwarePaginator
    {
        $query = MarketDeal::with(['user', 'good'])->where('shipped', $shipped);
        if ($search !== '') {
            $connection = DB::connection();
            $lower = 'LOWER';
            if ($connection->getDriverName() === 'sqlite') {
                $connection->getPdo()->sqliteCreateFunction('market_lower', fn ($value) => mb_strtolower((string) $value), 1);
                $lower = 'market_lower';
            }
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
            $query->where(function ($matches) use ($lower, $pattern, $search) {
                $matches->whereHas('user', fn ($user) => $user->whereRaw("{$lower}(name) LIKE ? ESCAPE '!'", [$pattern]))
                    ->orWhereHas('good', fn ($good) => $good->whereRaw("{$lower}(name) LIKE ? ESCAPE '!'", [$pattern]));
                if (ctype_digit(ltrim($search, '#'))) {
                    $matches->orWhere('market_deals.id', (int) ltrim($search, '#'));
                }
            });
        }

        $query->orderByDesc($shipped ? 'updated_at' : 'created_at')->orderByDesc('id');
        $pageName = $shipped ? 'shipped_page' : 'active_page';
        $orders = $query->paginate(25, ['*'], $pageName);
        // Fulfilling the last order on a page must not strand the teacher on an empty queue.
        if ($orders->currentPage() > $orders->lastPage()) {
            $orders = $query->paginate(25, ['*'], $pageName, $orders->lastPage());
        }
        $orders->withQueryString();
        if ($fragment !== null) $orders->fragment($fragment);
        return $orders;
    }
}
