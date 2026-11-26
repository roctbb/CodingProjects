@extends('layouts.left-menu')

@section('title', 'Дедлайны — '.$course->name)

@section('content')
    @php
        $isVisible = fn ($item) => $isManager || !$item['fully_penalized'];
    @endphp
    <div class="container-fluid px-0" data-deadline-workspace @unless($isManager) data-deadline-student @endunless>
        <x-gc-course-header :course="$course" :deadline-items="$deadlineItems" active="deadlines" />

        <div class="gc-card gc-empty-state" data-deadline-empty @if($deadlineItems->contains($isVisible)) hidden @endif>
            <div class="gc-empty-icon"><i class="fas fa-calendar-check" aria-hidden="true"></i></div>
            <h2 class="h6">{{ $isManager ? 'Дедлайнов пока нет' : 'Нет невыполненных задач со сроками' }}</h2>
            <p class="mx-auto mb-0">{{ $isManager ? 'Здесь появятся сроки задач из доступных вам уроков курса.' : 'Здесь появятся невыполненные задачи со сроками сдачи из доступных уроков.' }}</p>
        </div>

        @if($deadlineItems->isNotEmpty())
            @php
                [$missed, $upcoming] = $deadlineItems->partition(fn ($item) => $item['next'] === null);
                $deadlineGroups = [
                    'missed' => ['title' => 'Пропущенные дедлайны', 'items' => $missed],
                    'upcoming' => ['title' => 'Ближайшие дедлайны', 'items' => $upcoming],
                ];
            @endphp
            <div class="gc-stack">
                @foreach($deadlineGroups as $key => $group)
                    <section class="gc-deadline-agenda" aria-labelledby="deadlines-{{ $key }}" data-deadline-agenda="{{ $key }}" @unless($group['items']->contains($isVisible)) hidden @endunless>
                        <h2 class="gc-section-title" id="deadlines-{{ $key }}">{{ $group['title'] }}</h2>
                        @if($key === 'missed')
                            <p class="gc-help-text">Эти задачи ещё можно сдать.</p>
                        @endif
                        <ul class="list-unstyled mb-0" data-deadline-list>
                            @foreach($group['items'] as $item)
                                @php($task = $item['task'])
                                <li class="gc-card gc-deadline-task" data-deadline-row data-deadline-task-id="{{ $task->id }}" data-deadline-next-cutoff="{{ $item['next'] ?? '' }}" data-deadline-last="{{ $item['last']?->getTimestampMs() ?? 0 }}" @unless($isVisible($item)) hidden @endunless>
                                    <a class="gc-deadline-task__body" href="{{ url('/insider/courses/'.$course->id.'/steps/'.$task->step_id.'#task'.$task->id) }}">
                                        <strong>{{ $task->name }}</strong>
                                        @if($item['audience_name'])<small>{{ $item['audience_name'] }}</small>@endif
                                        <small>{{ $task->step->lesson->name }}@if($task->step->name !== $task->step->lesson->name)<span aria-hidden="true"> · </span>{{ $task->step->name }}@endif</small>
                                    </a>
                                    <div class="gc-deadline-task__conditions">
                                        <small class="gc-help-text" @if($item['next'] !== null) hidden @endif data-deadline-last-label>Последний срок</small>
                                        <x-gc-deadline-chain :rule="$item['rule']" :compact="true" note="Условия применяются к новым сдачам. Уже полученные баллы сохраняются. Сдавать можно и после последнего срока." />
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
@endsection
