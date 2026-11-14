<table class="table table-hover align-middle mb-0 gc-data-table market-orders-table">
    <thead class="text-uppercase small">
    <tr>
        <th scope="col">Дата заказа</th>
        @if($showShippedAt)
            <th scope="col">Дата доставки</th>
        @endif
        <th scope="col">Товар</th>
        <th scope="col">Покупатель</th>
        <th scope="col">Цена</th>
        @if($showActions)
            <th scope="col">Действия</th>
        @endif
    </tr>
    </thead>
    <tbody>
    @foreach($orders as $order)
        @php
            $orderPrice = $order->price ?? $order->good?->price;
        @endphp
        <tr>
            <td class="market-order-date" data-label="Заказан"><time datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->format('d.m.Y H:i') }}</time><span class="gc-help-text d-block">№{{ $order->id }}</span></td>
            @if($showShippedAt)
                <td class="market-order-date market-order-date--shipped" data-label="Доставлен">{{$order->updated_at->format('d.m.Y H:i')}}</td>
            @endif
            <td class="market-order-product">
                {{ $order->good?->name ?? 'Товар удалён' }}
                @if($order->source == 'auction')
                    <span class="badge rounded-pill bg-info-subtle text-info-emphasis border border-info-subtle ms-1">Аукцион</span>
                @endif
            </td>
            <td class="market-order-buyer">@if($order->user)<a class="text-decoration-none fw-medium" href="{{url('/insider/profile/'.$order->user->id)}}">{{$order->user->name}}</a>@else Ученик удалён @endif</td>
            <td class="market-order-price"><strong>{{ $orderPrice ?? '—' }}</strong> <i class="fas fa-coins gc-currency-icon" aria-hidden="true"></i><span class="visually-hidden"> GC</span></td>
            @if($showActions)
                <td class="market-orders-actions">
                    @if($order->user && $orderPrice !== null)
                    @if($order->good)
                    <form method="POST" action="{{ url('/insider/market/ship/'.$order->id) }}" class="d-inline">@csrf<input type="hidden" name="return_url" value="{{ $returnUrl ?? request()->fullUrl() }}"><button type="submit" class="btn btn-primary btn-sm rounded-3 market-order-action market-order-action--ship">
                        <i class="fas fa-check" aria-hidden="true"></i> Выдать
                    </button></form>
                    @endif
                    <form method="POST" action="{{ url('/insider/market/cancel/'.$order->id) }}" class="d-inline">@csrf<input type="hidden" name="return_url" value="{{ $returnUrl ?? request()->fullUrl() }}"><button type="submit" class="btn btn-outline-danger btn-sm rounded-3 market-order-action" data-confirm="Отменить заказ №{{ $order->id }} и вернуть {{ $orderPrice ?? '—' }} GC покупателю?">
                        <i class="fas fa-times" aria-hidden="true"></i> Отменить
                    </button></form>
                    @else
                        <span class="gc-help-text">{{ !$order->user ? 'Покупатель удалён' : 'Цена не сохранена' }}</span>
                    @endif
                </td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table>
