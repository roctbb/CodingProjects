@extends('layouts.left-menu')

@section('title', 'Мои курсы')

@section('content')
    @php
        $isTeacher = $isTeacher ?? ($user->role == 'teacher' || $user->role == 'admin');
        $activeCourses = $activeCourses ?? $my_courses->where('state', 'started');
        $draftCourses = $draftCourses ?? collect();
        $archiveCourses = $archiveCourses ?? collect();
        $availableCourses = $availableCourses ?? $open_courses->merge($private_courses);
        $birthdayUsers = $birthdayUsers ?? collect();
        $activeProgressPercents = $activeCourses->map(fn($course) => optional($courseProgress->get($course->id))->percent)->filter(fn($percent) => $percent !== null);
        $averageProgress = $activeProgressPercents->count() ? round($activeProgressPercents->avg()) : 0;
        $todayDeadlines = $upcomingDeadlines->filter(fn($deadline) => $deadline->expiration->isToday())->count();
        $rank = $user->rank();
        $score = $user->score();
        $balance = $user->balance();
        $rankProgress = min(100, max(0, round(100 * ($score - $rank->from) / max(1, $rank->to - $rank->from))));
    @endphp

    @if($user->isBirthday())
        <div class="gc-card border p-3 mb-3 alert-dismissible fade show position-relative" role="alert">
            <h5 class="mb-0 pe-4">
                <img src="{{ url('images/icons/icons8-confetti-48.png') }}" height="24" alt="">
                С днем рождения!!!
                <img src="{{ url('images/icons/icons8-confetti-48.png') }}" height="24" alt="">
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
        </div>
    @endif

    <div class="gc-title-row gc-title-row--center">
        <div class="min-width-0">
            <span class="text-muted text-uppercase fw-bold font-monospace small d-block mb-1">workspace</span>
            <h2 class="mb-1">Мои курсы</h2>
            @unless($isTeacher)
                <div class="d-flex flex-wrap gap-2 text-muted small">
                    <span><strong>{{ $activeCourses->count() }}</strong> активных</span>
                    <span><strong>{{ $open_courses->count() }}</strong> открытых</span>
                    <span><strong>{{ $private_courses->count() }}</strong> по инвайту</span>
                </div>
            @endunless
        </div>

        @if ($isTeacher)
            <a class="btn btn-success rounded-3 fw-semibold px-3 py-2" href="{{ url('/insider/courses/create/') }}"><i class="fas fa-plus me-1"></i>Создать курс</a>
        @else
            <form autocomplete="off" class="gc-card gc-invite-form" method="get" action="{{ url('insider/invite') }}">
                @csrf
                <i class="fas fa-ticket-alt text-muted d-none d-sm-inline"></i>
                <input type="text" class="form-control rounded-3" name="invite" placeholder="Введите инвайт на курс...">
                <button type="submit" class="btn btn-success rounded-3 fw-semibold text-nowrap">Добавить</button>
            </form>
        @endif
    </div>

    @unless($isTeacher)
        <div class="row row-cols-2 row-cols-lg-4 g-2 g-md-3 mb-4">
            <div class="col">
                <div class="gc-card gc-metric-card">
                    <span class="gc-eyebrow mb-2">Активные</span>
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <strong class="fs-3 lh-1">{{ $activeCourses->count() }}</strong>
                        <span class="gc-icon-tile"><i class="fas fa-layer-group"></i></span>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="gc-card gc-metric-card">
                    <span class="gc-eyebrow mb-2">Сегодня</span>
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <strong class="fs-3 lh-1">{{ $todayDeadlines }}</strong>
                        <span class="gc-icon-tile"><i class="far fa-clock"></i></span>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="gc-card gc-metric-card">
                    <span class="gc-eyebrow mb-2">Прогресс</span>
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <strong class="fs-3 lh-1">{{ $averageProgress }}%</strong>
                        <span class="gc-icon-tile"><i class="fas fa-chart-line"></i></span>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="gc-card gc-metric-card">
                    <span class="gc-eyebrow mb-2">Доступно</span>
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <strong class="fs-3 lh-1">{{ $availableCourses->count() }}</strong>
                        <span class="gc-icon-tile"><i class="fas fa-unlock-alt"></i></span>
                    </div>
                </div>
            </div>
        </div>
    @endunless

    @if ($isTeacher)
        <div class="d-flex align-items-center justify-content-between mb-3">
            <ul class="nav nav-pills gc-segmented-tabs" id="coursesTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link fw-semibold text-nowrap rounded-3 px-2 px-sm-3 py-2 small active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                        Активные <span class="badge rounded-pill bg-body gc-tab-count">{{ $activeCourses->count() }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold text-nowrap rounded-3 px-2 px-sm-3 py-2 small" id="draft-tab" data-bs-toggle="tab" data-bs-target="#draft" type="button" role="tab">
                        Черновики <span class="badge rounded-pill bg-body gc-tab-count">{{ $draftCourses->count() }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold text-nowrap rounded-3 px-2 px-sm-3 py-2 small" id="archive-tab" data-bs-toggle="tab" data-bs-target="#archive" type="button" role="tab">
                        Архив <span class="badge rounded-pill bg-body gc-tab-count">{{ $archiveCourses->count() }}</span>
                    </button>
                </li>
            </ul>
        </div>
    @endif

    <div class="tab-content" id="courses">
        <div class="tab-pane fade show active" id="active" role="tabpanel">
            <div class="row g-4">
                <div class="col-12 col-xl-9">
                    @foreach($notifications as $notification)
                        @php
                            $notificationType = in_array($notification->data['type'] ?? 'info', ['success', 'warning', 'danger', 'info']) ? $notification->data['type'] : 'info';
                            $notificationIcon = [
                                'success' => 'fas fa-check',
                                'warning' => 'fas fa-exclamation',
                                'danger' => 'fas fa-times',
                                'info' => 'fas fa-info',
                            ][$notificationType];
                            $notificationIcon = $notification->data['icon'] ?? $notificationIcon;
                        @endphp
                        <div class="gc-card gc-alert-row home-notification home-notification--{{ $notificationType }} alert-dismissible fade show" role="alert">
                            <span class="home-notification__icon"><i class="{{ $notificationIcon }}"></i></span>
                            <div class="min-width-0 flex-grow-1">
                                {!! clean($notification->data['text'] ?? '') !!}
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
                        </div>
                    @endforeach

                    @if($isTeacher && $pendingSolutionsTotal)
                        @php
                            $pendingSolutionsMod10 = $pendingSolutionsTotal % 10;
                            $pendingSolutionsMod100 = $pendingSolutionsTotal % 100;
                            $pendingSolutionsWord = ($pendingSolutionsMod10 == 1 && $pendingSolutionsMod100 != 11)
                                ? 'решение'
                                : (in_array($pendingSolutionsMod10, [2, 3, 4]) && !in_array($pendingSolutionsMod100, [12, 13, 14]) ? 'решения' : 'решений');
                        @endphp
                        <a class="gc-card home-review-strip home-review-notice" href="{{ url('/insider/reviews') }}" aria-label="Открыть список непроверенных решений">
                            <span class="home-review-head">
                                <span class="home-review-icon"><i class="fas fa-code-branch"></i></span>
                                <span class="min-width-0">
                                    <span class="home-review-eyebrow">Ожидают проверки</span>
                                    <span class="home-review-label">{{ $pendingSolutionsTotal }} {{ $pendingSolutionsWord }}</span>
                                </span>
                            </span>
                            <span class="home-review-notice__action">
                                <span class="home-card-arrow"><i class="fas fa-arrow-right"></i></span>
                            </span>
                        </a>
                    @elseif(!$isTeacher && ($upcomingDeadlines->count() || $courseProgress->count()))
                        <div class="gc-card home-next-card overflow-hidden mb-4">
                            <div class="gc-section-header gc-section-header--inline">
                                <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-terminal"></i></span>
                                <div class="min-width-0">
                                    <span class="gc-eyebrow">Сегодня</span>
                                    <h5 class="mb-1">Что дальше</h5>
                                    <p class="mb-0 text-muted small">Ближайшие сроки и общий прогресс по активным курсам.</p>
                                </div>
                            </div>
                            <div class="row g-3 p-3">
                                <div class="col-12 col-lg-7">
                                    <div class="gc-eyebrow mb-2">Ближайшие дедлайны</div>
                                    <div class="d-flex flex-column gap-2">
                                        @forelse($upcomingDeadlines as $deadline)
                                            <a href="{{ url('insider/courses/'.$deadline->course_id) }}" class="home-next-link">
                                                <span class="badge rounded-pill home-next-date @if($deadline->expiration->isToday()) is-today @endif">{{ $deadline->expiration->format('d.m') }}</span>
                                                <span class="min-width-0 flex-grow-1">
                                                    <strong class="d-block text-body text-truncate">{{ $deadline->task->name ?? 'Задача' }}</strong>
                                                    <small class="text-muted d-block text-truncate">{{ $deadline->course->name }}</small>
                                                </span>
                                            </a>
                                        @empty
                                            <div class="text-muted small">Ближайших дедлайнов нет.</div>
                                        @endforelse
                                    </div>
                                </div>
                                <div class="col-12 col-lg-5">
                                    <div class="gc-eyebrow mb-2">Прогресс</div>
                                    <div class="d-flex flex-column gap-2">
                                        @foreach($activeCourses->take(3) as $course)
                                            @php $progress = $courseProgress->get($course->id); @endphp
                                            <a href="{{ url('insider/courses/'.$course->id) }}" class="home-next-link home-next-link--spread">
                                                <span class="text-body text-truncate">{{ $course->name }}</span>
                                                <strong class="text-primary flex-shrink-0">{{ $progress ? round($progress->percent) : 0 }}%</strong>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @include('courses.partials.home_activity_feed')

                    @if ($activeCourses->count())
                        <div class="row row-cols-1 row-cols-md-2 {{ $isTeacher ? 'row-cols-xxl-3' : 'row-cols-xl-3' }} g-3">
                            @foreach($activeCourses as $course)
                                @include('courses.partials.home_course_card', ['variant' => 'active', 'showProgress' => true])
                            @endforeach
                        </div>
                    @else
                        @include('courses.partials.home_empty_state', [
                            'icon' => 'fas fa-rocket',
                            'title' => 'Вы пока не записаны на курсы',
                            'text' => 'Введите инвайт от преподавателя или выберите открытый курс ниже.',
                        ])
                    @endif

                    @if(!$isTeacher && $availableCourses->count())
                        <div class="mt-5">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h5 class="mb-0">Доступные курсы</h5>
                                <span class="text-muted small">{{ $availableCourses->count() }} всего</span>
                            </div>
                            <div class="row row-cols-1 row-cols-md-2 row-cols-xxl-3 g-3">
                                @foreach($availableCourses as $course)
                                    @include('courses.partials.home_course_card', ['variant' => 'available', 'isLinked' => false])
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                @include('courses.partials.home_profile_panel')
            </div>
        </div>

        @if ($isTeacher)
            <div class="tab-pane fade" id="draft" role="tabpanel">
                <div class="row g-4">
                    <div class="col-12 col-xl-9">
                        @if($draftCourses->count())
                            <div class="row row-cols-1 row-cols-md-2 row-cols-xxl-3 g-3">
                                @foreach($draftCourses as $course)
                                    @include('courses.partials.home_course_card', ['variant' => 'draft'])
                                @endforeach
                            </div>
                        @else
                            @include('courses.partials.home_empty_state', [
                                'icon' => 'fas fa-pen-ruler',
                                'title' => 'Черновиков нет',
                                'text' => 'Создайте курс, чтобы подготовить программу до запуска.',
                                'actionUrl' => url('/insider/courses/create/'),
                                'actionText' => 'Создать курс',
                            ])
                        @endif
                    </div>
                    @include('courses.partials.home_profile_panel')
                </div>
            </div>

            <div class="tab-pane fade" id="archive" role="tabpanel">
                <div class="row g-4">
                    <div class="col-12 col-xl-9">
                        @if($archiveCourses->count())
                            <div class="row row-cols-1 row-cols-md-2 row-cols-xxl-3 g-3">
                                @foreach($archiveCourses as $course)
                                    @include('courses.partials.home_course_card', ['variant' => 'archive'])
                                @endforeach
                            </div>
                        @else
                            @include('courses.partials.home_empty_state', [
                                'icon' => 'fas fa-box-archive',
                                'title' => 'Архив пуст',
                                'text' => 'Завершенные курсы появятся здесь.',
                            ])
                        @endif
                    </div>
                    @include('courses.partials.home_profile_panel')
                </div>
            </div>
        @endif
    </div>
@endsection
