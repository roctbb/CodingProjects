@extends('layouts.left-menu')

@section('title', 'Мои курсы')

@section('content')
    @php
        $isTeacher = $isTeacher ?? ($user->role == 'teacher' || $user->role == 'admin');
        $activeCourses = $activeCourses ?? $my_courses->where('state', 'started');
        $draftCourses = $draftCourses ?? collect();
        $archiveCourses = $archiveCourses ?? collect();
        $availableCourses = $availableCourses ?? $open_courses;
        $completedCourses = $completedCourses ?? collect();
        $birthdayUsers = $birthdayUsers ?? collect();
        $rank = $user->rank();
        $score = $user->score();
        $balance = $user->balance();
        $rankProgress = min(100, max(0, round(100 * ($score - $rank->from) / max(1, $rank->to - $rank->from))));
    @endphp

    <div class="courses-page">

    @if($user->isBirthday())
        <div class="gc-card border p-3 mb-3 alert-dismissible fade show position-relative" role="alert">
            <h5 class="mb-0 pe-4">
                <i class="fas fa-wand-magic-sparkles text-warning" aria-hidden="true"></i>
                С днем рождения!!!
                <i class="fas fa-wand-magic-sparkles text-warning" aria-hidden="true"></i>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
        </div>
    @endif

    <div class="gc-page-masthead mb-3">
    <x-gc-page-header title="Мои курсы" mark="rocket">
        <x-slot:middle>@include('courses.partials.home_activity_feed', ['compact' => true])</x-slot:middle>
        @if ($isTeacher)
            <a class="btn btn-primary fw-medium" href="{{ url('/insider/courses/create/') }}"><i class="fas fa-plus me-1"></i>Создать курс</a>
        @else
            <form autocomplete="off" class="gc-invite-form" method="get" action="{{ url('insider/invite') }}">
                @csrf
                <i class="fas fa-ticket-alt text-muted d-none d-sm-inline"></i>
                <input type="text" class="form-control" name="invite" maxlength="255" placeholder="Код приглашения" aria-label="Код приглашения на курс">
                <button type="submit" class="btn btn-primary fw-medium text-nowrap">Добавить</button>
            </form>
        @endif
    </x-gc-page-header>

    @if ($isTeacher)
        <div class="d-flex align-items-center justify-content-between mb-3">
            <x-gc-tab-strip>
            <ul class="nav gc-page-tabs" id="coursesTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link fw-medium text-nowrap active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                        Активные <span class="badge rounded-pill gc-tab-count">{{ $activeCourses->count() }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-medium text-nowrap" id="draft-tab" data-bs-toggle="tab" data-bs-target="#draft" type="button" role="tab">
                        Черновики <span class="badge rounded-pill gc-tab-count">{{ $draftCourses->count() }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-medium text-nowrap" id="archive-tab" data-bs-toggle="tab" data-bs-target="#archive" type="button" role="tab">
                        Архив <span class="badge rounded-pill gc-tab-count">{{ $archiveCourses->count() }}</span>
                    </button>
                </li>
            </ul>
            </x-gc-tab-strip>
        </div>
    @endif
    </div>
    @include('market.partials.promotion-banner')

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

                    @include('courses.partials.home_project_journey')

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
                    @elseif(!$isTeacher && $upcomingDeadlines->count())
                        <x-gc-deadline-preview :items="$upcomingDeadlines" :show-course="true" :limit="2" class="mb-3" />
                    @endif

                    @if ($activeCourses->count())
                        <div class="row row-cols-1 row-cols-md-2 {{ $activeCourses->count() > 2 ? ($isTeacher ? 'row-cols-xxl-3' : 'row-cols-xl-3') : '' }} g-3">
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

                    @if(!$isTeacher && $completedCourses->count())
                        <div class="mt-5">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h5 class="mb-0">Пройденные курсы</h5>
                                <span class="text-muted small">{{ $completedCourses->count() }} всего</span>
                            </div>
                            <div class="row row-cols-1 row-cols-md-2 row-cols-xxl-3 g-3">
                                @foreach($completedCourses as $completedCourse)
                                    @include('courses.partials.home_completed_course_card')
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
    </div>
@endsection
