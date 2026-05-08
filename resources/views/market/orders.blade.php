@extends('layouts.left-menu')

@section('title')
    Заказы магазина
@endsection

@section('content')
    <div class="management-page">
        <div class="management-header gc-card mb-3">
            <div>
                <a class="assessment-back-link" href="{{ url('/insider/market') }}"><i class="icon ion-chevron-left"></i> В магазин</a>
                <h2 class="mb-1">Заказы магазина</h2>
                <p class="mb-0 text-muted">Доставка и история покупок за GC.</p>
            </div>
            <div class="assessment-summary">
                <div><strong>{{ $active_orders->count() }}</strong><span>активных</span></div>
                <div><strong>{{ $shipped_orders->count() }}</strong><span>доставлено</span></div>
            </div>
        </div>

    <div class="gc-card management-table-card mb-3">
        <div class="card-body">
            <h5 class="mb-1">Активные заказы</h5>
            <p class="text-muted">Заказы, ожидающие доставки</p>
        </div>
        <div class="table-responsive">
            @if($active_orders->count() > 0)
                <table class="table table-hover mb-0 management-table market-orders-table">
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
                            <td data-label="Дата">{{$order->created_at->format('d.m.Y H:i')}}</td>
                            <td data-label="Товар">{{$order->good->name}}</td>
                            <td data-label="Покупатель"><a href="{{url('/insider/profile/'.$order->user->id)}}">{{$order->user->name}}</a></td>
                            <td data-label="Цена"><strong>{{$order->good->price}}</strong> <img src="{{ url('images/icons/icons8-coins-48.png') }}" height="20" alt=""/></td>
                            <td data-label="Действия" class="text-nowrap market-orders-actions">
                                <a href="{{url('/insider/market/ship/'.$order->id)}}" class="btn btn-success btn-sm">Доставлено</a>
                                <a href="{{url('/insider/market/cancel/'.$order->id)}}" class="btn btn-warning btn-sm" data-confirm="Отменить заказ?">Отменить</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <div class="p-3 text-muted">Нет активных заказов.</div>
            @endif
        </div>
    </div>

    <div class="gc-card management-table-card">
        <div class="card-body">
            <h5 class="mb-1">Доставленные заказы</h5>
            <p class="text-muted mb-0">Последние 50 выполненных заказов</p>
        </div>
        <div class="table-responsive">
            @if($shipped_orders->count() > 0)
                <table class="table table-hover mb-0 management-table market-orders-table">
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
                            <td data-label="Дата заказа">{{$order->created_at->format('d.m.Y H:i')}}</td>
                            <td data-label="Дата доставки">{{$order->updated_at->format('d.m.Y H:i')}}</td>
                            <td data-label="Товар">{{$order->good->name}}</td>
                            <td data-label="Покупатель"><a href="{{url('/insider/profile/'.$order->user->id)}}">{{$order->user->name}}</a></td>
                            <td data-label="Цена"><strong>{{$order->good->price}}</strong> <img src="{{ url('images/icons/icons8-coins-48.png') }}" height="20" alt=""/></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <div class="p-3 text-muted">Нет доставленных заказов.</div>
            @endif
        </div>
    </div>
    </div>
@endsection
