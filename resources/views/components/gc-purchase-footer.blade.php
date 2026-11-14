@props(['price' => null, 'from' => false, 'originalPrice' => null])

<div class="gc-purchase-footer">
    @if($price !== null)
        <span class="gc-purchase-pricing">@if($originalPrice !== null && $originalPrice > $price)<s class="gc-original-price" aria-label="Прежняя цена {{ $originalPrice }} GC">{{ $originalPrice }} GC</s>@endif<span class="gc-catalog-price">@if($from)<span class="gc-meta-label">от</span> @endif{{ $price }} <span class="gc-meta-label">GC</span></span></span>
    @endif
    <div class="gc-purchase-footer__action">{{ $slot }}</div>
</div>
