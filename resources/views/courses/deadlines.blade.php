@extends('layouts.left-menu')

@section('title', 'Дедлайны — '.$course->name)

@section('content')
    @php
        $pendingCount = $deadlineItems->where('done', false)->count();
        $penaltyCount = $deadlineItems->where('done', false)->where('penalty', '>', 0)->count();
        $upcomingCount = $deadlineItems->whereNotNull('next')->count();
        $filters = $isManager
            ? ['all' => ['Все задачи', $deadlineItems->count()], 'upcoming' => ['Есть следующий срок', $upcomingCount], 'penalty' => ['Со штрафом', $penaltyCount]]
            : ['all' => ['Все задачи', $deadlineItems->count()], 'pending' => ['К выполнению', $pendingCount], 'penalty' => ['Со штрафом', $penaltyCount], 'done' => ['Выполнено', $deadlineItems->count() - $pendingCount]];
    @endphp
    <div class="container-fluid px-0" data-deadline-workspace>
        <x-gc-course-header :course="$course" active="deadlines" />
        <section class="gc-card gc-deadline-controls" aria-labelledby="deadline-title">
            <div class="gc-deadline-controls__heading">
                <div class="min-width-0">
                    <h2 class="gc-section-title mb-1" id="deadline-title">Дедлайны</h2>
                    <p class="gc-help-text mb-0">Штраф сейчас и следующее изменение. Все сроки — по нажатию на условия сдачи.</p>
                </div>
                @if($deadlineItems->isNotEmpty())<span class="gc-help-text" role="status" aria-live="polite" data-deadline-count>Показано {{ $deadlineItems->count() }} из {{ $deadlineItems->count() }}</span>@endif
            </div>
            @if($deadlineItems->isNotEmpty())
                <div class="gc-deadline-tools">
                    <label class="input-group gc-search-box gc-deadline-search">
                        <span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
                        <input class="form-control" type="search" placeholder="Найти задачу или урок" aria-label="Поиск по дедлайнам" maxlength="120" data-deadline-search>
                        <button class="btn d-none" type="button" data-deadline-clear aria-label="Очистить поиск"><i class="fas fa-times" aria-hidden="true"></i></button>
                    </label>
                    <label class="gc-workspace-field gc-deadline-chapter"><span class="visually-hidden">Глава</span><select class="form-select" aria-label="Глава" data-deadline-chapter><option value="all">Все главы</option>@foreach($deadlineChapters as $chapter)<option value="{{ $chapter->id }}">{{ $chapter->name }}</option>@endforeach @if($deadlineItems->contains('chapter', '0'))<option value="0">Без главы</option>@endif</select></label>
                </div>
                <div class="gc-deadline-filters" role="group" aria-label="Условия сдачи">
                    @foreach($filters as $key => [$label, $count])
                        <button class="gc-filter-toggle" type="button" data-deadline-filter="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">{{ $label }} <span class="gc-help-text" data-deadline-filter-count>{{ $count }}</span></button>
                    @endforeach
                </div>
            @endif
            @if(!$isManager && \Illuminate\Support\Facades\Schema::hasTable('course_notification_preferences'))
                <details class="gc-disclosure gc-disclosure--inline gc-deadline-reminders">
                    <summary><i class="far fa-bell" aria-hidden="true"></i> Напоминания в Telegram</summary>
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
                <h3 class="h6">Дедлайнов пока нет</h3>
                <p class="mx-auto mb-0">Здесь появятся сроки задач из доступных вам уроков курса.</p>
            </div>
        @else
            <section class="gc-card gc-deadline-agenda" aria-label="Задачи и условия сдачи" data-deadline-agenda>
                <div class="gc-deadline-agenda__columns" aria-hidden="true"><span>Задача и урок</span><span>Условия новых сдач</span></div>
                <ul class="list-unstyled mb-0" data-deadline-list>
                    @foreach($deadlineItems as $item)
                        @php($task = $item['task'])
                        <li class="gc-deadline-task" data-deadline-row data-deadline-task-id="{{ $task->id }}" data-deadline-done="{{ $item['done'] ? 'true' : 'false' }}" data-deadline-penalty="{{ $item['penalty'] }}" data-deadline-next-cutoff="{{ $item['next'] ?? '' }}" data-deadline-last="{{ $item['last']?->getTimestampMs() ?? 0 }}" data-deadline-chapter-id="{{ $item['chapter'] }}" data-deadline-search-text="{{ $task->name.' '.$task->step->name.' '.$task->step->lesson->name }}">
                            <a class="gc-deadline-task__body" href="{{ url('/insider/courses/'.$course->id.'/steps/'.$task->step_id.'#task'.$task->id) }}">
                                <strong>{{ $task->name }}</strong>
                                <small>{{ $task->step->lesson->name }}@if($task->step->name !== $task->step->lesson->name)<span aria-hidden="true"> · </span>{{ $task->step->name }}@endif</small>
                            </a>
                            <div class="gc-deadline-task__conditions">
                                @if($item['done'])<span class="gc-deadline-task__state gc-deadline-task__state--done"><i class="fas fa-check" aria-hidden="true"></i> Выполнено</span>@endif
                                <x-gc-deadline-chain :rule="$item['rule']" note="Условия применяются к новым сдачам. Уже полученные баллы сохраняются. Сдавать можно и после последнего срока." />
                                @if($item['last'])<small class="gc-deadline-task__last gc-help-text" @if($item['next'] !== null) hidden @endif data-deadline-last-label>Последний срок <time datetime="{{ $item['last']->toIso8601String() }}">{{ $item['last']->format('d.m.Y H:i') }}</time></small>@endif
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
