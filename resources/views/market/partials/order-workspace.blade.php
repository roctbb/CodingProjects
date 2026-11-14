@php
    $ordersFragment = $ordersFragment ?? null;
    $ordersReturnUrl = request()->fullUrl().($ordersFragment ? '#'.$ordersFragment : '');
@endphp
<div class="gc-stack">
    <x-gc-workspace-toolbar>
        <form method="GET" action="{{ request()->url() }}{{ $ordersFragment ? '#'.$ordersFragment : '' }}" class="gc-toolbar w-100" role="search" aria-label="Поиск заказов">
            <div class="input-group gc-search-box gc-toolbar__search">
                <span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
                <input class="form-control" type="search" name="orders_q" value="{{ $ordersSearch }}" maxlength="200" placeholder="Товар, ученик или номер заказа" aria-label="Товар, ученик или номер заказа">
                <button class="btn btn-outline-secondary" type="submit">Найти</button>
            </div>
            @if($ordersSearch !== '')<a class="gc-text-action" href="{{ request()->url() }}{{ $ordersFragment ? '#'.$ordersFragment : '' }}">Сбросить</a>@endif
            <span class="gc-meta-label">К выдаче: {{ $active_orders->total() }} · Доставлено: {{ $shipped_orders->total() }}</span>
        </form>
    </x-gc-workspace-toolbar>
    @foreach([['orders' => $active_orders, 'active' => true, 'title' => 'Ожидают доставки'], ['orders' => $shipped_orders, 'active' => false, 'title' => 'Доставленные заказы']] as $section)
        <section class="gc-card gc-orders-panel" aria-label="{{ $section['title'] }}">
            <header class="gc-panel-heading"><h2>{{ $section['title'] }} <span class="gc-meta-label">{{ $section['orders']->total() }}</span></h2><x-gc-page-navigation :paginator="$section['orders']" :label="$section['title'].' — страницы'" /></header>
            @if($section['orders']->count())
                <div class="table-responsive">@include('market.partials.orders_table', ['orders' => $section['orders'], 'showActions' => $section['active'], 'showShippedAt' => !$section['active'], 'returnUrl' => $ordersReturnUrl])</div>
            @else
                <div class="gc-empty-inline"><p class="mb-0">{{ $ordersSearch !== '' ? 'По этому запросу заказов не найдено.' : ($section['active'] ? 'Все заказы выданы.' : 'Доставленных заказов пока нет.') }}</p></div>
            @endif
            @if($section['orders']->hasPages())
                <div class="gc-panel-body gc-summary-line market-orders-pagination"><span class="gc-help-text">{{ $section['orders']->firstItem() }}–{{ $section['orders']->lastItem() }} из {{ $section['orders']->total() }}</span><x-gc-page-navigation :paginator="$section['orders']" :label="$section['title'].' — следующие страницы'" /></div>
            @endif
        </section>
    @endforeach
</div>
