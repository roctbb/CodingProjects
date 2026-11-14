<?php

namespace App\Http\Controllers;

use App\CoinTransaction;
use App\CourseActivity;
use App\MarketDeal;
use App\MarketGood;
use App\Notifications\NewOrder;
use App\Program;
use App\Queries\MarketOrders;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Auth;

class MarketController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('admin')->only(['createView', 'editView', 'edit', 'create', 'archive', 'restore', 'finishAuction', 'creditView', 'credit']);
        $this->middleware('teacher')->only(['ship', 'cancel', 'orders']);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, MarketOrders $orders)
    {
        $user = User::findOrFail(Auth::User()->id);
        $canManageMarket = $user->role == 'teacher' || $user->role == 'admin';
        $goodsQuery = MarketGood::where('in_stock', true)->with(['auctionBids.user']);

        if (!$canManageMarket) {
            $goodsQuery->where('number', '>', 0);
        }

        $activeGoods = $goodsQuery->orderByRaw('CASE WHEN number > 0 THEN 0 ELSE 1 END')->orderBy('id', 'desc')->get();
        $goods = $activeGoods->where('sale_type', '!=', MarketGood::SALE_TYPE_AUCTION)->values();
        $auctions = $activeGoods->where('sale_type', MarketGood::SALE_TYPE_AUCTION)->values();
        $digitalGoods = $user->digitalStoreItems()->sortBy(fn ($item) => ((($item['type'] ?? 'learning_avatar_item') === 'learning_avatar_item' && $item['owned']) || $item['cost'] > $user->balance()) ? 1 : 0)->values();
        $archive = $canManageMarket ? MarketGood::where('in_stock', false)->with(['auctionBids.user', 'deals.user'])->orderBy('id', 'desc')->get() : collect();
        $ordersSearch = $canManageMarket ? $this->ordersSearch($request) : '';
        $active_orders = $canManageMarket ? $orders->paginate(false, $ordersSearch, 'market-orders') : collect();
        $shipped_orders = $canManageMarket ? $orders->paginate(true, $ordersSearch, 'market-orders') : collect();

        $myPurchases = $user->orders()->with('good')->latest()->orderByDesc('id')
            ->paginate(25, ['*'], 'purchases_page')->withQueryString()->fragment('market-purchases');
        $coinTransactions = $user->transactions()->latest()->orderByDesc('id')
            ->paginate(25, ['*'], 'history_page')->withQueryString()->fragment('market-history');
        $ownedDigitalGoods = $digitalGoods->filter(fn ($item) => ($item['owned'] ?? false) || ($item['active'] ?? false));

        return view('market.index', compact('goods', 'auctions', 'digitalGoods', 'user', 'archive', 'active_orders', 'shipped_orders', 'canManageMarket', 'myPurchases', 'coinTransactions', 'ownedDigitalGoods', 'ordersSearch'));
    }

    public function creditView()
    {
        $recipients = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('market.credit', compact('recipients'));
    }

    public function credit(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|integer|min:1|max:10000',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|integer|distinct|exists:users,id',
            'comment' => 'required|string|max:255',
        ]);

        $amount = (int) $data['amount'];
        $comment = clean($data['comment']);

        $transactions = DB::transaction(function () use ($data, $amount, $comment) {
            $transactions = [];

            foreach ($data['recipients'] as $recipientId) {
                $transaction = new CoinTransaction();
                $transaction->user_id = $recipientId;
                $transaction->price = $amount;
                $transaction->comment = $comment;
                $transaction->save();
                $transactions[] = $transaction;
            }

            return $transactions;
        });

        // Notify only after the entire batch is saved. A delivery failure must
        // not turn a completed credit into an error that invites resubmission.
        foreach ($transactions as $transaction) {
            rescue(fn () => $transaction->withNotification(null, 'success', 'fas fa-coins')->notifyUser());
        }

        $this->make_success_alert('Начисление выполнено', 'Начислено по ' . $amount . ' GC. Получателей: ' . count($transactions) . '.');

        return redirect('/insider/market');
    }

    public function orders(Request $request, MarketOrders $orders)
    {
        $user = User::findOrFail(Auth::User()->id);
        $ordersSearch = $this->ordersSearch($request);
        $active_orders = $orders->paginate(false, $ordersSearch);
        $shipped_orders = $orders->paginate(true, $ordersSearch);
        return view('market.orders', compact('user', 'active_orders', 'shipped_orders', 'ordersSearch'));
    }

    private function ordersSearch(Request $request): string
    {
        $data = $request->validate(['orders_q' => 'nullable|string|max:200']);
        return trim($data['orders_q'] ?? '');
    }

    private function ordersReturnUrl(Request $request): string
    {
        $value = $request->input('return_url');
        if (!is_string($value) || preg_match('/[\\\\\x00-\x20]/', $value)) return url('/insider/market#market-orders');
        $parts = parse_url($value);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['host']) && ($parts['host'] !== $request->getHost() || ($parts['scheme'] ?? '') !== $request->getScheme() || ($parts['port'] ?? ($request->isSecure() ? 443 : 80)) !== $request->getPort()))
            || (!isset($parts['host']) && !str_starts_with($value, '/'))
            || !in_array($parts['path'] ?? '', ['/insider/market', '/insider/market/', '/insider/market/orders'], true)) {
            return url('/insider/market#market-orders');
        }
        return $value;
    }

    public function createView()
    {
        $programs = Program::all();
        return view('market.create', compact('programs'));
    }

    public function editView($id)
    {
        $good = MarketGood::findOrFail($id);
        return view('market.edit', compact('good'));
    }

    public function edit($id, Request $request)
    {
        return DB::transaction(function () use ($id, $request) {
            MarketGood::whereKey($id)->lockForUpdate()->firstOrFail();
            return $this->editGood($id, $request);
        }, 5);
    }

    private function editGood($id, Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'required|string|max:255',
            'number' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'sale_type' => 'nullable|in:regular,auction',
        ]);

        $good = MarketGood::findOrFail($id);
        $saleType = $request->input('sale_type', MarketGood::SALE_TYPE_REGULAR);

        if ($good->isAuction() && $good->auctionBids()->count() > 0) {
            $saleSettingsChanged = $saleType !== $good->sale_type
                || (int) $request->price !== (int) $good->price
                || (int) $request->number !== (int) $good->number;

            if ($saleSettingsChanged) {
                $this->make_error_alert('Ошибка!', 'У аукциона уже есть ставки. Тип продажи, стартовую стоимость и количество мест менять нельзя.', $destination = 'head');
                return redirect()->back()->withInput();
            }
        }

        $good->name = $request->name;
        $good->description = clean($request->description);
        $good->image = $request->image;
        $good->number = $request->number;
        $good->price = $request->price;
        $good->sale_type = $saleType;

        if ($request->in_stock == 'on') {
            $good->in_stock = true;
        } else {
            $good->cancelActiveAuctionBids();
            $good->in_stock = false;
        }


        $good->save();
        return redirect('/insider/market/');
    }

    public function create(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'required|string|max:255',
            'number' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'sale_type' => 'nullable|in:regular,auction',
        ]);

        $good = new MarketGood();
        $good->name = $request->name;
        $good->description = clean($request->description);
        $good->image = $request->image;
        $good->number = $request->number;
        $good->price = $request->price;
        $good->sale_type = $request->input('sale_type', MarketGood::SALE_TYPE_REGULAR);

        if ($request->in_stock == 'on') {
            $good->in_stock = true;
        } else {
            $good->in_stock = false;
        }


        $good->save();
        return redirect('/insider/market/');
    }

    public function archive($id)
    {
        $good = DB::transaction(function () use ($id) {
            $good = MarketGood::whereKey($id)->lockForUpdate()->firstOrFail();
            $good->cancelActiveAuctionBids();
            $good->in_stock = false;
            $good->save();
            return $good;
        }, 5);

        $this->make_success_alert("Готово!", 'Товар "' . $good->name . '" перенесён в архив.', $destination = 'head');

        return redirect('/insider/market/');
    }

    public function restore($id)
    {
        $good = MarketGood::findOrFail($id);
        $good->in_stock = true;
        $good->save();

        $this->make_success_alert("Готово!", 'Товар "' . $good->name . '" возвращён в магазин.', $destination = 'head');

        return redirect('/insider/market/');
    }

    public function buy($id, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);
        $good = MarketGood::findOrFail($id);
        $request->validate(['expected_price' => 'required|integer|min:0', 'purchase_key' => 'required|uuid']);
        try {
            $deal = $good->buy($user, $request->integer('expected_price'), strtolower($request->purchase_key));
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() !== 'market_price_changed') throw $exception;
            $this->make_error_alert('Цена изменилась', 'Обновите цену товара перед покупкой. Монеты не списаны.', $destination = 'head');
            return redirect('/insider/market/');
        }

        if ($deal) {
            if ($deal->wasRecentlyCreated) rescue(fn () => User::find(1)?->notify(new NewOrder($deal)));
            $this->make_success_alert($deal->cancelled_at ? 'Заказ отменён' : 'Заказ оформлен',
                $deal->cancelled_at ? 'Монеты за этот заказ уже возвращены.' : 'Заказ №'.$deal->id.' доступен в разделе «Мои покупки».');
        } else {
            $this->make_error_alert("Ошибка!", 'Покупка "' . $good->name . '" не прошла.', $destination = 'head');
        }

        return redirect('/insider/market/');
    }

    public function buyDigital($itemKey, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);

        $request->validate(['expected_cost' => 'sometimes|integer|min:1']);
        try {
            $item = app(\App\Services\CoinWallet::class)->transaction($user->id, function (User $lockedUser) use ($itemKey, $request) {
                $catalog = User::learningAvatarItemCatalog();
                $itemCost = (int) ($catalog[$itemKey]['cost'] ?? 0);
                if ($request->has('expected_cost') && $request->integer('expected_cost') !== $itemCost) throw new \RuntimeException('market_price_changed');

                if ($lockedUser->balance() < $itemCost) {
                    throw new \RuntimeException('insufficient_balance');
                }

                $item = $lockedUser->learningAvatarBuyItem($itemKey);
                CoinTransaction::register(
                    $lockedUser->id,
                    -1 * $item['cost'],
                    'Learning avatar item ' . $item['key'] . ' User #' . $lockedUser->id,
                    'Куплен цифровой предмет «' . $item['name'] . '»: -' . $item['cost'] . ' GC',
                    'success',
                    'fas fa-user-astronaut'
                );

                return $item;
            });
        } catch (\InvalidArgumentException $exception) {
            $this->make_error_alert('Товар не найден', 'Такого цифрового товара нет в магазине.', $destination = 'head');
            return redirect('/insider/market/#market-digital');
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'market_price_changed') {
                $this->make_error_alert('Цена изменилась', 'Обновите страницу перед покупкой. Монеты не списаны.', $destination = 'head');
            } elseif ($exception->getMessage() === 'insufficient_balance') {
                $this->make_error_alert('Не хватает GC', 'Для покупки цифрового товара недостаточно монет.', $destination = 'head');
            } elseif ($exception->getMessage() === 'digital_item_already_owned') {
                $this->make_info_alert('Уже куплено', 'Этот предмет уже доступен в настройках комнаты.', $destination = 'head');
            } else {
                $this->make_error_alert('Покупка не прошла', 'Этот цифровой товар сейчас нельзя купить.', $destination = 'head');
            }

            return redirect('/insider/market/#market-digital');
        }

        $this->make_success_alert('Цифровой товар куплен', '«' . $item['name'] . '» теперь доступен в настройках комнаты профиля.', $destination = 'head');
        CourseActivity::recordDigitalPurchaseForActiveCourse($user, $item);

        return redirect('/insider/profile/' . $user->id . '#learning-avatar');
    }

    public function bid($id, Request $request)
    {
        $this->validate($request, [
            'amount' => 'required|numeric|min:0',
        ]);

        $user = User::findOrFail(Auth::User()->id);
        $good = MarketGood::findOrFail($id);

        if ($user->role == 'teacher' || $user->role == 'admin') {
            $this->make_error_alert('Ставка не принята', 'Ставки доступны ученикам. Завершить аукцион можно из меню товара.', $destination = 'head');
            return redirect('/insider/market/');
        }

        try {
            $bid = $good->placeBid($user, $request->amount);
            $this->make_success_alert('Ставка принята!', 'Ваша ставка на "' . $good->name . '": ' . $bid->amount . ' GC.', $destination = 'head');
        } catch (\RuntimeException $exception) {
            $this->make_error_alert('Ставка не принята', $exception->getMessage(), $destination = 'head');
        }

        return redirect('/insider/market/');
    }

    public function finishAuction($id)
    {
        $good = MarketGood::findOrFail($id);

        try {
            $deals = $good->finishAuction();

            $receiver = User::findOrFail(1);
            foreach ($deals as $deal) {
                rescue(fn () => $receiver->notify(new NewOrder($deal)));
            }

            $this->make_success_alert('Аукцион завершён!', 'Создано заказов: ' . $deals->count() . '.', $destination = 'head');
        } catch (\RuntimeException $exception) {
            $this->make_error_alert('Ошибка!', $exception->getMessage(), $destination = 'head');
        }

        return redirect('/insider/market/');
    }

    public function ship($id, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);
        DB::transaction(function () use ($id, $user) {
            $order = MarketDeal::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($order->cancelled_at, 409, 'Заказ уже отменён.');
            if ($order->shipped) return;
            $order->shipped = true; $order->shipped_by = $user->id; $order->save();
            CourseActivity::recordMarketOrderShipped($order, $user);
        });
        $this->make_success_alert('Успех!', 'Выдача заказа сохранена.');

        return redirect()->to($this->ordersReturnUrl($request));
    }

    public function cancel($id, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);
        $order = MarketDeal::findOrFail($id);

        app(\App\Services\CoinWallet::class)->transaction($order->user_id, function () use ($order, $user) {
            $lockedOrder = MarketDeal::whereKey($order->id)->lockForUpdate()->first();
            if (!$lockedOrder || $lockedOrder->cancelled_at) return;
            abort_if($lockedOrder->shipped, 409, 'Выданный заказ нельзя отменить.');
            $price = $lockedOrder->displayPrice();
            abort_if($price === null, 409, 'Цена заказа неизвестна.');
            $refund = CoinTransaction::register($lockedOrder->user_id, $price, 'Market order cancel Deal #'.$lockedOrder->id);
            if (($lockedOrder->source ?? 'purchase') !== 'auction') {
                MarketGood::whereKey($lockedOrder->good_id)->increment('number');
            }
            $lockedOrder->cancelled_at = now(); $lockedOrder->cancelled_by = $user->id;
            $lockedOrder->refund_transaction_id = $refund->id; $lockedOrder->save();
        });

        return redirect()->to($this->ordersReturnUrl($request));
    }


}
