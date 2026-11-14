@extends('layouts.left-menu')

@section('title', 'Дедлайны — '.$course->name)

@section('content')
    @php
        $pendingCount = $deadlines->where('is_done', false)->count();
        $pastCount = $deadlines->where('is_overdue', true)->count();
        $filters = $isManager
            ? ['all' => ['Все', $deadlines->count()], 'upcoming' => ['Предстоящие', $deadlines->count() - $pastCount], 'overdue' => ['Срок прошёл', $pastCount]]
            : ['all' => ['Все', $deadlines->count()], 'pending' => ['К выполнению', $pendingCount], 'overdue' => ['Просрочено', $pastCount], 'done' => ['Выполнено', $deadlines->count() - $pendingCount]];
        $states = [
            'done' => ['Выполнено', 'fa-check-circle'],
            'overdue' => [$isManager ? 'Срок прошёл' : 'Просрочено', 'fa-clock'],
            'today' => ['Сегодня', 'fa-clock'],
            'soon' => ['Скоро', 'fa-clock'],
            'upcoming' => ['Предстоит', 'fa-calendar'],
        ];
    @endphp
    <div class="container-fluid px-0" data-deadline-workspace>
        <x-gc-course-header :course="$course" active="deadlines" />
        <div class="gc-course-section-heading">
            <div class="min-width-0">
                <h2 class="mb-1">Дедлайны</h2>
                <p class="text-muted small mb-0">Сроки задач из всех доступных глав</p>
            </div>
        </div>

        @if ($deadlines->isEmpty())
            <div class="gc-empty-state">
                <div class="gc-empty-icon"><i class="fas fa-calendar-check" aria-hidden="true"></i></div>
                <h5>Дедлайнов пока нет</h5>
                <p class="mx-auto mb-0">Здесь появятся сроки задач из доступных вам уроков курса.</p>
            </div>
        @else
            <div class="gc-deadline-tools">
                <label class="input-group gc-search-box gc-deadline-search">
                    <span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
                    <input class="form-control" type="search" placeholder="Найти задачу, ступень или урок" aria-label="Поиск по дедлайнам" data-deadline-search>
                </label>
                <span class="gc-help-text" role="status" data-deadline-count>Показано {{ $deadlines->count() }} из {{ $deadlines->count() }}</span>
                <div class="gc-deadline-filters" role="group" aria-label="Состояние задач">
                    @foreach ($filters as $key => [$label, $count])
                        <button class="gc-filter-toggle" type="button" data-deadline-filter="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">{{ $label }} <span class="gc-help-text">{{ $count }}</span></button>
                    @endforeach
                </div>
            </div>

            @if ($deadlineMonths->count() > 1)
                <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Перейти к месяцу" data-deadline-month-links>
                    @foreach ($deadlineMonths as $monthKey => $days)
                        <a class="btn btn-outline-secondary btn-sm" href="#month-{{ $monthKey }}" data-deadline-month-link="{{ $monthKey }}">{{ $days->first()->first()->expiration->copy()->locale('ru')->translatedFormat('F Y') }}</a>
                    @endforeach
                </nav>
            @endif

            @foreach ($deadlineMonths as $monthKey => $days)
                <section class="gc-deadline-month" aria-labelledby="month-{{ $monthKey }}" data-deadline-month="{{ $monthKey }}">
                    <h3 class="h6 mb-3 text-capitalize @if ($deadlineMonths->count() === 1) visually-hidden @endif" id="month-{{ $monthKey }}">{{ $days->first()->first()->expiration->copy()->locale('ru')->translatedFormat('F Y') }}</h3>
                    <div class="d-grid gap-3">
                        @foreach ($days as $dateKey => $dayDeadlines)
                            @php
                                $date = $dayDeadlines->first()->expiration->copy()->locale('ru');
                                $lessonGroups = $dayDeadlines->groupBy(fn ($deadline) => $deadline->task->step->lesson->id);
                            @endphp
                            <section class="gc-card gc-deadline-day" aria-labelledby="day-{{ $dateKey }}" data-deadline-day>
                                <header class="gc-deadline-day__heading">
                                    <h4 class="h6 mb-0" id="day-{{ $dateKey }}"><time datetime="{{ $dateKey }}">{{ $date->translatedFormat('j F, l') }}</time></h4>
                                    <span class="gc-meta-label" data-deadline-day-count>{{ $dayDeadlines->count() }}</span>
                                </header>
                                <div class="gc-deadline-day__lessons">
                                @foreach ($lessonGroups as $lessonDeadlines)
                                    <div class="gc-deadline-lesson" data-deadline-lesson>
                                        <h5 class="gc-deadline-lesson__title">{{ $lessonDeadlines->first()->task->step->lesson->name }}</h5>
                                        <ul class="list-unstyled mb-0">
                                            @foreach ($lessonDeadlines as $deadline)
                                                @php
                                                    $state = $deadline->is_done ? 'done' : ($deadline->is_overdue ? 'overdue' : ($deadline->is_today ? 'today' : ($deadline->is_soon ? 'soon' : 'upcoming')));
                                                    [$stateLabel, $stateIcon] = $states[$state];
                                                @endphp
                                                <li data-deadline-row data-deadline-state="{{ $state }}" data-deadline-search-text="{{ $deadline->task->name.' '.$deadline->task->step->name.' '.$deadline->task->step->lesson->name }}">
                                                    <a class="gc-deadline-task" href="{{ url('/insider/courses/'.$course->id.'/steps/'.$deadline->task->step->id.'#task'.$deadline->task_id) }}">
                                                        <span class="gc-deadline-task__body"><strong>{{ $deadline->task->name }}</strong><small>{{ $deadline->task->step->name }}</small></span>
                                                        <span class="gc-deadline-task__state gc-deadline-task__state--{{ $state }}"><i class="fas {{ $stateIcon }}" aria-hidden="true"></i>{{ $stateLabel }}</span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                </section>
            @endforeach
            <div class="gc-empty-state" data-deadline-empty hidden>
                <div class="gc-empty-icon"><i class="fas fa-search" aria-hidden="true"></i></div>
                <h3 class="h6">Задачи не найдены</h3>
                <p>Попробуйте другое название или измените фильтр.</p>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-deadline-reset>Сбросить поиск и фильтр</button>
            </div>
        @endif
    </div>
@endsection
