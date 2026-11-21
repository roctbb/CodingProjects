@extends('layouts.left-menu')

@section('title', 'Дедлайны — '.$course->name)

@section('content')
    <div class="container-fluid px-0" data-deadline-workspace>
        <x-gc-course-header :course="$course" :deadline-items="$deadlineItems" active="deadlines" />
        <section class="gc-deadline-controls" aria-labelledby="deadline-title">
            <h2 class="gc-section-title" id="deadline-title">Дедлайны</h2>
            @if($deadlineItems->isNotEmpty())
                <details class="gc-deadline-search-panel" data-deadline-search-panel>
                    <summary><i class="fas fa-search" aria-hidden="true"></i> Поиск</summary>
                    <div class="gc-deadline-tools">
                        <div class="input-group gc-search-box gc-deadline-search">
                            <input class="form-control" type="search" placeholder="Задача или урок" aria-label="Поиск по дедлайнам" maxlength="120" data-deadline-search>
                            <button class="btn d-none" type="button" data-deadline-clear aria-label="Очистить поиск"><i class="fas fa-times" aria-hidden="true"></i></button>
                        </div>
                        <select class="form-select" aria-label="Глава" data-deadline-chapter>
                            <option value="all">Все главы</option>
                            @foreach($deadlineChapters as $chapter)<option value="{{ $chapter->id }}">{{ $chapter->name }}</option>@endforeach
                            @if($deadlineItems->contains('chapter', '0'))<option value="0">Без главы</option>@endif
                        </select>
                        <small class="gc-help-text" role="status" data-deadline-count>Показано {{ $deadlineItems->count() }} из {{ $deadlineItems->count() }}</small>
                    </div>
                </details>
            @endif
            @if(!$isManager && \Illuminate\Support\Facades\Schema::hasTable('course_notification_preferences'))
                <details class="gc-deadline-reminders">
                    <summary><i class="far fa-bell" aria-hidden="true"></i> Напоминания</summary>
                    <form class="gc-deadline-reminders__form" method="POST" action="{{ url('/insider/courses/'.$course->id.'/notifications/preference') }}">@csrf
                        <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="enabled" value="1" @checked(\Illuminate\Support\Facades\DB::table('course_notification_preferences')->where('course_id',$course->id)->where('user_id',auth()->id())->value('enabled'))><span class="form-check-label">Напоминать мне о сроках</span></label>
                        <button class="btn btn-outline-secondary btn-sm">Сохранить</button>
                        @if(!auth()->user()->telegram_chat_id)<a class="gc-help-text" href="{{ url('/insider/profile/'.auth()->id().'/edit?section=account') }}">Подключить Telegram в личных настройках</a>@endif
                    </form>
                </details>
            @endif
        </section>

        @if($deadlineItems->isEmpty())
            <div class="gc-card gc-empty-state">
                <div class="gc-empty-icon"><i class="fas fa-calendar-check" aria-hidden="true"></i></div>
                <h3 class="h6">{{ $isManager ? 'Дедлайнов пока нет' : 'Нет невыполненных задач со сроками' }}</h3>
                <p class="mx-auto mb-0">{{ $isManager ? 'Здесь появятся сроки задач из доступных вам уроков курса.' : 'Здесь появятся невыполненные задачи со сроками сдачи из доступных уроков.' }}</p>
            </div>
        @else
            <section class="gc-deadline-agenda" aria-label="Задачи и условия сдачи" data-deadline-agenda>
                <ul class="list-unstyled mb-0" data-deadline-list>
                    @foreach($deadlineItems as $item)
                        @php($task = $item['task'])
                        <li class="gc-card gc-deadline-task" data-deadline-row data-deadline-task-id="{{ $task->id }}" data-deadline-next-cutoff="{{ $item['next'] ?? '' }}" data-deadline-last="{{ $item['last']?->getTimestampMs() ?? 0 }}" data-deadline-chapter-id="{{ $item['chapter'] }}" data-deadline-search-text="{{ $task->name.' '.$task->step->name.' '.$task->step->lesson->name }}">
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
            <div class="gc-card gc-empty-state" data-deadline-empty hidden>
                <div class="gc-empty-icon"><i class="fas fa-search" aria-hidden="true"></i></div>
                <h3 class="h6">Задачи не найдены</h3>
                <p>Попробуйте другое название или измените фильтр.</p>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-deadline-reset>Сбросить поиск и фильтр</button>
            </div>
        @endif
    </div>
@endsection
