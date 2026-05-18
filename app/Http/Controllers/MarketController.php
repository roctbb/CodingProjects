<?php

namespace App\Http\Controllers;

use App\CoinTransaction;
use App\CourseActivity;
use App\MarketDeal;
use App\MarketGood;
use App\Notifications\NewOrder;
use App\Program;
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
        $this->middleware('admin')->only(['createView', 'editView', 'edit', 'create', 'archive', 'restore', 'finishAuction']);
        $this->middleware('teacher')->only(['ship', 'cancel', 'orders']);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = User::findOrFail(Auth::User()->id);
        $canManageMarket = $user->role == 'teacher' || $user->role == 'admin';
        $goodsQuery = MarketGood::where('in_stock', true)->with(['auctionBids.user']);

        if (!$canManageMarket) {
            $goodsQuery->where('number', '>', 0);
        }

        $activeGoods = $goodsQuery->orderBy('id', 'desc')->get();
        $goods = $activeGoods->where('sale_type', '!=', MarketGood::SALE_TYPE_AUCTION)->values();
        $auctions = $activeGoods->where('sale_type', MarketGood::SALE_TYPE_AUCTION)->values();
        $digitalGoods = $user->digitalStoreItems();
        $archive = $canManageMarket ? MarketGood::where('in_stock', false)->with(['auctionBids.user', 'deals.user'])->orderBy('id', 'desc')->get() : collect();
        $active_orders = $canManageMarket ? MarketDeal::where('shipped', false)->with(['user', 'good'])->orderBy('created_at', 'desc')->get() : collect();
        $shipped_orders = $canManageMarket
            ? MarketDeal::where('shipped', true)
                ->with(['user', 'good'])
                ->orderBy('updated_at', 'desc')
                ->paginate(25, ['*'], 'shipped_page')
                ->withQueryString()
                ->fragment('market-orders')
            : collect();

        return view('market.index', compact('goods', 'auctions', 'digitalGoods', 'user', 'archive', 'active_orders', 'shipped_orders', 'canManageMarket'));
    }

    public function orders()
    {
        $user = User::findOrFail(Auth::User()->id);
        $active_orders = MarketDeal::where('shipped', false)->with(['user', 'good'])->orderBy('created_at', 'desc')->get();
        $shipped_orders = MarketDeal::where('shipped', true)
            ->with(['user', 'good'])
            ->orderBy('updated_at', 'desc')
            ->paginate(25, ['*'], 'shipped_page')
            ->withQueryString();
        return view('market.orders', compact('user', 'active_orders', 'shipped_orders'));
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
        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required|string',
            'image' => 'required|string',
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
            'name' => 'required|string',
            'description' => 'required|string',
            'image' => 'required|string',
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
        $good = MarketGood::findOrFail($id);
        $good->cancelActiveAuctionBids();
        $good->in_stock = false;
        $good->save();

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
        $deal = $good->buy($user);

        if ($deal) {
            $receiver = User::findOrFail(1);
            $receiver->notify(new NewOrder($deal));
            $this->make_success_alert("Успех!", 'Покупка "' . $good->name . '" прошла успешно. Ожидайте доставки!', $destination = 'head');
        } else {
            $this->make_error_alert("Ошибка!", 'Покупка "' . $good->name . '" не прошла.', $destination = 'head');
        }

        return redirect('/insider/market/');
    }

    public function buyDigital($itemKey)
    {
        $user = User::findOrFail(Auth::User()->id);

        try {
            $item = DB::transaction(function () use ($user, $itemKey) {
                $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $catalog = User::learningAvatarItemCatalog();
                $itemCost = (int) ($catalog[$itemKey]['cost'] ?? 0);

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
            if ($exception->getMessage() === 'insufficient_balance') {
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
                $receiver->notify(new NewOrder($deal));
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
        $order = MarketDeal::findOrFail($id);

        $this->make_success_alert("Успех!", 'Доставка товара "' . $order->good->name . '" проведена.', $destination = 'head');


        $order->shipped = true;
        $order->shipped_by = Auth::User()->id;
        $order->save();
        CourseActivity::recordMarketOrderShipped($order, $user);

        $returnUrl = $request->input('return_url');
        $returnPath = $returnUrl ? parse_url($returnUrl, PHP_URL_PATH) : null;
        if ($returnPath && str_starts_with($returnPath, '/insider/market')) {
            return redirect()->to($returnUrl);
        }

        return redirect()->back();
    }

    public function cancel($id, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);
        $order = MarketDeal::findOrFail($id);

        if ($order->source == 'auction') {
            CoinTransaction::register($order->user_id, $order->displayPrice(), 'Auction order cancel Good #' . $order->good_id);
        } else {
            $transaction = CoinTransaction::where('user_id', $order->user_id)->where('comment', 'like', '%Good #' . $order->good_id . '%')->orderBy('id', 'desc')->first();
            if ($transaction) {
                $transaction->delete();
            }
        }
        $order->delete();

        return redirect('/insider/market/');
    }


}
