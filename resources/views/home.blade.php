@extends('layouts.left-menu')

@section('title', 'Мои курсы')

@section('content')
    @php
        $isTeacher = $user->role == 'teacher' || $user->role == 'admin';
        $activeCourses = $my_courses->where('state', 'started');
        $draftCourses = $courses->where('state', 'draft')->filter(function ($course) use ($user) {
            return $user->role == 'admin' || $course->teachers->contains($user);
        });
        $archiveCourses = $courses->where('state', 'ended')->filter(function ($course) use ($user) {
            return $user->role == 'admin' || $course->teachers->contains($user);
        })->sortByDesc('start_date');
        $availableCourses = $open_courses->merge($private_courses);
        $birthdayUsers = $users->where('birthday', '!=', null)->filter(function ($birthdayUser) {
            return $birthdayUser->birthday->month == now()->month
                && $birthdayUser->birthday->day > now()->day - 10
                && $birthdayUser->birthday->day < now()->day + 10;
        })->sortBy(fn($birthdayUser) => $birthdayUser->birthday->day)->values();
    @endphp

    @if($user->isBirthday())
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <h5 class="mb-0">
                <img src="{{ url('images/icons/icons8-confetti-48.png') }}" height="24" alt="">
                С днем рождения!!!
                <img src="{{ url('images/icons/icons8-confetti-48.png') }}" height="24" alt="">
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
        </div>
    @endif

    <div class="courses-page-heading courses-toolbar mb-3">
        <div class="min-width-0">
            <span class="courses-page-eyebrow">workspace</span>
            <h2 class="mb-1">Мои курсы</h2>
            @unless($isTeacher)
                <div class="courses-inline-stats">
                    <span><strong>{{ $activeCourses->count() }}</strong> активных</span>
                    <span><strong>{{ $open_courses->count() }}</strong> открытых</span>
                    <span><strong>{{ $private_courses->count() }}</strong> по инвайту</span>
                </div>
            @endunless
        </div>

        @if ($isTeacher)
            <a class="btn btn-success courses-create-btn" href="{{ url('/insider/courses/create/') }}"><i class="fas fa-plus me-1"></i>Создать курс</a>
        @else
            <form autocomplete="off" class="courses-invite-form gc-card" method="get" action="{{ url('insider/invite') }}">
                @csrf
                <i class="fas fa-ticket-alt text-muted"></i>
                <input type="text" class="form-control border-0 shadow-none" name="invite" placeholder="Введите инвайт на курс...">
                <button type="submit" class="btn btn-primary text-nowrap">Добавить</button>
            </form>
        @endif
    </div>

    @if ($isTeacher)
        <div class="courses-tabs-row mb-3">
            <ul class="nav nav-pills courses-tabs" id="coursesTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                        Активные <span class="badge bg-white text-primary ms-1">{{ $activeCourses->count() }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="draft-tab" data-bs-toggle="tab" data-bs-target="#draft" type="button" role="tab">
                        Черновики <span class="badge bg-white text-muted ms-1">{{ $draftCourses->count() }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="archive-tab" data-bs-toggle="tab" data-bs-target="#archive" type="button" role="tab">
                        Архив <span class="badge bg-white text-muted ms-1">{{ $archiveCourses->count() }}</span>
                    </button>
                </li>
            </ul>
        </div>
    @endif

    <div class="tab-content" id="courses">
        <div class="tab-pane fade show active" id="active" role="tabpanel">
            <div class="row g-3">
                <div class="col-12 col-xl-9">
                    @foreach($notifications as $notification)
                        <div class="alert alert-{{ $notification->data['type'] }} alert-dismissible fade show" role="alert">
                            {!! $notification->data['text'] !!}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
                        </div>
                    @endforeach

                    @if($isTeacher && $pendingSolutions->count())
                        <div class="courses-workbench courses-workbench--review gc-card mb-3">
                            <div class="courses-workbench__header">
                                <span class="courses-workbench__icon"><i class="fas fa-code-branch"></i></span>
                                <div class="min-width-0">
                                    <span class="courses-workbench__eyebrow">Очередь проверки</span>
                                    <h5 class="mb-1">Нужно проверить</h5>
                                    <p class="mb-0">Последние отправленные решения по вашим активным курсам.</p>
                                </div>
                                <span class="courses-workbench__count">{{ $pendingSolutions->count() }}</span>
                            </div>
                            <div class="courses-workbench-list">
                                @foreach($pendingSolutions as $solution)
                                    <a href="{{ url('insider/courses/'.$solution->course_id.'/tasks/'.$solution->task_id.'/student/'.$solution->user_id.'#solution-'.$solution->id) }}" class="courses-workbench-item text-decoration-none">
                                        <img src="{{ $solution->user->imageUrl() }}" class="avatar sm" alt="">
                                        <span class="min-width-0">
                                            <strong class="text-truncate">{{ $solution->user->name }}</strong>
                                            <small class="text-muted text-truncate">{{ $solution->task->name ?? 'Задача' }} · {{ $solution->course->name }}</small>
                                        </span>
                                        <small class="courses-workbench-item__date text-nowrap"><i class="far fa-clock"></i>{{ $solution->submitted->format('d.m') }}</small>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @elseif(!$isTeacher && ($upcomingDeadlines->count() || $courseProgress->count()))
                        <div class="courses-workbench courses-workbench--today gc-card mb-3">
                            <div class="courses-workbench__header">
                                <span class="courses-workbench__icon"><i class="fas fa-terminal"></i></span>
                                <div class="min-width-0">
                                    <span class="courses-workbench__eyebrow">Сегодня</span>
                                    <h5 class="mb-1">Что дальше</h5>
                                    <p class="mb-0">Ближайшие сроки и общий прогресс по активным курсам.</p>
                                </div>
                            </div>
                            <div class="courses-workbench-grid">
                                <div>
                                    <div class="courses-workbench-label">Ближайшие дедлайны</div>
                                    <div class="courses-workbench-list">
                                        @forelse($upcomingDeadlines as $deadline)
                                            <a href="{{ url('insider/courses/'.$deadline->course_id) }}" class="courses-workbench-item text-decoration-none">
                                                <span class="courses-workbench-date {{ $deadline->expiration->isToday() ? 'is-today' : '' }}">{{ $deadline->expiration->format('d.m') }}</span>
                                                <span class="min-width-0">
                                                    <strong class="text-truncate">{{ $deadline->task->name ?? 'Задача' }}</strong>
                                                    <small class="text-muted text-truncate">{{ $deadline->course->name }}</small>
                                                </span>
                                            </a>
                                        @empty
                                            <div class="courses-workbench-muted">Ближайших дедлайнов нет.</div>
                                        @endforelse
                                    </div>
                                </div>
                                <div>
                                    <div class="courses-workbench-label">Прогресс</div>
                                    <div class="courses-workbench-list">
                                        @foreach($activeCourses->take(3) as $course)
                                            @php $progress = $courseProgress->get($course->id); @endphp
                                            <a href="{{ url('insider/courses/'.$course->id) }}" class="courses-workbench-progress text-decoration-none">
                                                <span class="text-truncate">{{ $course->name }}</span>
                                                <strong>{{ $progress ? round($progress->percent) : 0 }}%</strong>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($activeCourses->count())
                        <div class="courses-card-grid {{ $isTeacher ? 'courses-card-grid--teacher' : '' }}">
                            @foreach($activeCourses as $course)
                                @php
                                    $progress = $courseProgress->get($course->id);
                                    $percent = $course->students->contains($user) ? round(optional($progress)->percent ?? 0) : null;
                                @endphp
                                <a href="{{ url('insider/courses/'.$course->id) }}" class="course-index-card gc-card text-decoration-none">
                                    <div class="course-index-card__body">
                                        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                            <h6 class="course-index-card__title mb-0">{{ $course->name }}</h6>
                                        </div>
                                        <p class="course-index-card__description">{{ \Illuminate\Support\Str::limit($course->description, 90) }}</p>
                                        <div class="course-index-card__spacer"></div>
                                        @if ($percent !== null)
                                            <div class="course-index-card__progress">
                                                <div class="d-flex align-items-center justify-content-between mb-1">
                                                    <small class="text-muted">Прогресс</small>
                                                    <small class="text-muted">
                                                        {{ $percent }}%
                                                        @if($progress && $progress->max_points)
                                                            · {{ $progress->points }}/{{ $progress->max_points }} XP
                                                        @endif
                                                    </small>
                                                </div>
                                                <x-gc-progress :percent="$percent" height="5px" />
                                            </div>
                                        @endif
                                        @if ($course->teachers->count())
                                            <div class="course-index-card__footer course-index-card__footer--split">
                                                <div class="course-index-card__teachers">
                                                    @foreach($course->teachers->take(3) as $teacher)
                                                        <img src="{{ $teacher->imageUrl() }}" class="avatar sm" title="{{ $teacher->name }}" alt="">
                                                    @endforeach
                                                    <span>{{ $course->teachers->first()->name }}</span>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="courses-empty-state gc-card">
                            <div class="courses-empty-state__icon"><i class="fas fa-rocket"></i></div>
                            <h5>Вы пока не записаны на курсы</h5>
                            <p>Введите инвайт от преподавателя или выберите открытый курс ниже.</p>
                        </div>
                    @endif

                    @if(!$isTeacher && $availableCourses->count())
                        <div class="mt-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h5 class="mb-0">Доступные курсы</h5>
                                <span class="text-muted small">{{ $availableCourses->count() }} всего</span>
                            </div>
                            <div class="courses-card-grid courses-card-grid--compact">
                                @foreach($availableCourses as $course)
                                    <div class="course-index-card gc-card course-index-card--available">
                                        <div class="course-index-card__body">
                                            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                                <h6 class="course-index-card__title mb-0">{{ $course->name }}</h6>
                                            </div>
                                            <p class="course-index-card__description">{{ \Illuminate\Support\Str::limit($course->description, 90) }}</p>
                                            <div class="course-index-card__spacer"></div>
                                            <div class="course-index-card__footer course-index-card__footer--split">
                                                <div class="course-index-card__teachers">
                                                    @if ($course->teachers->count())
                                                        @foreach($course->teachers->take(3) as $teacher)
                                                            <img src="{{ $teacher->imageUrl() }}" class="avatar sm" title="{{ $teacher->name }}" alt="">
                                                        @endforeach
                                                        <span>{{ $course->teachers->first()->name }}</span>
                                                    @else
                                                        <i class="fas fa-user-graduate"></i><span>Преподаватель не указан</span>
                                                    @endif
                                                </div>
                                                @if($course->mode == 'open')
                                                    <a href="{{ url('insider/courses/'.$course->id.'/enroll') }}" class="btn btn-primary btn-sm course-index-card__action">Записаться</a>
                                                @else
                                                    <span class="course-index-card__action course-index-card__action--muted">По инвайту</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="col-12 col-xl-3">
                    <div class="card gc-card courses-side-panel courses-profile-summary courses-profile-summary--compact">
                        <div class="card-body">
                            <div class="courses-side-section courses-side-section--profile">
                                <div class="courses-profile-head">
                                    <div class="avatar-ring courses-profile-avatar">
                                        <img src="{{ $user->imageUrl() }}" class="avatar lg" alt="">
                                    </div>
                                    <div class="courses-profile-identity min-width-0">
                                        <h6 class="mb-1 text-truncate"><a href="{{ url('/insider/profile/'.$user->id) }}" class="text-decoration-none">{{ $user->name }}</a></h6>
                                        <div class="courses-profile-badges">
                                            <span class="badge bg-success"><i class="fas fa-arrow-up me-1"></i>{{ $user->rank()->name }}</span>
                                            @if ($user->is_trainee)
                                                <span class="badge bg-info">Стажер</span>
                                            @endif
                                            @if ($user->is_teacher)
                                                <span class="badge bg-info">Преподаватель</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="courses-profile-balance">
                                    <img src="{{ url('images/icons/icons8-coins-48.png') }}" class="courses-coin-icon" alt="">
                                    <strong>{{ $user->balance() }}</strong>
                                    <span>GC</span>
                                </div>

                                <div class="courses-profile-xp">
                                    <x-gc-progress :percent="100*($user->score()-$user->rank()->from)/($user->rank()->to-$user->rank()->from)" height="6px" />
                                    <small>{{ $user->score() }} / {{ $user->rank()->to }} XP</small>
                                </div>

                                <div class="courses-profile-school">
                                    <span>Учеба</span>
                                    <strong>{{ $user->school }}, {{ $user->grade() }} класс</strong>
                                </div>

                                <a href="{{ url('/insider/profile/'.$user->id) }}" class="courses-profile-link text-decoration-none">Смотреть профиль <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>

                            <div class="courses-side-section">
                                <div class="courses-birthday-title">
                                    <span class="courses-birthday-title__icon"><i class="fas fa-cake-candles"></i></span>
                                    <div>
                                        <h6 class="card-title mb-0">Дни рождения</h6>
                                        <small class="text-muted">Ближайшие даты</small>
                                    </div>
                                </div>
                                <ul class="list-unstyled mb-0 courses-birthday-list">
                                    @forelse($birthdayUsers->take(8) as $buser)
                                        @php
                                            $isBirthdayToday = $buser->birthday->month === now()->month && $buser->birthday->day === now()->day;
                                        @endphp
                                        <li class="courses-birthday-row {{ $isBirthdayToday ? 'is-today' : '' }}">
                                            <span class="courses-birthday-date {{ $isBirthdayToday ? 'is-today' : '' }}">
                                                {{ $buser->birthday->format('d.m') }}
                                            </span>
                                            <span class="courses-birthday-person min-width-0">
                                                <a class="text-decoration-none text-truncate {{ $isBirthdayToday ? 'fw-bold' : '' }}" href="{{ url('insider/profile/'.$buser->id) }}">{{ $buser->name }}</a>
                                                @if($isBirthdayToday)
                                                    <small>Сегодня</small>
                                                @endif
                                            </span>
                                        </li>
                                    @empty
                                        <li class="text-muted">Пока нет ближайших дней рождения.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($isTeacher)
            <div class="tab-pane fade" id="draft" role="tabpanel">
                @if($draftCourses->count())
                    <div class="courses-card-grid courses-card-grid--teacher courses-card-grid--compact mt-2">
                        @foreach($draftCourses as $course)
                            <a href="{{ url('insider/courses/'.$course->id) }}" class="course-index-card gc-card text-decoration-none">
                                <div class="course-index-card__body">
                                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                        <h6 class="course-index-card__title mb-0">{{ $course->name }}</h6>
                                    </div>
                                    <p class="course-index-card__description">{{ \Illuminate\Support\Str::limit($course->description, 90) }}</p>
                                    <div class="course-index-card__spacer"></div>
                                    @if($course->teachers->count())
                                        <div class="course-index-card__footer course-index-card__footer--split">
                                            <div class="course-index-card__teachers">
                                                @foreach($course->teachers->take(3) as $teacher)
                                                    <img src="{{ $teacher->imageUrl() }}" class="avatar sm" title="{{ $teacher->name }}" alt="">
                                                @endforeach
                                                <span>{{ $course->teachers->first()->name }}</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="courses-empty-state gc-card mt-2">
                        <div class="courses-empty-state__icon"><i class="fas fa-pen-ruler"></i></div>
                        <h5>Черновиков нет</h5>
                        <p>Создайте курс, чтобы подготовить программу до запуска.</p>
                        <a class="btn btn-success" href="{{ url('/insider/courses/create/') }}">Создать курс</a>
                    </div>
                @endif
            </div>

            <div class="tab-pane fade" id="archive" role="tabpanel">
                @if($archiveCourses->count())
                    <div class="courses-card-grid courses-card-grid--teacher courses-card-grid--compact mt-2">
                        @foreach($archiveCourses as $course)
                            <a href="{{ url('insider/courses/'.$course->id) }}" class="course-index-card gc-card text-decoration-none">
                                <div class="course-index-card__body">
                                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                        <h6 class="course-index-card__title mb-0">{{ $course->name }}</h6>
                                    </div>
                                    <p class="course-index-card__description">{{ \Illuminate\Support\Str::limit($course->description, 90) }}</p>
                                    <div class="course-index-card__spacer"></div>
                                    <div class="course-index-card__footer course-index-card__footer--split">
                                        <div class="course-index-card__meta">
                                            <i class="far fa-calendar-alt"></i>
                                            <span>
                                                @if ($course->start_date && $course->end_date)
                                                    {{ $course->start_date->format('d.m.Y') }} - {{ $course->end_date->format('d.m.Y') }}
                                                @elseif ($course->start_date)
                                                    с {{ $course->start_date->format('d.m.Y') }}
                                                @elseif ($course->end_date)
                                                    до {{ $course->end_date->format('d.m.Y') }}
                                                @else
                                                    Даты не указаны
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="courses-empty-state gc-card mt-2">
                        <div class="courses-empty-state__icon"><i class="fas fa-box-archive"></i></div>
                        <h5>Архив пуст</h5>
                        <p>Завершенные курсы появятся здесь.</p>
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection
