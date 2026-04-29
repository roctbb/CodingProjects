<md-outlined-card class="md-surface md-birthdays md-surface-card">
    @php
        $now = \Carbon\Carbon::now();
        $todayBirthdays = $upcomingBirthdays->filter(function ($buser) use ($now) {
            return $buser->birthday->month == $now->month && $buser->birthday->day == $now->day;
        })->values();
        $futureBirthdays = $upcomingBirthdays->filter(function ($buser) use ($now) {
            if ($buser->birthday->month > $now->month) {
                return true;
            }
            if ($buser->birthday->month < $now->month) {
                return false;
            }
            return $buser->birthday->day > $now->day;
        })->values();
    @endphp

    <header class="md-birthdays__head">
        <div class="md-birthdays__title-wrap">
            <i class="md-birthdays__title-icon fas fa-birthday-cake" aria-hidden="true"></i>
            <h2 class="md-birthdays__title">Дни рождения</h2>
            <p class="md-birthdays__subtitle">Сегодня и ближайшие даты</p>
        </div>
        <md-assist-chip class="md-birthdays__head-chip" label="{{ $upcomingBirthdays->count() }}"></md-assist-chip>
    </header>

    @if ($todayBirthdays->count() || $futureBirthdays->count())
        @if ($todayBirthdays->count())
            <section class="md-birthdays__group" aria-label="Дни рождения сегодня">
                <header class="md-birthdays__group-head">
                    <h3 class="md-birthdays__group-title">Сегодня</h3>
                    <md-assist-chip class="md-birthdays__group-chip" label="{{ $todayBirthdays->count() }}"></md-assist-chip>
                </header>
                <ul class="md-birthdays__list" aria-label="Список дней рождения сегодня" role="list">
                    @foreach($todayBirthdays as $buser)
                        <li class="md-birthdays__item md-birthdays__item--today">
                            <a class="md-birthday-name" href="{{ url('insider/profile/'.$buser->id) }}">{{ $buser->name }}</a>
                            <span class="md-birthdays__item-end">
                                <md-assist-chip class="md-birthdays__date-chip" label="{{ $buser->birthday->format('d.m') }}"></md-assist-chip>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($futureBirthdays->count())
            <section class="md-birthdays__group" aria-label="Ближайшие дни рождения">
                <header class="md-birthdays__group-head">
                    <h3 class="md-birthdays__group-title">Ближайшие</h3>
                    <md-assist-chip class="md-birthdays__group-chip" label="{{ $futureBirthdays->count() }}"></md-assist-chip>
                </header>
                <ul class="md-birthdays__list" aria-label="Список ближайших дней рождения" role="list">
                    @foreach($futureBirthdays as $buser)
                        <li class="md-birthdays__item">
                            <a class="md-birthday-name" href="{{ url('insider/profile/'.$buser->id) }}">{{ $buser->name }}</a>
                            <span class="md-birthdays__item-end">
                                <md-assist-chip class="md-birthdays__date-chip" label="{{ $buser->birthday->format('d.m') }}"></md-assist-chip>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @else
        <p class="md-empty-note">Сегодня и в ближайшие дни дней рождения нет.</p>
    @endif
</md-outlined-card>
