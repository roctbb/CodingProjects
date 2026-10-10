@extends('layouts.left-menu')

@section('title', 'Дедлайны — '.$course->name)

@section('content')
    <div class="container-fluid px-0">
        <div class="gc-card gc-page-header mb-4">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id) }}"><i class="fas fa-chevron-left" aria-hidden="true"></i> К курсу</a>
                <h2 class="mb-1">Дедлайны</h2>
                <p class="text-muted mb-0">{{ $course->name }} · Все главы</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge rounded-pill bg-body-tertiary">Всего: {{ $deadlines->count() }}</span>
                @if ($deadlines->where('is_overdue', true)->count())
                    <span class="badge rounded-pill bg-danger-subtle text-danger">Просрочено: {{ $deadlines->where('is_overdue', true)->count() }}</span>
                @endif
            </div>
        </div>

        @if ($deadlines->isEmpty())
            <div class="gc-empty-state">
                <div class="gc-empty-icon"><i class="fas fa-calendar-check" aria-hidden="true"></i></div>
                <h5>Дедлайнов пока нет</h5>
                <p class="mx-auto mb-0">Здесь появятся сроки задач из доступных вам уроков курса.</p>
            </div>
        @else
            <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Перейти к месяцу">
                @foreach ($deadlineMonths as $monthKey => $days)
                    <a class="btn btn-outline-secondary btn-sm rounded-pill" href="#month-{{ $monthKey }}">
                        {{ $days->first()->first()->expiration->copy()->locale('ru')->translatedFormat('F Y') }}
                    </a>
                @endforeach
            </nav>

            @foreach ($deadlineMonths as $monthKey => $days)
                <section class="mb-5" aria-labelledby="month-{{ $monthKey }}">
                    <h3 class="h4 mb-3 text-capitalize" id="month-{{ $monthKey }}">
                        {{ $days->first()->first()->expiration->copy()->locale('ru')->translatedFormat('F Y') }}
                    </h3>
                    <div class="d-flex flex-column gap-3">
                        @foreach ($days as $dateKey => $dayDeadlines)
                            @php($date = $dayDeadlines->first()->expiration->copy()->locale('ru'))
                            <section class="gc-card p-3 p-md-4" aria-labelledby="day-{{ $dateKey }}">
                                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                                    <h4 class="h6 mb-0" id="day-{{ $dateKey }}">
                                        <time datetime="{{ $dateKey }}">{{ $date->translatedFormat('j F, l') }}</time>
                                    </h4>
                                    <span class="badge rounded-pill bg-body-tertiary">{{ $dayDeadlines->count() }}</span>
                                </div>
                                <ul class="list-unstyled d-flex flex-column gap-2 mb-0">
                                    @foreach ($dayDeadlines as $deadline)
                                        <li>
                                            <a class="course-deadline-item d-flex flex-wrap justify-content-between gap-3 p-3 @if ($deadline->is_overdue) is-overdue @elseif ($deadline->is_soon) is-soon @endif"
                                               href="{{ url('/insider/courses/'.$course->id.'/steps/'.$deadline->task->step->id.'#task'.$deadline->task_id) }}">
                                                <span class="course-deadline-body min-width-0 text-break">
                                                    <strong>{{ $deadline->task->name }}</strong>
                                                    <small class="text-muted">{{ $deadline->task->step->lesson->name }} · {{ $deadline->task->step->name }}</small>
                                                </span>
                                                <span class="course-deadline-state @if ($deadline->is_done) bg-success-subtle text-success @endif">
                                                    @if ($deadline->is_done)
                                                        Выполнено
                                                    @elseif ($deadline->is_overdue)
                                                        Просрочено
                                                    @elseif ($deadline->is_today)
                                                        Сегодня
                                                    @elseif ($deadline->is_soon)
                                                        Скоро
                                                    @else
                                                        Предстоит
                                                    @endif
                                                </span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
    </div>
@endsection
