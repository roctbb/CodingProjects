@extends('layouts.left-menu')

@section('title')
    Изменение товара "{{$good->name}}"
@endsection

@section('content')
    <div class="container-xl px-0">
        <div class="gc-card gc-page-header mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/market') }}"><i class="icon ion-chevron-left"></i> В магазин</a>
                <h2 class="mb-1 text-truncate">Изменение товара</h2>
                <p class="mb-0 text-muted text-truncate">{{ old('name', $good->name) }}</p>
            </div>
        </div>

        <div class="row g-3 align-items-start">
            <div class="col-12 col-lg-8">
                <div class="gc-card overflow-hidden">
                    <form method="POST" enctype="multipart/form-data">
                        {{ csrf_field() }}

                        <div class="p-3 p-md-4">
                            <div class="mb-3">
                                <label for="name" class="form-label">Название</label>
                                <input id="name" type="text" class="form-control rounded-3" name="name" value="{{ old('name', $good->name) }}" data-market-preview-field="name" required>
                                @error('name')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Описание</label>
                                <textarea id="description" class="form-control rounded-3" name="description" rows="5" data-market-preview-field="description" required>{{ old('description', $good->description) }}</textarea>
                                @error('description')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-sm-6">
                                    <label for="number" class="form-label">Количество</label>
                                    <input id="number" type="number" min="0" class="form-control rounded-3" name="number" value="{{ old('number', $good->number) }}" data-market-preview-field="number" required>
                                    @error('number')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label for="price" class="form-label">Стоимость / стартовая ставка</label>
                                    <input id="price" type="number" min="0" class="form-control rounded-3" name="price" value="{{ old('price', $good->price) }}" data-market-preview-field="price" required>
                                    @error('price')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="sale_type" class="form-label">Способ продажи</label>
                                <select id="sale_type" class="form-select rounded-3" name="sale_type" data-market-preview-field="sale_type" @if ($good->isAuction() && $good->auctionBids()->count() > 0) disabled @endif>
                                    <option value="regular" @if (old('sale_type', $good->sale_type ?? 'regular') == 'regular') selected @endif>Обычная покупка</option>
                                    <option value="auction" @if (old('sale_type', $good->sale_type ?? 'regular') == 'auction') selected @endif>Аукцион: верхние ставки получают товар</option>
                                </select>
                                @if ($good->isAuction() && $good->auctionBids()->count() > 0)
                                    <input type="hidden" name="sale_type" value="auction">
                                    <small class="text-muted">У аукциона уже есть ставки, поэтому способ продажи зафиксирован.</small>
                                @else
                                    <small class="text-muted">Для аукциона количество означает число победителей.</small>
                                @endif
                                @error('sale_type')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="image" class="form-label">Фото</label>
                                <input id="image" type="url" class="form-control rounded-3" name="image" value="{{ old('image', $good->image) }}" data-market-preview-field="image" required>
                                @error('image')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <label class="gc-switch-card d-flex align-items-start gap-2 mb-0" for="in_stock">
                                <input id="in_stock" type="checkbox" class="form-check-input mt-1 flex-shrink-0" name="in_stock" value="on" @if (old('in_stock', $good->in_stock ? 'on' : '') == 'on') checked @endif>
                                <span class="d-grid gap-1 lh-sm">
                                    <strong>В продаже</strong>
                                    <small class="text-muted">Товар виден в магазине и доступен для покупки при наличии.</small>
                                </span>
                            </label>
                        </div>

                        <div class="gc-form-footer flex-column-reverse flex-sm-row justify-content-end gap-2">
                            <a class="btn btn-outline-secondary rounded-3" href="{{ url('/insider/market') }}">Отмена</a>
                            <button type="submit" class="btn btn-success rounded-3 px-3 fw-semibold">Сохранить товар</button>
                        </div>
                    </form>
                </div>
            </div>

            <aside class="col-12 col-lg-4 sticky-lg-top pt-lg-1">
                <div class="text-muted fw-semibold small mb-2">Предпросмотр</div>
                <article class="gc-card overflow-hidden d-flex flex-column">
                    <div class="ratio ratio-4x3 gc-media-frame">
                        <img class="w-100 h-100 object-fit-cover" src="{{ old('image', $good->image) }}" alt="{{ old('name', $good->name) }}" data-market-preview-image>
                        <div class="d-flex align-items-center justify-content-center text-muted fs-2 d-none" data-market-preview-empty><i class="fas fa-gift"></i></div>
                    </div>

                    <div class="p-3 d-flex flex-column flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            @if (old('number', $good->number) > 0)
                                <span class="badge rounded-pill bg-body-tertiary" data-market-preview-stock>В наличии: {{ old('number', $good->number) }}</span>
                            @else
                                <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold" data-market-preview-stock>Закончился</span>
                            @endif
                            <span class="badge rounded-pill bg-info-subtle text-info-emphasis border border-info-subtle" data-market-preview-sale-type>{{ old('sale_type', $good->sale_type ?? 'regular') == 'auction' ? 'Аукцион' : 'Покупка' }}</span>
                        </div>

                        <h6 class="fw-bold lh-sm mb-2" data-market-preview-name>{{ old('name', $good->name) }}</h6>
                        <p class="text-muted small lh-sm mb-3" data-market-preview-description>{{ old('description', $good->description) }}</p>

                        <button class="btn btn-success gc-action-button gc-action-button--block mt-auto pe-none" type="button" tabindex="-1" aria-disabled="true">
                            <span data-market-preview-action>Купить за</span> <span data-market-preview-price>{{ old('price', $good->price) }}</span> <i class="fas fa-coins"></i>
                        </button>
                    </div>
                </article>
            </aside>
        </div>
    </div>

    @include('market.partials.form_preview_script')
@endsection
