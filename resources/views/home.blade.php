@extends('layouts.left-menu')

@section('title')
    Мои курсы
@endsection

@section('content')
    @php
        $isManager = $user->role == 'teacher' || $user->role == 'admin';
        $startedCourses = $my_courses->where('state', 'started');
        $draftCourses = $courses->where('state', 'draft')->filter(function ($course) use ($user) {
            return $user->role == 'admin' || $course->teachers->contains($user);
        });
        $archiveCourses = $courses->where('state', 'ended')->sortByDesc('start_date')->filter(function ($course) use ($user) {
            return $user->role == 'admin' || $course->teachers->contains($user);
        });
        $upcomingBirthdays = $users->where('birthday', '!=', null)->sortBy(function ($col) {
            return $col->birthday->day;
        })->filter(function ($buser) {
            return $buser->birthday->month == \Carbon\Carbon::now()->month
                && $buser->birthday->day > \Carbon\Carbon::now()->day - 10
                && $buser->birthday->day < \Carbon\Carbon::now()->day + 10;
        });
    @endphp

    @php
        $cpuiTabsSelector = '.cp-tabs .nav-link[data-bs-toggle="tab"]';
        $cpuiPopoverSelector = '[data-bs-toggle="popover"]';
        $cpuiEnableHashSync = true;

        $courseCards = $startedCourses->values()->map(function ($course) use ($user) {
            $percent = null;
            $cstudent = $course->students->firstWhere('id', $user->id);
            if ($course->students->contains($user)) {
                $percent = (int) round($course->getPercent($user));
            }

            $taskAlerts = collect();
            if ($cstudent != null) {
                foreach ($course->program->steps as $step) {
                    foreach ($step->tasks as $task) {
                        $deadlineModel = $task->getDeadline($course->id);
                        if ($deadlineModel && !$task->isDone($cstudent->id)) {
                            $exp = $deadlineModel->expiration;
                            $deadline = $exp instanceof \Carbon\Carbon
                                ? $exp->copy()->addDay()
                                : \Carbon\Carbon::parse($exp)->addDay();

                            if (\Carbon\Carbon::now()->gt($deadline)) {
                                $taskAlerts->push([
                                    'level' => 'danger',
                                    'step_id' => $step->id,
                                    'task_id' => $task->id,
                                    'task_name' => $task->name,
                                    'deadline_at' => $deadline,
                                    'deadline_label' => $deadline->format('d.m'),
                                ]);
                            } elseif (\Carbon\Carbon::now()->addDays(1)->gt($deadline)) {
                                $taskAlerts->push([
                                    'level' => 'warning',
                                    'step_id' => $step->id,
                                    'task_id' => $task->id,
                                    'task_name' => $task->name,
                                    'deadline_at' => $deadline,
                                    'deadline_label' => $deadline->format('d.m'),
                                ]);
                            }
                        }
                    }
                }
            }

            $overdueCount = $taskAlerts->where('level', 'danger')->count();
            $warningCount = $taskAlerts->where('level', 'warning')->count();
            $urgentAlert = $taskAlerts->firstWhere('level', 'danger') ?: $taskAlerts->firstWhere('level', 'warning');

            $continueStep = optional(
                $course->program->lessons->first(function ($lesson) use ($course, $user) {
                    return $lesson->steps->count() > 0
                        && ($lesson->isAvailable($course) || $course->teachers->contains($user) || $user->role == 'admin');
                })
            )->steps->first();

            if ($overdueCount > 0) {
                $statusLabel = 'Просрочено';
                $statusClass = 'danger';
            } elseif ($warningCount > 0) {
                $statusLabel = 'Нужно сделать';
                $statusClass = 'warning';
            } elseif ($percent !== null && $percent >= 100) {
                $statusLabel = 'Сдано';
                $statusClass = 'success';
            } elseif ($percent !== null && $percent > 0) {
                $statusLabel = 'В процессе';
                $statusClass = 'progress';
            } else {
                $statusLabel = 'Нужно сделать';
                $statusClass = 'warning';
            }

            return [
                'course' => $course,
                'percent' => $percent,
                'overdue_count' => $overdueCount,
                'warning_count' => $warningCount,
                'urgent_alert' => $urgentAlert,
                'continue_step_id' => optional($continueStep)->id,
                'continue_url' => $continueStep
                    ? url('/insider/courses/'.$course->id.'/steps/'.$continueStep->id)
                    : url('insider/courses/'.$course->id),
                'status_label' => $statusLabel,
                'status_class' => $statusClass,
            ];
        });

        $primaryContinueCard = $courseCards->first();
        $todayOverdueTotal = $courseCards->sum('overdue_count');
        $todayWarningTotal = $courseCards->sum('warning_count');
        $nextDeadlineItem = $courseCards->pluck('urgent_alert')->filter()->sortBy('deadline_at')->first();
    @endphp

    <div class="cp-dashboard cp-home-page">
        @if($user->isBirthday())
            <div class="alert alert-info alert-dismissible cp-alert" role="alert">
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                </button>
                С днем рождения! Пусть учебный год будет ярким и результативным.
            </div>
        @endif

        <section class="cp-toolbar">
            <div class="cp-toolbar__actions">
                @if ($isManager)
                    <ul class="nav nav-tabs cp-tabs cp-tabs--manager" id="coursesTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="active-tab" data-bs-toggle="tab" href="#active" role="tab"
                               aria-controls="active" aria-selected="true">
                                <i class="icon fa-solid fa-building-columns"></i>
                                <span>Мои курсы</span>
                                <span class="cp-tab-count">{{ $startedCourses->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="draft-tab" data-bs-toggle="tab" href="#draft" role="tab"
                                aria-controls="draft" aria-selected="false">
                                <i class="icon fa-solid fa-pen-to-square"></i>
                                <span>Черновики</span>
                                <span class="cp-tab-count">{{ $draftCourses->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="archive-tab" data-bs-toggle="tab" href="#archive" role="tab"
                                aria-controls="archive" aria-selected="false">
                                <i class="icon fa-solid fa-box-archive"></i>
                                <span>Архив</span>
                                <span class="cp-tab-count">{{ $archiveCourses->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="cp-create-btn" href="{{ url('/insider/courses/create/') }}">
                                <i class="icon fa-solid fa-circle-plus"></i> Создать
                            </a>
                        </li>
                    </ul>
                @else
                    <form autocomplete="off" class="cp-invite" method="post" action="{{ url('insider/invite') }}">
                        <input autocomplete="false" name="hidden" type="text" class="cp-hidden-input">
                        {{ csrf_field() }}
                        <input type="text" class="form-control" id="invite" name="invite" placeholder="Инвайт на курс">
                        <button type="submit" class="btn btn-primary">Добавить</button>
                    </form>
                @endif
            </div>
        </section>

        <div class="tab-content" id="courses">
            <div class="tab-pane fade show active cp-pane" id="active" role="tabpanel" aria-labelledby="active-tab">
                <div class="cp-layout">
                    <div class="cp-main-col">
                        <section class="cp-today-card">
                            <div class="cp-today-card__head">
                                <div>
                                    <h3 class="cp-today-card__title">{{ $isManager ? '📊 Панель преподавателя' : '📚 Что сделать сегодня' }}</h3>
                                    <p class="cp-today-card__subtitle">
                                        {{ $isManager ? 'Быстрый обзор ваших курсов и текущей нагрузки.' : 'Короткий план, чтобы не потеряться в дедлайнах.' }}
                                    </p>
                                </div>
                                @if (!$isManager && $primaryContinueCard)
                                    <a class="cp-today-card__continue" href="{{ url('insider/courses/'.$primaryContinueCard['course']->id) }}">
                                        <i class="icon fa-solid fa-arrow-right"></i> Перейти к курсу
                                    </a>
                                @endif
                                @if ($isManager)
                                    <a class="cp-today-card__continue" href="#active">
                                        <i class="icon fa-solid fa-layer-group"></i> К курсам
                                    </a>
                                @endif
                            </div>

                            <div class="cp-today-card__stats">
                                @if ($isManager)
                                    <span class="cp-today-card__stat">🎓 Активных курсов: <strong>{{ $startedCourses->count() }}</strong></span>
                                    <span class="cp-today-card__stat">📝 Черновиков: <strong>{{ $draftCourses->count() }}</strong></span>
                                    <span class="cp-today-card__stat">🗂 Архив: <strong>{{ $archiveCourses->count() }}</strong></span>
                                @else
                                    <span class="cp-today-card__stat">✅ Сдано за 7 дней: <strong>{{ $weeklySubmittedCount }}</strong></span>
                                    <span class="cp-today-card__stat">🧠 Проверено: <strong>{{ $weeklyCheckedCount }}</strong></span>
                                    <span class="cp-today-card__stat">🏆 XP: <strong>{{ $weeklyPoints }}</strong></span>
                                @endif
                            </div>

                            <ul class="cp-today-list">
                                @if ($isManager)
                                    <li class="cp-today-list__item">
                                        <span class="cp-today-list__icon">⚠️</span>
                                        <span>Сейчас в работе просроченных задач: {{ $todayOverdueTotal }}</span>
                                    </li>
                                    <li class="cp-today-list__item">
                                        <span class="cp-today-list__icon">⏱</span>
                                        <span>Заданий на подходе по дедлайнам: {{ $todayWarningTotal }}</span>
                                    </li>
                                @else
                                    @if ($primaryContinueCard)
                                        <li class="cp-today-list__item">
                                            <span class="cp-today-list__icon">▶</span>
                                            <span>
                                                Перейти к курсу
                                                <a href="{{ url('insider/courses/'.$primaryContinueCard['course']->id) }}">{{ $primaryContinueCard['course']->name }}</a>
                                            </span>
                                        </li>
                                    @endif

                                    <li class="cp-today-list__item">
                                        <span class="cp-today-list__icon">⏰</span>
                                        <span>
                                            @if ($nextDeadlineItem)
                                                Ближайший дедлайн: {{ $nextDeadlineItem['task_name'] }} до {{ $nextDeadlineItem['deadline_label'] }}
                                            @else
                                                Дедлайнов на сегодня нет, можно двигаться по плану.
                                            @endif
                                        </span>
                                    </li>

                                    <li class="cp-today-list__item">
                                        <span class="cp-today-list__icon">⚠️</span>
                                        <span>Просрочено задач: {{ $todayOverdueTotal }}</span>
                                    </li>
                                @endif

                                <li class="cp-today-list__item">
                                    <span class="cp-today-list__icon">📌</span>
                                    <span>
                                        @if ($todayWarningTotal > 0)
                                            {{ $isManager ? 'На подходе дедлайны у студентов: ' : 'На подходе дедлайны: ' }}{{ $todayWarningTotal }}
                                        @elseif($recentNotifications->count())
                                            Последнее уведомление: {{ \Illuminate\Support\Str::limit(strip_tags($recentNotifications->first()->data['text'] ?? ''), 90) }}
                                        @else
                                            Уведомлений пока нет.
                                        @endif
                                    </span>
                                </li>
                            </ul>
                        </section>

                        @foreach($notifications as $notification)
                            <div class="alert alert-{{ $notification->data['type'] }} alert-dismissible fade show" role="alert">
                                {!! $notification->data['text'] !!}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                                </button>
                            </div>
                        @endforeach

                        @if ($startedCourses->count())
                            <div class="cp-courses-grid">
                                @foreach($courseCards as $courseCard)
                                    @php
                                        $course = $courseCard['course'];
                                        $percent = $courseCard['percent'];
                                        $overdueCount = $courseCard['overdue_count'];
                                        $warningCount = $courseCard['warning_count'];
                                    @endphp

                                    <article class="cp-course-card {{ $course->is_open ? 'cp-course-card--open' : 'cp-course-card--private' }} @if ($overdueCount > 0) cp-course-card--overdue @elseif ($warningCount > 0) cp-course-card--attention @endif">
                                        <header class="cp-course-card__header">
                                            <h3 class="cp-course-card__title cp-course-card__title--compact">
                                                <a href="{{ url('insider/courses/'.$course->id) }}">{{ $course->name }}</a>
                                            </h3>
                                            <span class="cp-status cp-status--{{ $courseCard['status_class'] }}">{{ $courseCard['status_label'] }}</span>
                                        </header>

                                        <div class="cp-course-card__subtitle cp-course-card__subtitle--compact">
                                            <span>Уроков: {{ $course->program->lessons->count() }}</span>
                                            <span>Студентов: {{ $course->students->count() }}</span>
                                            <span>{{ $course->is_open ? 'Открытый курс' : 'Курс группы' }}</span>
                                        </div>

                                        @if ($percent !== null)
                                            <div class="cp-progress-mini">
                                                <div class="cp-progress-mini__track">
                                                    <span style="width: {{ max(0, min(100, $percent)) }}%;"></span>
                                                </div>
                                                <span class="cp-progress-mini__value">{{ $percent }}%</span>
                                            </div>
                                        @endif

                                        <div class="cp-course-card__meta">
                                            @if ($overdueCount > 0)
                                                <span class="cp-chip cp-chip--danger">Просрочено: {{ $overdueCount }}</span>
                                            @endif
                                            @if ($warningCount > 0)
                                                <span class="cp-chip cp-chip--warn">Срок скоро: {{ $warningCount }}</span>
                                            @endif
                                        </div>

                                        <footer class="cp-course-card__footer">
                                            <a class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" href="{{ url('insider/courses/'.$course->id) }}">
                                                <i class="icon fa-solid fa-arrow-right"></i> Перейти к курсу
                                            </a>
                                            @if ($course->site != null)
                                                <a class="cp-link" target="_blank" href="{{ $course->site }}"><i class="icon fa-solid fa-link"></i> Сайт</a>
                                            @endif
                                        </footer>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="cp-empty">Вы пока не записаны на курсы.</div>
                        @endif
                    </div>

                    <div class="cp-side-col">
                        <aside class="cp-side-card cp-birthday-card">
                            <div class="cp-birthday-card__head">
                                <h3 class="cp-side-card__title cp-birthday-card__title">
                                    <i class="icon fa-solid fa-star"></i>
                                    Празднуем день рождения
                                </h3>
                                <span class="cp-birthday-card__count">
                                    {{ $upcomingBirthdays->count() }}
                                </span>
                            </div>
                            @if ($upcomingBirthdays->count())
                                <ul class="cp-birthday-list">
                                    @foreach($upcomingBirthdays as $buser)
                                        @php
                                            $isTodayBirthday = $buser->birthday->day == \Carbon\Carbon::now()->day
                                                && $buser->birthday->month == \Carbon\Carbon::now()->month;
                                        @endphp
                                        <li>
                                            <a class="@if ($isTodayBirthday) cp-birthday-list__today @endif"
                                               href="{{ url('insider/profile/'.$buser->id) }}">
                                                {{ $buser->name }}
                                            </a>
                                            <span class="cp-birthday-date @if ($isTodayBirthday) cp-birthday-date--today @endif">
                                                {{ $buser->birthday->format('d.m') }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="cp-empty cp-empty--compact cp-birthday-empty">В ближайшие дни дней рождения нет.</div>
                            @endif
                        </aside>
                    </div>
                </div>
            </div>

            @if ($isManager)
                <div class="tab-pane fade cp-pane cp-pane--draft" id="draft" role="tabpanel" aria-labelledby="draft-tab">
                    @if ($draftCourses->count())
                        <div class="cp-courses-grid cp-courses-grid--draft">
                            @foreach($draftCourses as $course)
                                <article class="cp-course-card cp-course-card--draft cp-course-card--draft-view">
                                    <header class="cp-course-card__header">
                                        <h3 class="cp-course-card__title">
                                            <a href="{{ url('insider/courses/'.$course->id) }}">{{ $course->name }}</a>
                                        </h3>
                                        <span class="cp-badge">Черновик</span>
                                    </header>
                                    <p class="cp-course-card__hint">Курс еще не запущен и доступен для доработки.</p>
                                    <p class="cp-course-card__description">{{ $course->description }}</p>
                                    <footer class="cp-course-card__footer">
                                        <a href="{{ url('insider/courses/'.$course->id.'/edit') }}"
                                           class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                                            <i class="icon fa-solid fa-pen-to-square"></i> Редактировать
                                        </a>
                                        <a href="{{ url('insider/courses/'.$course->id) }}"
                                           class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                                            <i class="icon fa-solid fa-play"></i> Открыть
                                        </a>
                                    </footer>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="cp-empty">Черновиков пока нет.</div>
                    @endif
                </div>

                <div class="tab-pane fade cp-pane cp-pane--archive" id="archive" role="tabpanel" aria-labelledby="archive-tab">
                    @if ($archiveCourses->count())
                        <div class="cp-courses-grid cp-courses-grid--archive">
                            @foreach($archiveCourses as $course)
                                <article class="cp-course-card cp-course-card--archive">
                                    <header class="cp-course-card__header">
                                        <h3 class="cp-course-card__title">{{ $course->name }}</h3>
                                        <p class="cp-archive-date">
                                            @if ($course->start_date){{ $course->start_date->format('d.m.Y') }}@endif
                                            @if ($course->end_date) - {{ $course->end_date->format('d.m.Y') }}@endif
                                        </p>
                                    </header>
                                    <footer class="cp-course-card__footer">
                                        <a href="{{ url('insider/courses/'.$course->id) }}"
                                           class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                                            <i class="icon fa-solid fa-file-lines"></i> Страница
                                        </a>
                                    </footer>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="cp-empty">Архивных курсов пока нет.</div>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection
