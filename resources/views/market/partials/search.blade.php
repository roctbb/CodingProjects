@if($items->count())
    <x-gc-workspace-toolbar class="gc-catalog-controls mb-4" data-market-controls>
        <div class="gc-toolbar">
            <div class="input-group gc-search-box gc-toolbar__search">
                <span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
                <input type="search" class="form-control" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}" data-market-search data-market-grid="#{{ $gridId }}">
                <button class="btn d-none" type="button" data-market-clear aria-label="Очистить поиск"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            @if($gridId !== 'market-archive-grid')
                <select class="form-select form-select-sm gc-toolbar__select" data-market-availability aria-label="Доступность товаров"><option value="all">Все товары</option><option value="available">Доступные мне</option></select>
            @endif
            @if(in_array($gridId, ['market-goods-grid', 'market-digital-grid']) && app(\App\Services\SystemStoreSettings::class)->promotionActive())
                <label class="form-check mb-0 small"><input class="form-check-input" type="checkbox" data-market-promotion-only @checked(request('promotion') === '1')><span class="form-check-label">Со скидкой</span></label>
            @endif
            <span class="gc-meta-label" data-market-count aria-live="polite">{{ $items->count() }} из {{ $items->count() }}</span>
        </div>
        @isset($categories)
            <div class="gc-filters gc-filters--scroll" role="group" aria-label="Категории цифровых товаров">
                @foreach($categories as $categoryKey => $category)
                    @if($category['count'] > 0 || $categoryKey === 'all')
                        <button type="button" class="gc-filter {{ $categoryKey === 'all' ? 'is-active' : '' }}" aria-pressed="{{ $categoryKey === 'all' ? 'true' : 'false' }}" data-market-category-filter="{{ $categoryKey }}" data-market-grid="#{{ $gridId }}"><i class="{{ $category['icon'] }}" aria-hidden="true"></i>{{ $category['label'] }}<span>{{ $category['count'] }}</span></button>
                    @endif
                @endforeach
            </div>
        @endisset
    </x-gc-workspace-toolbar>
@endif
