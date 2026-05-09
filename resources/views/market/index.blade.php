@extends('layouts.left-menu')

@section('title', 'Магазин')

@section('content')
    @php
        $goodsTabActive = $goods->count() > 0 || $auctions->count() == 0;
    @endphp

    <div class="gc-card market-hero gc-page-header mb-3">
        <div class="min-width-0">
            <span class="gc-eyebrow">Workspace</span>
            <h2 class="mb-1">Магазин</h2>
            <div class="d-flex flex-wrap gap-2 text-muted small">
                <span>
                    <i class="fas fa-coins text-warning"></i>
                    <strong>{{ $user->balance() }}</strong> GC
                </span>
                <a href="{{ url('/insider/profile#gc-history') }}" class="text-muted text-decoration-none">История GC</a>
                <span><strong>{{ $goods->count() }}</strong> товаров</span>
                <span><strong>{{ $auctions->count() }}</strong> аукционов</span>
            </div>
        </div>
        @if ($canManageMarket)
            <div class="d-flex gap-2 flex-wrap justify-content-end">
                @if ($user->role == 'admin')
                    <a class="btn btn-success rounded-3 fw-semibold px-3 py-2" href="{{ url('/insider/market/create/') }}"><i class="fas fa-plus me-1"></i>Добавить</a>
                @endif
            </div>
        @endif
    </div>

    <div class="d-flex align-items-center justify-content-between mb-3">
        <ul class="nav nav-pills gc-segmented-tabs" id="marketTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link fw-semibold text-nowrap rounded-3 px-2 px-sm-3 py-2 small @if($goodsTabActive) active @endif" id="market-goods-tab" data-bs-toggle="tab" data-bs-target="#market-goods" type="button" role="tab">
                    Товары <span class="badge rounded-pill bg-body gc-tab-count">{{ $goods->count() }}</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-semibold text-nowrap rounded-3 px-2 px-sm-3 py-2 small @if(!$goodsTabActive) active @endif" id="market-auctions-tab" data-bs-toggle="tab" data-bs-target="#market-auctions" type="button" role="tab">
                    Аукционы <span class="badge rounded-pill bg-body gc-tab-count">{{ $auctions->count() }}</span>
                </button>
            </li>
            @if ($canManageMarket)
                <li class="nav-item">
                    <button class="nav-link fw-semibold text-nowrap rounded-3 px-2 px-sm-3 py-2 small" id="market-archive-tab" data-bs-toggle="tab" data-bs-target="#market-archive" type="button" role="tab">
                        Архив <span class="badge rounded-pill bg-body gc-tab-count">{{ $archive->count() }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold text-nowrap rounded-3 px-2 px-sm-3 py-2 small" id="market-orders-tab" data-bs-toggle="tab" data-bs-target="#market-orders" type="button" role="tab">
                        Заказы <span class="badge rounded-pill bg-body gc-tab-count">{{ $active_orders->count() }}</span>
                    </button>
                </li>
            @endif
        </ul>
    </div>

    <div class="tab-content" id="marketTabsContent">
        <div class="tab-pane fade @if($goodsTabActive) show active @endif" id="market-goods" role="tabpanel" aria-labelledby="market-goods-tab">
            @if($goods->count())
                <div class="gc-card gc-toolbar-card market-goods-toolbar">
                    <div>
                        <h5 class="mb-1">Товары</h5>
                        <p class="mb-0 text-muted small">Обычные покупки без ставок.</p>
                    </div>
                    <div class="input-group input-group-sm gc-search-box market-search">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="search" class="form-control" placeholder="Найти товар" aria-label="Найти товар" data-market-search data-market-grid="#market-goods-grid">
                        <button class="btn d-none" type="button" data-market-clear aria-label="Очистить поиск"><i class="fas fa-times"></i></button>
                        <span class="input-group-text gc-search-box__count" data-market-count>{{ $goods->count() }} из {{ $goods->count() }}</span>
                    </div>
                </div>
            @endif
            @include('market.partials.goods_grid', ['items' => $goods, 'isArchive' => false, 'gridId' => 'market-goods-grid'])
        </div>

        <div class="tab-pane fade @if(!$goodsTabActive) show active @endif" id="market-auctions" role="tabpanel" aria-labelledby="market-auctions-tab">
            @if($auctions->count())
                <div class="gc-card gc-toolbar-card market-goods-toolbar">
                    <div>
                        <h5 class="mb-1">Аукционы</h5>
                        <p class="mb-0 text-muted small">Ограниченные призы для верхних ставок.</p>
                    </div>
                    <div class="input-group input-group-sm gc-search-box market-search">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="search" class="form-control" placeholder="Найти аукцион" aria-label="Найти аукцион" data-market-search data-market-grid="#market-auctions-grid">
                        <button class="btn d-none" type="button" data-market-clear aria-label="Очистить поиск"><i class="fas fa-times"></i></button>
                        <span class="input-group-text gc-search-box__count" data-market-count>{{ $auctions->count() }} из {{ $auctions->count() }}</span>
                    </div>
                </div>
            @endif
            @include('market.partials.goods_grid', ['items' => $auctions, 'isArchive' => false, 'gridId' => 'market-auctions-grid', 'emptyTitle' => 'Нет активных аукционов', 'emptyText' => 'Новые аукционы появятся здесь.'])
        </div>

        @if ($canManageMarket)
            <div class="tab-pane fade" id="market-archive" role="tabpanel" aria-labelledby="market-archive-tab">
                @if($archive->count())
                    <div class="gc-card gc-toolbar-card market-goods-toolbar">
                        <div>
                            <h5 class="mb-1">Архив</h5>
                            <p class="mb-0 text-muted small">Снятые с продажи товары.</p>
                        </div>
                        <div class="input-group input-group-sm gc-search-box market-search">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="search" class="form-control" placeholder="Найти в архиве" aria-label="Найти в архиве" data-market-search data-market-grid="#market-archive-grid">
                            <button class="btn d-none" type="button" data-market-clear aria-label="Очистить поиск"><i class="fas fa-times"></i></button>
                            <span class="input-group-text gc-search-box__count" data-market-count>{{ $archive->count() }} из {{ $archive->count() }}</span>
                        </div>
                    </div>
                @endif
                @include('market.partials.goods_grid', ['items' => $archive, 'isArchive' => true, 'gridId' => 'market-archive-grid'])
            </div>

            <div class="tab-pane fade" id="market-orders" role="tabpanel" aria-labelledby="market-orders-tab">
                <div class="d-grid gap-3">
                    <div class="gc-card overflow-hidden">
                        <div class="gc-section-header gc-section-header--inline">
                            <span class="gc-icon-tile market-orders-icon flex-shrink-0"><i class="fas fa-clock"></i></span>
                            <div>
                                <h5 class="mb-1">Активные заказы</h5>
                                <p class="text-muted mb-0 small">Заказы, ожидающие доставки</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            @if($active_orders->count() > 0)
                                @include('market.partials.orders_table', ['orders' => $active_orders, 'showActions' => true, 'showShippedAt' => false, 'returnUrl' => request()->fullUrl().'#market-orders'])
                            @else
                                <div class="gc-empty-inline">
                                    <span class="gc-empty-icon"><i class="fas fa-inbox"></i></span>
                                    <p class="mb-0">Нет активных заказов.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="gc-card overflow-hidden">
                        <div class="gc-section-header gc-section-header--inline">
                            <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-check"></i></span>
                            <div>
                                <h5 class="mb-1">Доставленные заказы</h5>
                                <p class="text-muted mb-0 small">История доставок, по 25 заказов на странице</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            @if($shipped_orders->count() > 0)
                                @include('market.partials.orders_table', ['orders' => $shipped_orders, 'showActions' => false, 'showShippedAt' => true])
                            @else
                                <div class="gc-empty-inline">
                                    <span class="gc-empty-icon"><i class="fas fa-box-open"></i></span>
                                    <p class="mb-0">Нет доставленных заказов.</p>
                                </div>
                            @endif
                        </div>
                        @if($shipped_orders->hasPages())
                            <div class="market-orders-pagination border-top p-3">
                                {{ $shipped_orders->links('pagination::bootstrap-5') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
