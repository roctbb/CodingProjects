<div class="gc-stack">
    @foreach($people->groupBy(fn ($person) => $person->birthday->format('m-d')) as $group)
        @php
            $firstPerson = $group->first();
            $distance = $firstPerson->birthday_distance_days;
            $isToday = $distance !== null && $distance == 0;
            $dayLabel = $isToday ? 'Сегодня поздравляем' : ($distance == 1 ? 'Завтра' : 'через '.$distance.' дн.');
        @endphp
        <section class="gc-date-group {{ $isToday ? 'is-today' : '' }}">
            <h3 class="gc-date-group__title"><span>{{ $dayLabel }}</span><time datetime="{{ $firstPerson->next_birthday_at?->format('Y-m-d') ?? $firstPerson->birthday->format('Y-m-d') }}">{{ $firstPerson->birthday->format('d.m') }}</time></h3>
            <ul class="list-unstyled gc-name-list mb-0">
                @foreach($group as $person)
                    <li>
                        <a href="{{ url('/insider/profile/'.$person->id) }}">
                            <span>{{ $person->name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</div>
