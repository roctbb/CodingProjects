<table class="table table-hover align-middle mb-0 gc-data-table market-orders-table">
    <thead class="text-uppercase small">
    <tr>
        <th>Дата заказа</th>
        @if($showShippedAt)
            <th>Дата доставки</th>
        @endif
        <th>Товар</th>
        <th>Покупатель</th>
        <th>Цена</th>
        @if($showActions)
            <th>Действия</th>
        @endif
    </tr>
    </thead>
    <tbody>
    @foreach($orders as $order)
        <tr>
            <td data-label="Дата заказа">{{$order->created_at->format('d.m.Y H:i')}}</td>
            @if($showShippedAt)
                <td data-label="Дата доставки">{{$order->updated_at->format('d.m.Y H:i')}}</td>
            @endif
            <td data-label="Товар">
                {{$order->good->name}}
                @if($order->source == 'auction')
                    <span class="badge rounded-pill bg-info-subtle text-info-emphasis border border-info-subtle ms-1">Аукцион</span>
                @endif
            </td>
            <td data-label="Покупатель"><a class="text-decoration-none fw-semibold" href="{{url('/insider/profile/'.$order->user->id)}}">{{$order->user->name}}</a></td>
            <td data-label="Цена"><strong>{{$order->displayPrice()}}</strong> <i class="fas fa-coins text-warning"></i></td>
            @if($showActions)
                <td data-label="Действия" class="market-orders-actions">
                    <a href="{{url('/insider/market/ship/'.$order->id)}}?return_url={{ urlencode($returnUrl ?? request()->fullUrl()) }}" class="btn btn-success btn-sm rounded-3 market-order-action market-order-action--ship">
                        <i class="fas fa-check"></i> Доставлено
                    </a>
                    <a href="{{url('/insider/market/cancel/'.$order->id)}}" class="btn btn-outline-danger btn-sm rounded-3 market-order-action" data-confirm="Отменить заказ?">
                        <i class="fas fa-times"></i> Отменить
                    </a>
                </td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table>
