@extends('layouts.left-menu')
@section('title', 'Цены и акции')
@section('content')
<div class="gc-form-layout">
    @include('settings.partials.masthead', ['title' => 'Цены и акции', 'description' => 'Стоимость в GC для магазина, профиля и платных действий.'])
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
        @include('settings.partials.store-promotion')
        <div class="row g-3 mb-4">
            @foreach(collect($items)->groupBy('group', true) as $group => $groupItems)
                <div class="col-12">
                    <section class="gc-card p-3 p-md-4 h-100">
                        <h2 class="gc-section-title">{{ $group }}</h2>
                        @foreach($groupItems as $key => $item)
                            <div class="row g-2 mb-3">
                                <label class="col-12 form-label mb-0" for="price-{{ $key }}">{{ $item['label'] }}</label>
                                <div class="col-12 col-sm-5">
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
        <p class="text-muted small">Указаны базовые цены. Скидка акции применяется к выбранным товарам. Уже оплаченные покупки и возвраты сохраняют исходную стоимость; бонусы питомцев продолжают действовать.</p>

    </form>
    <x-gc-form-actions form="system-store-settings" />
</div>
@endsection
