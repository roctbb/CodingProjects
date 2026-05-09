@extends('layouts.left-menu')

@section('title')
    Заказы магазина
@endsection

@section('content')
    <div class="container-fluid px-0">
        <div class="gc-card gc-page-header mb-4">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/market') }}"><i class="icon ion-chevron-left"></i> В магазин</a>
                <h2 class="fw-bold lh-sm mb-1">Заказы магазина</h2>
                <p class="mb-0 text-muted">Доставка и история покупок за GC.</p>
            </div>
            <div class="row g-2 flex-nowrap flex-shrink-0">
                <div class="col">
                    <div class="gc-summary-tile">
                        <strong>{{ $active_orders->count() }}</strong>
                        <span>активных</span>
                    </div>
                </div>
                <div class="col">
                    <div class="gc-summary-tile">
                        <strong>{{ $shipped_orders->total() }}</strong>
                        <span>доставлено</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="gc-card overflow-hidden mb-3">
            <div class="gc-section-header gc-section-header--inline">
                <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-clock"></i></span>
                <div>
                    <h5 class="mb-1">Активные заказы</h5>
                    <p class="text-muted mb-0 small">Заказы, ожидающие доставки</p>
                </div>
            </div>
            <div class="table-responsive">
                @if($active_orders->count() > 0)
                    @include('market.partials.orders_table', ['orders' => $active_orders, 'showActions' => true, 'showShippedAt' => false, 'returnUrl' => request()->fullUrl()])
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
@endsection
