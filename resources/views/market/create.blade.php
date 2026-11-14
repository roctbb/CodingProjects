@extends('layouts.left-menu')

@section('title')
    Создание товара
@endsection

@section('content')
    <div class="container-xl px-0 gc-form-layout">
        <div class="gc-page-header mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/market') }}"><i class="fas fa-chevron-left"></i> В магазин</a>
                <h1 class="mb-1">Создание товара</h1>
                <p class="mb-0 text-muted">Новый товар для магазина и быстрый предпросмотр карточки.</p>
            </div>
        </div>

        <div class="row g-3 align-items-start">
            <div class="col-12">
                <div class="gc-card overflow-hidden">
                    <form id="market-create-form" method="POST" enctype="multipart/form-data" data-authoring-form>
                        {{ csrf_field() }}

                        <div class="p-3 p-md-4">
                            <div class="mb-3">
                                <label for="name" class="form-label">Название</label>
                                <input id="name" type="text" class="form-control rounded-3" name="name" value="{{ old('name') }}" maxlength="255" data-market-preview-field="name" required>
                                @error('name')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Описание</label>
                                <textarea id="description" class="form-control rounded-3" name="description" rows="5" data-market-preview-field="description" required>{{ old('description') }}</textarea>
                                @error('description')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-sm-6">
                                    <label for="number" class="form-label">Количество</label>
                                    <input id="number" type="number" min="0" class="form-control rounded-3" name="number" value="{{ old('number', 1) }}" data-market-preview-field="number" required>
                                    @error('number')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label for="price" class="form-label">Стоимость / стартовая ставка</label>
                                    <input id="price" type="number" min="0" class="form-control rounded-3" name="price" value="{{ old('price') }}" data-market-preview-field="price" required>
                                    @error('price')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="sale_type" class="form-label">Способ продажи</label>
                                <select id="sale_type" class="form-select rounded-3" name="sale_type" data-market-preview-field="sale_type">
                                    <option value="regular" @if (old('sale_type', 'regular') == 'regular') selected @endif>Обычная покупка</option>
                                    <option value="auction" @if (old('sale_type') == 'auction') selected @endif>Аукцион: верхние ставки получают товар</option>
                                </select>
                                @error('sale_type')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                                <small class="text-muted">Для аукциона количество означает число победителей.</small>
                            </div>

                            <div class="mb-3">
                                <label for="image" class="form-label">Фото</label>
                                <input id="image" type="url" class="form-control rounded-3" name="image" value="{{ old('image') }}" maxlength="255" data-market-preview-field="image" required>
                                @error('image')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <label class="gc-switch-card d-flex align-items-start gap-2 mb-0" for="in_stock">
                                <input id="in_stock" type="checkbox" class="form-check-input mt-1 flex-shrink-0" name="in_stock" value="on" @if (old('in_stock', 'on') == 'on') checked @endif>
                                <span class="d-grid gap-1 lh-sm">
                                    <strong>В продаже</strong>
                                    <small class="text-muted">Товар появится в магазине сразу после создания.</small>
                                </span>
                            </label>
                        </div>


                    </form>
                </div>
            </div>

            @include('market.partials.form-preview', ['good' => $good ?? null])
        </div>
        <x-gc-form-actions form="market-create-form" label="Создать товар" />
    </div>

@endsection
