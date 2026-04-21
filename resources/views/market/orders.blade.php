@extends('layouts.left-menu')

@section('title')
    Заказы магазина
@endsection

@section('content')
    <div class="cp-market-orders-page">
    <div class="row cp-row-gap-top align-items-center">
        <div class="col-md-8">
            <h2 class="cp-heading-lite mb-0">Заказы магазина</h2>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a class="btn btn-outline-secondary" href="{{url('/insider/market/')}}">Вернуться в магазин</a>
        </div>
    </div>

    <div class="row cp-row-gap-top">
        <div class="col">
            <h4 class="cp-market-orders-title">Активные заказы <span class="badge text-bg-light">{{$active_orders->count()}}</span></h4>
        </div>
    </div>

    @if($active_orders->count() > 0)
        <div class="row cp-row-gap-top">
            <div class="col">
                <div class="card cp-market-orders-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
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
                                        <td>{{$order->good->price}} <img class="cp-h-20" src="{{ url('images/icons/icons8-coins-48.png') }}" alt="coins"/></td>
                                        <td class="cp-market-orders-actions">
                                            <a href="{{url('/insider/market/ship/'.$order->id)}}" class="btn btn-primary btn-sm">Доставлено</a>
                                            <a href="{{url('/insider/market/cancel/'.$order->id)}}" class="btn btn-warning btn-sm" onclick="return confirm('Отменить заказ?')">Отменить</a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-info mt-3 mb-0">Нет активных заказов.</div>
    @endif

    <div class="row cp-row-gap-top-lg">
        <div class="col">
            <h4 class="cp-market-orders-title">Доставленные заказы <span class="badge text-bg-light">последние 50</span></h4>
        </div>
    </div>

    @if($shipped_orders->count() > 0)
        <div class="row cp-row-gap-top">
            <div class="col">
                <div class="card cp-market-orders-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
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
                                        <td>{{$order->good->price}} <img class="cp-h-20" src="{{ url('images/icons/icons8-coins-48.png') }}" alt="coins"/></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-info mt-3">Нет доставленных заказов.</div>
    @endif
    </div>

@endsection
