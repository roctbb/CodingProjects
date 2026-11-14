<div class="gc-auto-columns gc-align-start">
    <x-gc-panel title="Мои заказы" class="gc-purchase-panel">
        <x-slot:actions><span class="gc-meta-label">{{ method_exists($myPurchases, 'total') ? $myPurchases->total() : $myPurchases->count() }}</span></x-slot:actions>
        <div class="gc-rows gc-purchase-list">
            @forelse($myPurchases as $deal)
                <div class="gc-row gc-purchase-row">
                    <div class="gc-summary-line"><strong class="fw-medium">{{ $deal->good?->name ?? 'Товар удалён' }}</strong><span class="gc-amount">{{ $deal->price ?? $deal->good?->price ?? '—' }} GC</span></div>
                    <div class="gc-inline-meta"><span class="gc-soft-badge">{{ $deal->shipped ? 'Доставлено' : 'Ожидает доставки' }}</span><span>№{{ $deal->id }}</span></div>
                    <div class="gc-inline-meta"><time datetime="{{ $deal->created_at?->toIso8601String() }}">Заказан {{ $deal->created_at?->format('d.m.Y') }}</time>@if($deal->shipped)<span>Выдан {{ $deal->updated_at?->format('d.m.Y') }}</span>@endif</div>
                </div>
            @empty
                <p class="text-muted mb-3">Заказов пока нет. Выберите награду в каталоге товаров.</p>
                <a href="#market-goods" data-market-tab-link class="btn btn-outline-primary btn-sm">Выбрать товар</a>
            @endforelse
        </div>
        @if(method_exists($myPurchases, 'hasPages') && $myPurchases->hasPages())<div class="mt-4">{{ $myPurchases->links('pagination::bootstrap-5') }}</div>@endif
    </x-gc-panel>
    <x-gc-panel title="Моя цифровая коллекция" class="gc-purchase-panel">
        <x-slot:actions><span class="gc-meta-label">{{ $ownedDigitalGoods->count() }}</span></x-slot:actions>
        <p class="gc-help-text mb-3">Купленные предметы и действующие оформления. Все списания — в <a href="#market-history" data-market-tab-link>истории GC</a>.</p>
        <div class="gc-rows gc-purchase-list gc-purchase-collection">
            @forelse($ownedDigitalGoods as $item)
                @php
                    $isRoomItem = ($item['type'] ?? 'learning_avatar_item') === 'learning_avatar_item';
                @endphp
                <div class="gc-row gc-row--split">
                    <div class="min-width-0"><div class="fw-medium">{{ $item['name'] }}</div><div class="gc-inline-meta"><span>{{ ($item['active'] ?? false) ? 'Активно' : 'В коллекции' }}</span>@if(!empty($item['active_until']))<span>до {{ $item['active_until']->format('d.m.Y') }}</span>@endif</div></div>
                    <a href="{{ url('/insider/profile/'.$user->id.($isRoomItem ? '#room-settings' : '')) }}" class="btn btn-outline-secondary btn-sm" aria-label="{{ $isRoomItem ? 'Выбрать в комнате: ' : 'Посмотреть в профиле: ' }}{{ $item['name'] }}">{{ $isRoomItem ? 'В комнату' : 'В профиль' }}</a>
                </div>
            @empty
                <p class="text-muted mb-3">Здесь появятся ваши предметы и активные оформления.</p>
                <a href="#market-digital" data-market-tab-link class="btn btn-outline-primary btn-sm">Выбрать оформление</a>
            @endforelse
        </div>
    </x-gc-panel>
</div>
