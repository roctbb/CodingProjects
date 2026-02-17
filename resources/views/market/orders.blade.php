@extends('layouts.left-menu')

@section('title')
    Заказы магазина
@endsection

@section('content')
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <h2>Заказы магазина</h2>
        </div>
        <div class="col">
            <a class="btn btn-secondary float-right" href="{{url('/insider/market/')}}">Вернуться в магазин</a>
        </div>
    </div>

    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <h4>Активные заказы ({{$active_orders->count()}})</h4>
        </div>
    </div>

    @if($active_orders->count() > 0)
        <div class="row" style="margin-top: 15px;">
            <div class="col">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Товар</th>
                        <th>Покупатель</th>
                        <th>Цена</th>
                        <th>Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($active_orders as $order)
                        <tr>
                            <td>{{$order->created_at->format('d.m.Y H:i')}}</td>
                            <td>{{$order->good->name}}</td>
                            <td><a href="{{url('/insider/profile/'.$order->user->id)}}">{{$order->user->name}}</a></td>
                            <td>{{$order->good->price}} <img style="height: 20px;" src="{{ url('images/icons/icons8-coins-48.png') }}"/></td>
                            <td>
                                <a href="{{url('/insider/market/ship/'.$order->id)}}" class="btn btn-success btn-sm">Доставлено</a>
                                <a href="{{url('/insider/market/cancel/'.$order->id)}}" class="btn btn-warning btn-sm" onclick="return confirm('Отменить заказ?')">Отменить</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <p class="text-muted">Нет активных заказов</p>
    @endif

    <div class="row" style="margin-top: 30px;">
        <div class="col">
            <h4>Доставленные заказы (последние 50)</h4>
        </div>
    </div>

    @if($shipped_orders->count() > 0)
        <div class="row" style="margin-top: 15px;">
            <div class="col">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>Дата заказа</th>
                        <th>Дата доставки</th>
                        <th>Товар</th>
                        <th>Покупатель</th>
                        <th>Цена</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($shipped_orders as $order)
                        <tr>
                            <td>{{$order->created_at->format('d.m.Y H:i')}}</td>
                            <td>{{$order->updated_at->format('d.m.Y H:i')}}</td>
                            <td>{{$order->good->name}}</td>
                            <td><a href="{{url('/insider/profile/'.$order->user->id)}}">{{$order->user->name}}</a></td>
                            <td>{{$order->good->price}} <img style="height: 20px;" src="{{ url('images/icons/icons8-coins-48.png') }}"/></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <p class="text-muted">Нет доставленных заказов</p>
    @endif

@endsection
