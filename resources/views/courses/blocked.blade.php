@extends('layouts.left-menu')

@section('title')
    {{$course->name}}
@endsection

@section('content')
    @php
        $blockedRecords = $blocked->flatten(1);
        $blockedStudentsCount = $blocked->count();
        $blockedTasksCount = $blockedRecords->unique('task_id')->count();
        $blockedRowsCount = $blockedRecords->unique(function ($record) {
            return $record->user_id.':'.$record->task_id;
        })->count();
    @endphp
    <div class="container-fluid px-0">
        <div class="gc-card gc-page-header mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id) }}"><i class="icon ion-chevron-left"></i> К курсу</a>
                <h2 class="mb-1">Заблокированные задачи</h2>
                <p class="mb-0 text-muted text-truncate">{{ $course->name }}</p>
            </div>
            <div class="row row-cols-3 g-2 flex-shrink-0 blocked-summary">
                <div class="col"><div class="gc-summary-tile"><strong>{{ $blockedStudentsCount }}</strong><span>учеников</span></div></div>
                <div class="col"><div class="gc-summary-tile"><strong>{{ $blockedTasksCount }}</strong><span>задач</span></div></div>
                <div class="col"><div class="gc-summary-tile"><strong>{{ $blockedRowsCount }}</strong><span>записей</span></div></div>
            </div>
        </div>

        @if ($blocked->isEmpty())
            <div class="gc-empty-state">
                <div class="gc-empty-icon"><i class="fas fa-shield-alt"></i></div>
                <h5>Блокировок нет</h5>
                <p class="mx-auto mb-0">Если задача будет заблокирована из-за плагиата или другой причины, она появится здесь.</p>
            </div>
        @else
            <div class="gc-card gc-toolbar-card blocked-toolbar">
                <div>
                    <h5 class="mb-1">Список блокировок</h5>
                    <p class="mb-0 text-muted small">Быстрый обзор задач, которые требуют внимания преподавателя.</p>
                </div>
                <div class="input-group input-group-sm gc-search-box blocked-search">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="search" class="form-control" placeholder="Найти блокировку" aria-label="Найти блокировку" data-blocked-search data-blocked-table="#blocked-table">
                    <button class="btn d-none" type="button" data-blocked-clear aria-label="Очистить поиск"><i class="fas fa-times"></i></button>
                    <span class="input-group-text gc-search-box__count" data-blocked-count>{{ $blockedRowsCount }} из {{ $blockedRowsCount }}</span>
                </div>
            </div>

            <div class="gc-card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 gc-data-table course-blocked-table" id="blocked-table">
                        <thead class="text-uppercase small">
                        <tr>
                            <th>Студент</th>
                            <th>Задача</th>
                            <th>Причина</th>
                            <th>Дата</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($blocked as $user_id => $records)
                            @php
                                $student = $records->first()->user;
                                $tasks = $records->unique('task_id');
                            @endphp
                            @foreach ($tasks as $bt)
                                <tr data-blocked-row data-blocked-search-text="{{ $student->name }} {{ $student->activeCustomTitle() }} {{ optional($bt->task)->name ?? ('Задача #'.$bt->task_id) }} {{ $bt->reason ?? 'заблокировано' }}">
                                    <td data-label="Студент">
                                        <a class="fw-semibold text-decoration-none d-inline-flex align-items-center gap-1 min-width-0" href="{{ url('/insider/profile/'.$student->id) }}" target="_blank">
                                            <span class="text-truncate">{{ $student->name }}</span>
                                            @include('profile.partials.custom_title_badge', ['profileUser' => $student, 'compact' => true])
                                        </a>
                                    </td>
                                    <td data-label="Задача">
                                        <a class="text-decoration-none" target="_blank"
                                           href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$bt->task_id.'/student/'.$student->id) }}">
                                            {{ optional($bt->task)->name ?? ('Задача #'.$bt->task_id) }}
                                        </a>
                                    </td>
                                    <td data-label="Причина"><span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle fw-semibold">{{ $bt->reason ?? 'заблокировано' }}</span></td>
                                    <td data-label="Дата">
                                        @if($bt->blocked_at)
                                            {{ $bt->blocked_at->format('d.m.Y H:i') }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
