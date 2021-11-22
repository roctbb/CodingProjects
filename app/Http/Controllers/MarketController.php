<?php

namespace App\Http\Controllers;

use App\CompletedCourse;
use App\Course;
use App\MarketDeal;
use App\MarketGood;
use App\Notifications\NewOrder;
use App\Program;
use App\ProgramStep;
use App\Http\Controllers\Controller;
use App\Provider;
use App\User;
use App\Lesson;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
        $this->middleware('admin')->only(['createView', 'editView', 'edit', 'create', 'ship']);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = User::findOrFail(Auth::User()->id);
        $goods = MarketGood::where('in_stock', true)->orderBy('id', 'desc')->get();
        $active_orders = MarketDeal::where('shipped', false)->get();
        $archive = MarketGood::where('in_stock', false)->get();
        return view('market.index', compact('goods', 'user', 'archive', 'active_orders'));
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
        ]);

        $good = MarketGood::findOrFail($id);
        $good->name = $request->name;
        $good->description = clean($request->description);
        $good->image = $request->image;
        $good->number = $request->number;
        $good->price = $request->price;

        if ($request->in_stock == 'on') {
            $good->in_stock = true;
        } else {
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
        ]);

        $good = new MarketGood();
        $good->name = $request->name;
        $good->description = clean($request->description);
        $good->image = $request->image;
        $good->number = $request->number;
        $good->price = $request->price;

        if ($request->in_stock == 'on') {
            $good->in_stock = true;
        } else {
            $good->in_stock = false;
        }


        $good->save();
        return redirect('/insider/market/');
    }

    public function buy($id, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);
        $good = MarketGood::findOrFail($id);
        $deal = $good->buy($user);

        if ($deal)
        {
            $receiver = User::findOrFail(1);
            $receiver->notify(new NewOrder($deal));
            $this->make_success_alert("Успех!", 'Покупка "'.$good->name.'" прошла успешно. Ожидайте доставки!', $destination = 'head');
        }
        else {
            $this->make_error_alert("Ошибка!", 'Покупка "'.$good->name.'" не прошла.', $destination = 'head');
        }

        return redirect('/insider/market/');
    }

    public function ship($id, Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);
        $order = MarketDeal::findOrFail($id);

        $this->make_success_alert("Успех!", 'Доставка товара "'.$order->good->name.'" проведена.', $destination = 'head');


        $order->shipped = true;
        $order->shipped_by = Auth::User()->id;
        $order->save();

        return redirect('/insider/market/');
    }


}
