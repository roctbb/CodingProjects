<?php

namespace App;

use App\Notifications\AuctionEvent;
use Carbon\Carbon;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

class MarketGood extends Authenticatable
{
    const SALE_TYPE_REGULAR = 'regular';
    const SALE_TYPE_AUCTION = 'auction';

    protected $table = 'market_goods';
    public $timestamps = false;
    protected $casts = [
        'auction_finished_at' => 'datetime',
        'in_stock' => 'boolean',
    ];

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
        return $this->hasMany('App\MarketDeal', 'good_id', 'id');
    }

    public function auctionBids()
    {
        return $this->hasMany('App\MarketBid', 'good_id', 'id');
    }

    public function isAuction()
    {
        return $this->sale_type === self::SALE_TYPE_AUCTION;
    }

    public function isAuctionActive()
    {
        return $this->isAuction() && $this->in_stock && $this->auction_finished_at === null && $this->number > 0;
    }

    public function currentAuctionPrice()
    {
        $bid = $this->auctionBids()->orderBy('amount', 'desc')->orderBy('created_at', 'asc')->orderBy('id', 'asc')->first();

        return $bid ? $bid->amount : $this->price;
    }

    public function userAuctionBid($user)
    {
        return $this->auctionBids()->where('user_id', $user->id)->first();
    }

    public function topAuctionBids($limit = null)
    {
        $query = $this->auctionBids()->with('user')->orderBy('amount', 'desc')->orderBy('created_at', 'asc')->orderBy('id', 'asc');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function auctionWinnerDeals()
    {
        if ($this->relationLoaded('deals')) {
            return $this->deals
                ->where('source', 'auction')
                ->sortBy('id')
                ->sortBy('created_at')
                ->sortByDesc('price')
                ->values();
        }

        return $this->deals()
            ->with('user')
            ->where('source', 'auction')
            ->orderBy('price', 'desc')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function minAuctionBid($user = null)
    {
        $minBid = (int) $this->price;
        $topBids = $this->topAuctionBids((int) $this->number);

        if ($this->number > 0 && $topBids->count() >= $this->number) {
            $minBid = max($minBid, (int) $topBids->last()->amount + 1);
        }

        if ($user) {
            $userBid = $this->userAuctionBid($user);
            if ($userBid) {
                $minBid = max($minBid, (int) $userBid->amount + 1);
            }
        }

        return $minBid;
    }

    public function placeBid($user, $amount)
    {
        return DB::transaction(function () use ($user, $amount) {
            $good = self::where('id', $this->id)->lockForUpdate()->firstOrFail();
            $amount = (int) $amount;

            if (!$good->isAuctionActive()) {
                throw new \RuntimeException('Аукцион уже завершён или снят с продажи.');
            }

            $previousWinnerUserIds = $good->topAuctionBids((int) $good->number)->pluck('user_id')->all();
            $previousBid = MarketBid::where('good_id', $good->id)->where('user_id', $user->id)->lockForUpdate()->first();
            $previousAmount = $previousBid ? (int) $previousBid->amount : 0;
            $minBid = $good->minAuctionBid($user);

            if ($amount < $minBid) {
                throw new \RuntimeException('Минимальная ставка сейчас: ' . $minBid . ' GC.');
            }

            $delta = $amount - $previousAmount;
            if ($delta > $user->balance()) {
                throw new \RuntimeException('Недостаточно GC для этой ставки.');
            }

            if (!$previousBid) {
                $previousBid = new MarketBid();
                $previousBid->user_id = $user->id;
                $previousBid->good_id = $good->id;
            }

            $previousBid->amount = $amount;
            $previousBid->save();

            if ($delta > 0) {
                CoinTransaction::register($user->id, -1 * $delta, 'Auction bid Good #' . $good->id);
            }

            $winnerUserIds = $good->topAuctionBids((int) $good->number)->pluck('user_id')->all();
            $isWinning = in_array($user->id, $winnerUserIds);
            if ($isWinning) {
                CourseActivity::recordAuctionLeadingBidForActiveCourse($user, $good, $amount);
            }
            DB::afterCommit(function () use ($good, $amount, $isWinning, $user) {
                $user->notify(new AuctionEvent(
                    '🔨 Ваша ставка на <strong>"' . e($good->name) . '"</strong> принята: <strong>' . $amount . ' GC</strong>.' .
                    ($isWinning ? ' Сейчас она входит в число победителей.' : ' Сейчас она не входит в число победителей.'),
                    $isWinning ? 'success' : 'info'
                ));

                User::where('role', 'admin')->where('id', '!=', $user->id)->get()->each(function ($admin) use ($good, $amount, $user) {
                    $admin->notify(new AuctionEvent(
                        '🔨 Новая ставка на <strong>"' . e($good->name) . '"</strong>: ' . e($user->name) . ' поставил(а) <strong>' . $amount . ' GC</strong>.',
                        'info'
                    ));
                });
            });

            $displacedUserIds = array_diff($previousWinnerUserIds, $winnerUserIds, [$user->id]);
            if (count($displacedUserIds) > 0) {
                DB::afterCommit(function () use ($displacedUserIds, $good, $amount, $user) {
                    User::whereIn('id', $displacedUserIds)->get()->each(function ($displacedUser) use ($good, $amount, $user) {
                        $displacedUser->notify(new AuctionEvent(
                            '⚠️ Ваша ставка на <strong>"' . e($good->name) . '"</strong> больше не входит в число победителей. ' .
                            e($user->name) . ' поставил(а) <strong>' . $amount . ' GC</strong>.',
                            'warning'
                        ));
                    });
                });
            }

            return $previousBid;
        });
    }

    public function finishAuction()
    {
        return DB::transaction(function () {
            $good = self::where('id', $this->id)->lockForUpdate()->firstOrFail();

            if (!$good->isAuctionActive()) {
                throw new \RuntimeException('Этот аукцион нельзя завершить.');
            }

            $winningBids = MarketBid::where('good_id', $good->id)
                ->with('user')
                ->orderBy('amount', 'desc')
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->limit((int) $good->number)
                ->get();

            $winnerIds = $winningBids->pluck('id')->all();
            $deals = collect();

            foreach ($winningBids as $bid) {
                $deal = MarketDeal::where('good_id', $good->id)->where('user_id', $bid->user_id)->where('source', 'auction')->first();

                if (!$deal) {
                    $deal = new MarketDeal();
                    $deal->user_id = $bid->user_id;
                    $deal->good_id = $good->id;
                    $deal->shipped = false;
                    $deal->source = 'auction';
                }

                $deal->price = $bid->amount;
                $deal->save();
                $deals->push($deal);
                CourseActivity::recordMarketPurchaseForActiveCourse($bid->user, $good, (int) $bid->amount, 'auction', $deal);

                DB::afterCommit(function () use ($bid, $good) {
                    $bid->user->notify(new AuctionEvent(
                        '🏆 Вы выиграли аукцион <strong>"' . e($good->name) . '"</strong> со ставкой <strong>' . $bid->amount . ' GC</strong>. Заказ ожидает доставки.',
                        'success'
                    ));
                });
            }

            MarketBid::where('good_id', $good->id)
                ->whereNotIn('id', $winnerIds ?: [0])
                ->with('user')
                ->get()
                ->each(function ($bid) use ($good) {
                    CoinTransaction::register($bid->user_id, $bid->amount, 'Auction refund Good #' . $good->id);
                    DB::afterCommit(function () use ($bid, $good) {
                        $bid->user->notify(new AuctionEvent(
                            '↩️ Аукцион <strong>"' . e($good->name) . '"</strong> завершён. Ваша ставка <strong>' . $bid->amount . ' GC</strong> не вошла в число победителей и возвращена.',
                            'info'
                        ));
                    });
                });

            $good->number = 0;
            $good->in_stock = false;
            $good->auction_finished_at = now();
            $good->save();
            CourseActivity::recordAuctionFinished($good, $deals->count());

            return $deals;
        });
    }

    public function cancelActiveAuctionBids()
    {
        if (!$this->isAuction() || $this->auction_finished_at !== null) {
            return;
        }

        $this->auctionBids()->get()->each(function ($bid) {
            CoinTransaction::register($bid->user_id, $bid->amount, 'Auction refund Good #' . $this->id);
            $bid->user->notify(new AuctionEvent(
                '↩️ Аукцион <strong>"' . e($this->name) . '"</strong> отменён. Ваша ставка <strong>' . $bid->amount . ' GC</strong> возвращена.',
                'warning'
            ));
            $bid->delete();
        });
    }

    public function buy($user)
    {
        if ($this->isAuction()) {
            return null;
        }

        if ($this->number < 1)
            return null;
        if ($user->balance() < $this->price)
            return null;

        $this->number -= 1;
        $this->save();

        $deal = new MarketDeal();
        $deal->user_id = $user->id;
        $deal->good_id = $this->id;
        $deal->price = $this->price;
        $deal->source = 'purchase';
        $deal->shipped = false;
        $deal->save();

        CoinTransaction::register($user->id, -1 * $this->price, "Good #".$this->id);
        CourseActivity::recordMarketPurchaseForActiveCourse($user, $this, (int) $this->price, 'purchase', $deal);

        return $deal;
    }

}
