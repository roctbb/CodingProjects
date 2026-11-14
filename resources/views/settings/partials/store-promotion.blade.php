@php
    $savedPromotion = $settings['promotion'];
    $savedPromotionStatus = 'Выключена';
    if ($savedPromotion['enabled']) {
        $promotionNow = now('Europe/Moscow');
        $savedPromotionStatus = !$savedPromotion['name'] || !count($savedPromotion['items']) ? 'Не настроена'
            : ($savedPromotion['ends_at'] && \Carbon\Carbon::parse($savedPromotion['ends_at'], 'Europe/Moscow')->lte($promotionNow) ? 'Завершена'
            : ($savedPromotion['starts_at'] && \Carbon\Carbon::parse($savedPromotion['starts_at'], 'Europe/Moscow')->gt($promotionNow) ? 'Запланирована' : 'Действует'));
    }
@endphp
<section class="gc-card p-3 p-md-4 mb-4" aria-labelledby="store-promotion-title">
    <div class="gc-summary-line mb-3"><h2 class="gc-section-title mb-0" id="store-promotion-title">Акция магазина</h2><span class="gc-soft-badge">Сейчас: {{ $savedPromotionStatus }}</span></div>
    <p class="gc-help-text mb-3">Выберите товары, размер скидки и период. Изменения начнут действовать после сохранения. <a href="{{ url('/insider/market') }}">Открыть магазин</a></p>
    <div class="gc-stack">
        <input type="hidden" name="settings[promotion][enabled]" value="0">
        <label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="settings[promotion][enabled]" value="1" @checked(old('settings.promotion.enabled', $settings['promotion']['enabled']))><span class="form-check-label">Акция включена</span></label>
        <div><label for="promo-name" class="form-label">Название</label><input id="promo-name" class="form-control" name="settings[promotion][name]" maxlength="120" value="{{ old('settings.promotion.name', $settings['promotion']['name']) }}" placeholder="Например, Неделя открытий"></div>
        <div><label for="promo-description" class="form-label">Сообщение для учеников</label><textarea id="promo-description" class="form-control" name="settings[promotion][description]" rows="2" maxlength="1000" placeholder="Какие товары участвуют и чему посвящена акция">{{ old('settings.promotion.description', $settings['promotion']['description']) }}</textarea><p class="form-text mb-0">Название и сообщение появятся на главной странице и в магазине, пока акция действует.</p></div>
        <div class="row g-3">
            <div class="col-12 col-sm-4"><label for="promo-percent" class="form-label">Скидка</label><div class="input-group"><input id="promo-percent" class="form-control" type="number" min="1" max="99" step="1" required name="settings[promotion][discount_percent]" value="{{ old('settings.promotion.discount_percent', $settings['promotion']['discount_percent']) }}"><span class="input-group-text">%</span></div></div>
            <div class="col-12 col-sm-4"><label for="promo-starts-at" class="form-label">Начало, МСК</label><input id="promo-starts-at" class="form-control" type="datetime-local" name="settings[promotion][starts_at]" value="{{ old('settings.promotion.starts_at', $settings['promotion']['starts_at']) }}"></div>
            <div class="col-12 col-sm-4"><label for="promo-ends-at" class="form-label">Окончание, МСК</label><input id="promo-ends-at" class="form-control" type="datetime-local" name="settings[promotion][ends_at]" value="{{ old('settings.promotion.ends_at', $settings['promotion']['ends_at']) }}"></div>
        </div>
        <p class="gc-help-text mb-0">Без дат акция действует до ручного выключения. Цена округляется вверх до целого GC, минимум — 1 GC.</p>
        <div data-promotion-picker data-source="{{ route('settings.system-store.targets') }}">
            <div class="gc-summary-line mb-3"><h3 class="gc-section-label mb-0">Товары акции</h3><button type="button" class="btn btn-outline-secondary btn-sm" data-promotion-open aria-haspopup="dialog" aria-controls="promotion-picker">Выбрать товары</button></div>
            <div class="gc-promotion-selection" data-promotion-selection>
                @foreach($promotionItems as $selectedItem)
                    @if($loop->index === 6)<details class="gc-promotion-selection__more gc-disclosure"><summary>Ещё {{ count($promotionItems) - 6 }} товаров</summary><div class="gc-promotion-selection mt-2">@endif
                    <span class="gc-promotion-selection__item" data-selected-promotion-item data-item-id="{{ $selectedItem['id'] }}" data-item-label="{{ $selectedItem['label'] }}"><input type="hidden" name="settings[promotion][items][]" value="{{ $selectedItem['id'] }}"><span>{{ $selectedItem['label'] }}</span><button type="button" class="gc-text-action" data-promotion-remove="{{ $selectedItem['id'] }}" aria-label="Убрать {{ $selectedItem['label'] }} из акции"><i class="fas fa-times" aria-hidden="true"></i></button></span>
                    @if($loop->last && count($promotionItems) > 6)</div></details>@endif
                @endforeach
            </div>
            <p class="gc-help-text mt-2 mb-0" data-promotion-selection-count aria-live="polite">Выбрано: {{ count($promotionItems) }}</p>
            <dialog class="gc-dialog gc-promotion-picker" id="promotion-picker" data-promotion-picker-modal aria-labelledby="promotion-picker-title">
                <div class="gc-promotion-picker__heading"><h2 id="promotion-picker-title">Товары акции</h2><button class="btn btn-outline-secondary btn-sm gc-icon-button" type="button" data-promotion-cancel aria-label="Закрыть выбор товаров"><i class="fas fa-times" aria-hidden="true"></i></button></div>
                <div class="gc-promotion-picker__filters">
                    <label for="promotion-kind" class="visually-hidden">Вид товаров</label><select class="form-select" id="promotion-kind" data-promotion-kind><option value="digital">Цифровые товары и услуги</option><option value="goods">Обычные товары</option></select>
                    <div class="gc-search-box input-group"><span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span><input id="promotion-search" class="form-control" type="search" maxlength="200" placeholder="Найти товар" aria-label="Найти товар акции" data-promotion-search autocomplete="off"></div>
                </div>
                <div class="gc-summary-line"><p class="gc-help-text mb-0" data-promotion-status aria-live="polite"></p><button class="btn btn-outline-secondary btn-sm" type="button" data-promotion-page-select>Выбрать страницу</button></div>
                <div class="gc-promotion-picker__list" data-promotion-list></div>
                <div class="gc-summary-line"><button class="btn btn-outline-secondary btn-sm" type="button" data-promotion-prev>← Назад</button><span class="gc-help-text" data-promotion-page>Страница 1</span><button class="btn btn-outline-secondary btn-sm" type="button" data-promotion-next>Далее →</button></div>
                <div class="gc-dialog-actions"><span class="gc-help-text" data-promotion-staged-count aria-live="polite"></span><button class="btn btn-outline-secondary btn-sm" type="button" data-promotion-cancel>Отмена</button><button class="btn btn-primary btn-sm" type="button" data-promotion-apply>Выбрать</button></div>
            </dialog>
        </div>
    </div>
</section>
