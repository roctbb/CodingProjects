@php
    $previewImage = old('image', $good?->image);
    $previewName = old('name', $good?->name ?? 'Новый товар');
    $previewNumber = old('number', $good?->number ?? 1);
    $previewAuction = old('sale_type', $good?->sale_type ?? 'regular') === 'auction';
@endphp
<section class="col-12 gc-form-preview" aria-labelledby="market-preview-title" data-market-preview="{{ $good ? 'market-edit-form' : 'market-create-form' }}">
    <article class="gc-card overflow-hidden d-flex flex-column">
        <header class="gc-panel-heading pb-3"><h2 id="market-preview-title">Предпросмотр товара</h2></header>
        <div class="ratio ratio-4x3 gc-media-frame">
            <img class="w-100 h-100 object-fit-cover @if(!$previewImage) d-none @endif" @if($previewImage) src="{{ $previewImage }}" @endif alt="{{ $previewName }}" data-market-preview-image>
            <div class="d-flex align-items-center justify-content-center text-muted fs-2 @if($previewImage) d-none @endif" data-market-preview-empty><i class="fas fa-gift" aria-hidden="true"></i></div>
        </div>
        <div class="p-3 d-flex flex-column flex-grow-1">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <span class="badge rounded-pill {{ $previewNumber > 0 ? 'bg-body-tertiary' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' }}" data-market-preview-stock>{{ $previewNumber > 0 ? 'В наличии: '.$previewNumber : 'Закончился' }}</span>
                <span class="badge rounded-pill bg-info-subtle text-info-emphasis border border-info-subtle" data-market-preview-sale-type>{{ $previewAuction ? 'Аукцион' : 'Покупка' }}</span>
            </div>
            <h6 class="fw-medium lh-sm mb-2" data-market-preview-name>{{ $previewName }}</h6>
            <p class="text-muted small lh-sm mb-3" data-market-preview-description>{{ old('description', $good?->description ?? 'Описание товара появится здесь.') }}</p>
            <div class="gc-purchase-footer mt-auto"><span class="gc-purchase-price"><span data-market-preview-price>{{ old('price', $good?->price ?? 0) }}</span> GC</span><button class="btn btn-primary btn-sm pe-none" type="button" tabindex="-1" aria-disabled="true"><span data-market-preview-action>{{ $previewAuction ? 'Ставка' : 'Купить' }}</span></button></div>
        </div>
    </article>
</section>
