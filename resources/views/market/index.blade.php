@extends('layouts.left-menu')
@section('title', 'Магазин')
@section('content')
    @php
        $myPurchases = $myPurchases ?? collect();
        $coinTransactions = $coinTransactions ?? collect();
        $ownedDigitalGoods = $ownedDigitalGoods ?? collect();
        $goodsTabActive = $goods->count() > 0 || ($digitalGoods->count() == 0 && $auctions->count() == 0);
        $digitalTabActive = !$goodsTabActive && $digitalGoods->count() > 0;
        $auctionsTabActive = !$goodsTabActive && !$digitalTabActive;
        $digitalCategoryDefinitions = [
            'all' => ['label' => 'Все', 'icon' => 'fas fa-layer-group', 'count' => $digitalGoods->count()],
            'pets' => ['label' => 'Питомцы', 'icon' => 'fas fa-paw', 'count' => $digitalGoods->filter(fn ($item) => ($item['slot'] ?? null) === 'pet_right')->count()],
            'room_items' => ['label' => 'Предметы комнаты', 'icon' => 'fas fa-cube', 'count' => $digitalGoods->filter(fn ($item) => ($item['type'] ?? 'learning_avatar_item') === 'learning_avatar_item' && ($item['slot'] ?? null) !== 'pet_right')->count()],
            'frames' => ['label' => 'Рамки', 'icon' => 'fas fa-user-circle', 'count' => $digitalGoods->filter(fn ($item) => in_array(($item['type'] ?? null), ['avatar_frame', 'custom_avatar_frame'], true))->count()],
            'titles' => ['label' => 'Звания', 'icon' => 'fas fa-certificate', 'count' => $digitalGoods->filter(fn ($item) => ($item['type'] ?? null) === 'custom_title')->count()],
            'services' => ['label' => 'Услуги', 'icon' => 'fas fa-wand-magic-sparkles', 'count' => $digitalGoods->filter(fn ($item) => ($item['type'] ?? null) === 'room_character')->count()],
        ];
    @endphp
    <div class="gc-page-masthead mb-4">
    <x-gc-page-header title="Магазин" mark="bag-shopping" description="Награды за учебу: предметы для комнаты, цифровые товары и аукционы.">
        <a class="gc-balance-pill text-decoration-none" href="#market-history" data-market-tab-link><i class="fas fa-coins" aria-hidden="true"></i><strong>{{ $user->balance() }}</strong> GC</a>
        @if($user->role == 'admin')
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('settings.system-store.edit') }}">Цены и акции</a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ url('/insider/market/credit') }}">Начислить</a>
            <a class="btn btn-primary btn-sm" href="{{ url('/insider/market/create/') }}"><i class="fas fa-plus me-1"></i>Добавить товар</a>
        @endif
    </x-gc-page-header>
    <x-gc-tab-strip class="mb-4">
    <ul class="nav gc-page-tabs" id="marketTabs" role="tablist" aria-label="Разделы магазина">
        @foreach([
            'goods' => ['Товары', $goodsTabActive], 'digital' => ['Цифровые', $digitalTabActive],
            'auctions' => ['Аукционы', $auctionsTabActive], 'purchases' => ['Мои покупки', false], 'history' => ['История GC', false],
        ] as $tab => [$label, $active])
            <li class="nav-item" role="presentation"><button class="nav-link {{ $active ? 'active' : '' }}" id="market-{{ $tab }}-tab" data-bs-toggle="tab" data-bs-target="#market-{{ $tab }}" type="button" role="tab" aria-controls="market-{{ $tab }}" aria-selected="{{ $active ? 'true' : 'false' }}">{{ $label }}</button></li>
        @endforeach
        @if($canManageMarket)
            @foreach(['archive' => 'Архив', 'orders' => 'Заказы учеников'] as $tab => $label)
                <li class="nav-item" role="presentation"><button class="nav-link" id="market-{{ $tab }}-tab" data-bs-toggle="tab" data-bs-target="#market-{{ $tab }}" type="button" role="tab" aria-controls="market-{{ $tab }}" aria-selected="false">{{ $label }}</button></li>
            @endforeach
        @endif
    </ul>
    </x-gc-tab-strip>
    </div>
    @include('market.partials.promotion-banner', ['catalogOnly' => true])
    <div class="tab-content" id="marketTabsContent">
        <div class="tab-pane fade {{ $goodsTabActive ? 'show active' : '' }}" id="market-goods" role="tabpanel" aria-labelledby="market-goods-tab">
            @include('market.partials.search', ['items' => $goods, 'gridId' => 'market-goods-grid', 'placeholder' => 'Найти товар'])
            @include('market.partials.goods_grid', ['items' => $goods, 'isArchive' => false, 'gridId' => 'market-goods-grid'])
        </div>
        <div class="tab-pane fade {{ $digitalTabActive ? 'show active' : '' }}" id="market-digital" role="tabpanel" aria-labelledby="market-digital-tab">
            <div class="gc-collection-invitation mb-3">
                <span class="gc-page-mark" aria-hidden="true"><i class="fas fa-cubes"></i></span>
                <div><strong>Твоя комната — твой стиль</strong><p class="gc-help-text mb-0">Предметы, питомцы и оформления для профиля.</p></div>
                <a class="btn btn-outline-secondary btn-sm" href="{{ url('/insider/profile/'.$user->id.'#room-settings') }}">Моя комната</a>
            </div>
            @include('market.partials.search', ['items' => $digitalGoods, 'gridId' => 'market-digital-grid', 'placeholder' => 'Найти цифровой товар', 'categories' => $digitalCategoryDefinitions])
            @include('market.partials.digital_goods_grid', ['items' => $digitalGoods, 'gridId' => 'market-digital-grid'])
        </div>
        <div class="tab-pane fade {{ $auctionsTabActive ? 'show active' : '' }}" id="market-auctions" role="tabpanel" aria-labelledby="market-auctions-tab">
            @include('market.partials.search', ['items' => $auctions, 'gridId' => 'market-auctions-grid', 'placeholder' => 'Найти аукцион'])
            @include('market.partials.goods_grid', ['items' => $auctions, 'isArchive' => false, 'gridId' => 'market-auctions-grid', 'emptyTitle' => 'Нет активных аукционов', 'emptyText' => 'Новые аукционы появятся здесь.'])
        </div>
        <div class="tab-pane fade" id="market-purchases" role="tabpanel" aria-labelledby="market-purchases-tab">
            @include('market.partials.purchases')
        </div>
        <div class="tab-pane fade" id="market-history" role="tabpanel" aria-labelledby="market-history-tab">
            <x-gc-panel title="История GC">
                <x-slot:actions><span class="gc-meta-label">Баланс {{ $user->balance() }} GC</span></x-slot:actions>
                <x-gc-wallet-history :transactions="$coinTransactions" />
                @if(method_exists($coinTransactions, 'hasPages') && $coinTransactions->hasPages())<div class="mt-4">{{ $coinTransactions->links('pagination::bootstrap-5') }}</div>@endif
            </x-gc-panel>
        </div>
        @if ($canManageMarket)
            <div class="tab-pane fade" id="market-archive" role="tabpanel" aria-labelledby="market-archive-tab">
                @include('market.partials.search', ['items' => $archive, 'gridId' => 'market-archive-grid', 'placeholder' => 'Найти в архиве'])
                @include('market.partials.goods_grid', ['items' => $archive, 'isArchive' => true, 'gridId' => 'market-archive-grid'])
            </div>

            <div class="tab-pane fade" id="market-orders" role="tabpanel" aria-labelledby="market-orders-tab">
                @include('market.partials.order-workspace', ['ordersFragment' => 'market-orders'])
            </div>
        @endif
    </div>
@endsection
