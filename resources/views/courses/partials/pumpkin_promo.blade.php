@php($storeSettings = app(\App\Services\SystemStoreSettings::class))
@if($storeSettings->promotionActive())
    <section class="gc-card p-3 p-md-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3"
             style="background: linear-gradient(115deg, #fff1ce, #ffdab0); color: #45230f; border: 1px solid #e8aa55;" aria-labelledby="pumpkin-promo-title">
        <div>
            <h3 id="pumpkin-promo-title" class="mb-1">🎃 Тыквенный спас 2026!</h3>
            <p class="mb-1">Скидка 50% на генерацию персонажа:
                <s>{{ $storeSettings->price('room_character') }} GC</s>
                <strong>{{ $storeSettings->roomCharacterPrice() }} GC</strong>.
            </p>
            @if($storeSettings->all()['pumpkin_promo']['ends_at'])
                <div class="small">До {{ \Carbon\Carbon::parse($storeSettings->all()['pumpkin_promo']['ends_at'], 'Europe/Moscow')->format('d.m.Y H:i') }} МСК.</div>
            @endif
        </div>
        <a class="btn btn-dark rounded-3 px-4" href="{{ url('/insider/profile/'.Auth::id().'#learning-avatar') }}">Создать персонажа</a>
    </section>
@endif
