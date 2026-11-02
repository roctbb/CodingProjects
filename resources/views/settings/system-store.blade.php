@extends('layouts.left-menu')
@section('title', 'Цены системных товаров')
@section('content')
<div class="container-xl px-0">
    @include('settings.partials.tabs')
    <div class="gc-card gc-page-header mb-4">
        <div>
            <span class="gc-eyebrow">Администрирование</span>
            <h2 class="fw-medium mb-1">Цены системных товаров</h2>
            <p class="text-muted mb-0">Стоимость в GC для магазина, профиля и платных действий.</p>
        </div>
        <button type="submit" form="system-store-settings" class="btn btn-success rounded-3 px-4">Сохранить</button>
    </div>
    @if(session('status'))
        <div class="alert alert-success rounded-3" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger rounded-3" role="alert">
            <strong>Изменения не сохранены.</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form id="system-store-settings" method="POST" action="{{ route('settings.system-store.update') }}">
        @csrf
        @method('PUT')
        <section class="gc-card p-3 p-md-4 mb-4 border border-warning">
            <h4>🎃 Тыквенный спас 2026!</h4>
            <p class="text-muted">Скидка 50% на генерацию персонажа. На главной появится плашка с ценой и ссылкой на генерацию.</p>
            <input type="hidden" name="settings[pumpkin_promo][enabled]" value="0">
            <label class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="settings[pumpkin_promo][enabled]" value="1"
                       @checked(old('settings.pumpkin_promo.enabled', $settings['pumpkin_promo']['enabled']))>
                <span class="form-check-label">Акция включена</span>
            </label>
            <label for="promo-ends-at" class="form-label">Окончание акции, московское время</label>
            <input id="promo-ends-at" class="form-control" style="max-width: 22rem" type="datetime-local"
                   name="settings[pumpkin_promo][ends_at]" value="{{ old('settings.pumpkin_promo.ends_at', $settings['pumpkin_promo']['ends_at']) }}">
            <div class="form-text">После этой даты скидка и плашка отключатся автоматически. Пустая дата — до ручного выключения.</div>
        </section>
        <div class="row g-3 mb-4">
            @foreach(collect($items)->groupBy('group', true) as $group => $groupItems)
                <div class="col-12 col-xl-6">
                    <section class="gc-card p-3 p-md-4 h-100">
                        <h4 class="mb-3">{{ $group }}</h4>
                        @foreach($groupItems as $key => $item)
                            <div class="row align-items-center g-2 mb-3">
                                <label class="col-sm-8 form-label mb-0" for="price-{{ $key }}">{{ $item['label'] }}</label>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <input id="price-{{ $key }}" class="form-control" type="number" min="1" max="100000" step="1" required
                                               name="settings[prices][{{ $key }}]" value="{{ old('settings.prices.'.$key, $settings['prices'][$key]) }}">
                                        <span class="input-group-text">GC</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </section>
                </div>
            @endforeach
        </div>
        <p class="text-muted small">Указаны базовые цены. Скидка на генерацию применяется отдельно. Уже оплаченные покупки и возвраты сохраняют исходную стоимость; бонусы питомцев продолжают действовать.</p>
        <button class="btn btn-success rounded-3 px-4" type="submit">Сохранить цены и акцию</button>
    </form>
</div>
@endsection
