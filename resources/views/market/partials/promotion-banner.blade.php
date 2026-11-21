@php
    $storeSettings = app(\App\Services\SystemStoreSettings::class);
    $promotion = $storeSettings->all()['promotion'];
    $promotionTabs = collect(['goods' => 'good:', 'digital' => 'digital:'])->filter(fn ($prefix) => collect($promotion['items'])->contains(fn ($target) => str_starts_with($target, $prefix)));
@endphp
@if($storeSettings->promotionActive())
    @if($compact ?? false)
        <a class="gc-home-offer" href="{{ url('/insider/market?promotion=1#market-'.($promotionTabs->keys()->first() ?? 'goods')) }}">
            <i class="fas fa-tag" aria-hidden="true"></i>
            <span>{{ $promotion['name'] }}</span>
            <strong>−{{ $promotion['discount_percent'] }}%</strong>
            <i class="fas fa-chevron-right" aria-hidden="true"></i>
        </a>
    @else
    <section class="gc-promotion-banner" aria-label="Акция магазина" @if($catalogOnly ?? false) data-market-promotion-banner @endif>
        <span class="gc-promotion-banner__icon" aria-hidden="true"><i class="fas fa-gift"></i></span>
        <div class="min-width-0"><div class="gc-actions"><h2>{{ $promotion['name'] }}</h2><x-gc-promotion-badge :percent="$promotion['discount_percent']" /></div>
            @if(filled($promotion['description']))<p class="gc-preserve-lines">{{ $promotion['description'] }}</p>@endif
            @if($promotion['ends_at'])<span class="gc-help-text">До {{ \Carbon\Carbon::parse($promotion['ends_at'], 'Europe/Moscow')->format('d.m.Y H:i') }} МСК</span>@endif
        </div>
        <div class="gc-promotion-banner__actions">
            @foreach($promotionTabs as $tab => $prefix)
                <a class="btn btn-outline-secondary btn-sm" href="{{ url('/insider/market?promotion=1#market-'.$tab) }}" @if($catalogOnly ?? false) data-market-promotion-link="{{ $tab }}" @endif>{{ $tab === 'goods' ? 'Товары со скидкой' : 'Цифровые со скидкой' }}</a>
            @endforeach
        </div>
    </section>
    @endif
@endif
