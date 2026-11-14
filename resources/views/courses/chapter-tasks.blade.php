@extends('layouts.left-menu')
@section('title', 'Задачи главы — '.$course->name)
@section('content')
    <x-gc-course-header :course="$course" :chapter="$chapter" active="tasks" />
    <div class="gc-stack">
        <form method="GET" class="gc-workspace-toolbar gc-stack gc-stack--compact" aria-label="Фильтры задач">
            <div class="gc-course-task-controls">
                @if($course->program->chapters->count() > 1)
                    <label class="gc-workspace-field"><span class="form-label mb-0">Глава</span>
                        <select name="chapter" class="form-select" onchange="this.form.submit()">
                            @foreach($course->program->chapters as $item)
                                <option value="{{ $item->id }}" @selected($chapter?->id === $item->id) @disabled(!$available->contains('id', $item->id))>{{ $item->name }}{{ !$available->contains('id', $item->id) ? ' · пока закрыта' : '' }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <div class="min-width-0"><h2 class="gc-section-title mb-1">Задачи главы</h2><p class="gc-help-text mb-0">Выполнено {{ $counts['done'] }} из {{ $counts['all'] }} · только доступные вам задания</p></div>
            </div>
            <div class="gc-status-filters" role="group" aria-label="Состояние задач">
                @foreach(['all' => 'Все', 'pending' => 'К выполнению', 'review' => 'На проверке', 'done' => 'Выполнено'] as $key => $label)
                    <button class="gc-filter-toggle" name="state" value="{{ $key }}" aria-pressed="{{ $state === $key ? 'true' : 'false' }}">{{ $label }} <span class="gc-help-text">{{ $counts[$key] }}</span></button>
                @endforeach
            </div>
            <noscript><button class="btn btn-outline-secondary btn-sm">Показать главу</button></noscript>
        </form>
        @php $visibleCount = 0; @endphp
        @foreach($taskGroups as $group)
            @php
                $rows = $group['rows']->filter(fn ($row) => $state === 'all' || ($state === 'pending' ? $row['state'] !== 'done' : $row['state'] === $state));
                $visibleCount += $rows->count();
            @endphp
            @continue($rows->isEmpty())
            <section class="gc-card gc-course-task-group" aria-labelledby="tasks-lesson-{{ $group['lesson']->id }}">
                <header class="gc-course-task-group__heading"><h3 class="gc-section-title mb-0" id="tasks-lesson-{{ $group['lesson']->id }}">{{ $group['lesson']->name }}</h3><span class="gc-help-text">Задач: {{ $rows->count() }}</span></header>
                <ul class="list-unstyled mb-0">
                    @foreach($rows as $row)
                        @php $task = $row['task']; @endphp
                        <li>
                            <a class="gc-course-task-row" href="{{ url('/insider/courses/'.$course->id.'/steps/'.$row['step']->id.'#task'.$task->id) }}" data-chapter-task="{{ $task->id }}" data-task-result="{{ $row['state'] }}">
                                <span class="gc-course-task-row__body"><strong>{{ $task->name }}@if($task->is_star) <i class="fas fa-star" aria-label="Дополнительная задача"></i>@endif</strong><small>{{ $row['step']->name }}@if($task->getDeadline($course->id)?->expiration) · Срок: {{ $task->getDeadline($course->id)->expiration->format('d.m.Y') }}@endif</small></span>
                                <x-gc-task-state :task="$task" :viewer="$user" :course="$course" />
                                <span class="gc-course-task-row__score">{{ $row['state'] === 'blocked' ? 0 : ($row['best']?->mark ?? 0) }} / {{ $task->max_mark }} XP</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
        @if(!$visibleCount)
            <div class="gc-empty-state"><div class="gc-empty-icon"><i class="fas fa-list-check" aria-hidden="true"></i></div><h3 class="h6">{{ $counts['all'] ? 'В этом фильтре нет задач' : 'Доступных задач пока нет' }}</h3><p>{{ $counts['all'] ? 'Выберите другой статус, чтобы увидеть остальные задания.' : 'Задачи появятся здесь, когда преподаватель откроет уроки.' }}</p></div>
        @endif
    </div>
@endsection
