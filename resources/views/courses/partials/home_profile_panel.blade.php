<div class="col-12 col-xl-3">
    <div class="gc-card overflow-hidden sticky-xl-top">
        <div class="p-0">
            <div class="p-3">
                <div class="home-profile-head">
                    <x-gc-avatar :user="$user" size="lg" class="home-profile-head__avatar" alt="" />
                    <div class="home-profile-head__body">
                        <h6 class="home-profile-head__title"><a href="{{ url('/insider/profile/'.$user->id) }}" class="text-decoration-none">{{ $user->name }}</a></h6>
                        <div class="home-profile-head__badges">
                            @include('profile.partials.custom_title_badge', ['profileUser' => $user, 'compact' => true])
                            <span class="badge rounded-pill bg-body-tertiary"><i class="fas fa-arrow-up me-1"></i>{{ $rank->name }}</span>
                            @if ($user->is_trainee)
                                <span class="badge rounded-pill bg-body-tertiary">Стажер</span>
                            @endif
                            @if ($user->is_teacher)
                                <span class="badge rounded-pill bg-body-tertiary">Преподаватель</span>
                            @endif
                        </div>
                    </div>
                    <a href="{{ url('/insider/profile/'.$user->id) }}" class="btn btn-outline-secondary btn-sm rounded-3 gc-icon-button home-profile-open" aria-label="Открыть профиль"><i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="row g-2 border-top pt-3 mb-3">
                    <div class="col-6">
                        <div class="gc-info-tile h-100">
                            <span class="text-muted small d-block mb-1">Монеты</span>
                            <div class="d-flex align-items-center gap-1">
                                <img src="{{ url('images/icons/icons8-coins-48.png') }}" width="18" height="18" alt="">
                                <strong class="lh-1">{{ $balance }}</strong>
                                <span class="text-muted small">GC</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="gc-info-tile h-100">
                            <span class="text-muted small d-block mb-1">Опыт</span>
                            <div class="d-flex align-items-baseline gap-1">
                                <strong class="lh-1">{{ $score }}</strong>
                                <span class="text-muted small">XP</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                        <span class="text-muted small">До следующего ранга</span>
                        <span class="text-muted small">{{ $rankProgress }}%</span>
                    </div>
                    <x-gc-progress :percent="$rankProgress" height="6px" />
                    <small class="text-muted d-block mt-1">{{ $score }} / {{ $rank->to }} XP</small>
                </div>

                @if($user->school || $user->grade_year)
                    <div class="border-top pt-3 mt-2">
                        <span class="gc-eyebrow">Учеба</span>
                        <strong class="fw-medium small d-block lh-sm">
                            {{ $user->school ?: 'Школа не указана' }}@if($user->grade_year), {{ $user->grade() }} класс@endif
                        </strong>
                    </div>
                @else
                    <div class="border-top pt-3 mt-2">
                        <span class="gc-eyebrow">Профиль</span>
                        <strong class="fw-medium small d-block lh-sm">
                            {{ $user->role == 'admin' ? 'Администратор' : ($isTeacher ? 'Преподаватель' : 'Ученик') }}
                        </strong>
                    </div>
                @endif
            </div>

            <div class="p-3 border-top">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2 min-width-0">
                        <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-cake-candles"></i></span>
                        <div class="min-width-0">
                            <h6 class="mb-0">Дни рождения</h6>
                            <small class="text-muted">Ближайшие даты</small>
                        </div>
                    </div>
                    <span class="badge rounded-pill bg-body-tertiary">{{ $birthdayUsers->count() }}</span>
                </div>
                <ul class="list-unstyled d-flex flex-column gap-2 mb-0">
                    @forelse($birthdayUsers->take(8) as $buser)
                        @php
                            $birthdayDistance = $buser->birthday_distance_days ?? null;
                            $isBirthdayToday = $birthdayDistance == 0;
                            $birthdayLabel = $isBirthdayToday
                                ? 'Сегодня'
                                : ($birthdayDistance == 1 ? 'Завтра' : 'через '.$birthdayDistance.' дн.');
                        @endphp
                        <li class="home-birthday-item @if($isBirthdayToday) is-today @endif">
                            <span class="badge rounded-pill home-birthday-date">
                                {{ $buser->birthday->format('d.m') }}
                            </span>
                            <span class="d-flex align-items-center justify-content-between gap-2 min-width-0 flex-grow-1">
                                <a class="text-decoration-none {{ $isBirthdayToday ? 'fw-bold' : 'text-body' }} d-inline-flex align-items-center gap-1 min-width-0" href="{{ url('insider/profile/'.$buser->id) }}">
                                    <span class="text-truncate">{{ $buser->name }}</span>
                                    @include('profile.partials.custom_title_badge', ['profileUser' => $buser, 'compact' => true])
                                </a>
                                <small class="{{ $isBirthdayToday ? 'text-warning-emphasis fw-bold' : 'text-muted' }} flex-shrink-0">{{ $birthdayLabel }}</small>
                            </span>
                        </li>
                    @empty
                        <li class="text-center text-muted bg-body-tertiary rounded-3 px-3 py-3">Пока нет ближайших дней рождения.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

</div>
