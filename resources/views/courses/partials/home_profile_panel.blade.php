<aside class="gc-stack gc-sticky-panel" aria-label="Личный обзор">
    <section class="gc-card gc-home-profile">
        <a class="gc-home-profile__portrait" href="{{ url('/insider/profile/'.$user->id) }}" aria-label="Открыть свой профиль">
            <x-gc-avatar :user="$user" :frame="false" alt="" loading="lazy" />
        </a>
        <div class="gc-panel-body">
            @if($customTitle = $user->activeCustomTitle())
                <div class="gc-help-text mb-2">{{ $customTitle }}</div>
            @endif
            <div class="gc-summary-line">
                <span class="gc-meta-label">Баланс</span>
                <a href="{{ url('/insider/market#market-history') }}" class="gc-amount gc-wallet-link text-decoration-none"><i class="fas fa-coins gc-currency-icon" aria-hidden="true"></i>{{ $balance }} GC</a>
            </div>
            <section class="mt-3" aria-label="Опыт и ранг">
                <div class="gc-summary-line">
                    <span class="gc-meta-label">Опыт · {{ $rank->name }}</span>
                    <span class="gc-amount">{{ $score }} XP</span>
                </div>
                <div class="mt-2" title="Прогресс к следующему рангу"><x-gc-progress :percent="$rankProgress" height="5px" /></div>
            </section>
        </div>
    </section>

    <x-gc-panel class="gc-panel--occasion" aria-label="Дни рождения в ближайшие семь дней">
        <x-slot:heading>
            <div class="gc-occasion-heading">
                <span class="gc-occasion-icon" aria-hidden="true"><i class="fas fa-cake-candles"></i></span>
                <div>
                    <h2>Дни рождения</h2>
                    <p class="gc-help-text mb-0">Ближайшие 7 дней</p>
                </div>
            </div>
        </x-slot:heading>
        @if($birthdayUsers->isNotEmpty())
            @include('courses.partials.birthday_rows', ['people' => $birthdayUsers])
        @else
            <p class="gc-help-text mb-0">В ближайшие 7 дней именинников нет.</p>
        @endif
    </x-gc-panel>
</aside>
