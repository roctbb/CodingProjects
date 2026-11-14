@extends('layouts.left-menu')
@section('title', 'Журнал')
@section('content')
    @php
        $journalLessons = $assessmentLessons->filter(fn ($lesson) => $lesson->steps->flatMap->tasks->isNotEmpty())->values();
        $tasks = $journalLessons->flatMap(fn ($lesson) => $lesson->steps->flatMap->tasks);
        $assessmentStates = [
            'empty' => ['Без баллов', 'fa-minus'], 'partial' => ['Частично', 'fa-circle-half-stroke'],
            'complete' => ['Выполнено', 'fa-circle-check'], 'review' => ['На проверке', 'fa-hourglass-half'],
            'blocked' => ['Заблокировано', 'fa-lock'],
        ];
        $journalRows = $course->students->map(function ($student) use ($tasks, $blockedTaskMap, $pendingReviewPairs) {
            $attempts = $student->submissions->groupBy('task_id');
            $cells = $tasks->mapWithKeys(function ($task) use ($student, $attempts, $blockedTaskMap, $pendingReviewPairs) {
                $best = \App\Solution::bestScoredIn($attempts->get($task->id, collect()));
                $earned = \App\Solution::bestScoredIn($attempts->get($task->id, collect())->filter(fn ($s) => $s->evaluatedMark() > 0));
                $blocked = isset($blockedTaskMap[$student->id.':'.$task->id]);
                $mark = $blocked ? 0 : ($best?->mark ?? 0);
                $earnedMark = $blocked ? 0 : ($earned?->mark ?? 0);
                $review = !$blocked && isset($pendingReviewPairs[$student->id.':'.$task->id]);
                $state = $blocked ? 'blocked' : ($review ? 'review' : ($task->max_mark > 0 && $earnedMark >= $task->max_mark ? 'complete' : ($mark > 0 ? 'partial' : 'empty')));
                return [$task->id => compact('mark', 'earnedMark', 'state')];
            });
            return compact('student', 'cells');
        });
    @endphp
    <x-gc-course-header :course="$course" :chapter="$assessmentChapter" active="journal" />
    <div class="gc-stack" data-assessment-workspace>
        <x-gc-workspace-toolbar class="assessment-toolbar" role="group" aria-label="Фильтры журнала">
            <div class="gc-journal-controls">
                @if($course->program->chapters->count() > 1)
                <form method="GET" class="gc-workspace-field gc-journal-chapter">
                    <label for="assessment-chapter" class="form-label mb-0">Глава</label>
                    <select name="chapter" id="assessment-chapter" class="form-select" onchange="this.form.submit()" @disabled($course->program->chapters->isEmpty())>
                        @forelse($course->program->chapters as $chapter)
                            <option value="{{ $chapter->id }}" @selected($assessmentChapter?->id === $chapter->id)>{{ $chapter->name }}</option>
                        @empty<option>Глав пока нет</option>@endforelse
                    </select>
                    <noscript><button class="btn btn-outline-secondary btn-sm">Показать</button></noscript>
                </form>
                @endif
                <div class="input-group input-group-sm gc-search-box assessment-student-search">
                    <span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
                    <input type="search" class="form-control" placeholder="Найти ученика" aria-label="Найти ученика" data-assessment-student-search data-assessment-table="#assessment-table">
                    <button class="btn d-none" type="button" data-assessment-student-clear aria-label="Очистить поиск"><i class="fas fa-times" aria-hidden="true"></i></button>
                    <span class="input-group-text gc-search-box__count" data-assessment-student-count aria-live="polite">{{ $course->students->count() }} из {{ $course->students->count() }}</span>
                </div>
            </div>
            @if($tasks->isNotEmpty())
                <p class="visually-hidden" id="assessment-filter-help">Фильтр выбирает учеников по работам всей главы.</p>
                <div class="gc-journal-mobile-tools"><label class="gc-journal-mobile-filter"><span class="visually-hidden">Работы</span><select class="form-select" data-assessment-state-picker aria-label="Ученики по состоянию работ главы" aria-describedby="assessment-filter-help"><option value="all" data-label="Все ученики">Все ученики</option>@foreach($assessmentStates as $key => [$label, $icon])<option value="{{ $key }}" data-label="{{ $label }}">{{ $label }}</option>@endforeach</select></label><button class="btn btn-outline-secondary btn-sm" type="button" data-assessment-open-detail aria-label="Перейти к задачам выбранного урока">К задачам</button></div>
                <div class="gc-status-filters gc-journal-state-buttons" role="group" aria-label="Ученики по состоянию всех работ главы">
                    <button class="gc-filter-toggle" type="button" data-assessment-state-filter="all" aria-pressed="true">Все</button>
                    @foreach($assessmentStates as $state => [$label, $icon])
                        <button class="gc-filter-toggle" type="button" data-assessment-state-filter="{{ $state }}" aria-pressed="false"><i class="fas {{ $icon }} gc-assessment-state--{{ $state }}" aria-hidden="true"></i>{{ $label }} <span class="gc-help-text" data-assessment-state-count>0</span></button>
                    @endforeach
                </div>
            @endif
        </x-gc-workspace-toolbar>
        @if($tasks->isEmpty())
            <div class="gc-card p-4"><p class="mb-0 text-muted">В этой главе пока нет задач.</p></div>
        @else
            <details class="gc-journal-overview" data-assessment-overview open>
                <summary class="gc-summary-line"><h2 class="gc-section-title mb-0" id="assessment-overview-title">{{ $course->program->chapters->count() > 1 ? 'Итоги главы' : 'Итоги уроков' }}</h2><span class="gc-help-text">{{ $journalLessons->count() }} уроков · {{ $tasks->count() }} задач <i class="fas fa-chevron-down ms-2" aria-hidden="true"></i></span></summary>
                <div class="gc-card overflow-hidden">
                    <div class="table-responsive assessment-table-wrap" role="region" aria-label="Итоги уроков главы, прокручивается по горизонтали" tabindex="0">
                        <table class="table table-sm align-middle assessment-table gc-data-table mb-0" id="assessment-table">
                            <thead><tr><th class="assessment-sticky-col" scope="col">Ученик</th>
                                @foreach($journalLessons as $lesson)
                                    @php $lessonTasks = $lesson->steps->flatMap->tasks; @endphp
                                    <th class="assessment-lesson-head" scope="col" data-assessment-column="{{ $lesson->id }}" data-table-column>
                                        <button type="button" class="gc-text-action gc-journal-lesson" title="{{ $lesson->name }}" data-assessment-lesson-select="{{ $lesson->id }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" aria-controls="assessment-detail-content">
                                            <span>{{ $lesson->name }}</span><small>{{ $lessonTasks->count() }} задач · {{ $lessonTasks->sum('max_mark') }} XP <i class="fas fa-angle-down" aria-hidden="true"></i></small>
                                        </button>
                                    </th>
                                @endforeach
                                <th class="assessment-total-head" scope="col">Всего XP</th>
                            </tr></thead>
                            <tbody>
                                @foreach($journalRows as $journalRow)
                                    @php
                                        $student = $journalRow['student'];
                                        $cells = $journalRow['cells'];
                                    @endphp
                                    <tr data-assessment-row="{{ $student->id }}" data-assessment-states="{{ $cells->pluck('state')->unique()->implode(' ') }}" data-assessment-student-name="{{ $student->name }} {{ $student->activeCustomTitle() }}">
                                        <th scope="row" class="assessment-sticky-col assessment-student-cell"><a class="gc-quiet-link" href="{{ url('/insider/profile/'.$student->id) }}">{{ $student->name }}</a></th>
                                        @foreach($journalLessons as $lesson)
                                            @php
                                                $lessonTasks = $lesson->steps->flatMap->tasks;
                                                $lessonCells = $cells->only($lessonTasks->pluck('id')->all());
                                                $lessonMark = $lessonCells->sum('mark');
                                                $lessonEarnedMark = $lessonCells->sum('earnedMark');
                                                $lessonMax = $lessonTasks->sum('max_mark');
                                                $lessonStates = $lessonCells->pluck('state');
                                                $lessonState = $lessonStates->contains('review') ? 'review' : ($lessonStates->every(fn ($state) => $state === 'blocked') ? 'blocked' : ($lessonMax > 0 && $lessonEarnedMark >= $lessonMax ? 'complete' : ($lessonMark > 0 ? 'partial' : 'empty')));
                                            @endphp
                                            <td data-assessment-column="{{ $lesson->id }}"><button type="button" class="gc-text-action gc-assessment-cell-action" data-assessment-lesson-select="{{ $lesson->id }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" aria-controls="assessment-detail-content" aria-label="{{ $student->name }} · {{ $lesson->name }}: {{ $lessonMark }} из {{ $lessonMax }}, {{ $assessmentStates[$lessonState][0] }}. Показать задачи ниже"><x-gc-assessment-score :mark="$lessonMark" :maximum="$lessonMax" :state="$lessonState" :label="$assessmentStates[$lessonState][0]" /></button></td>
                                        @endforeach
                                        <td class="assessment-total-cell">{{ $cells->sum('mark') }}</td>
                                    </tr>
                                @endforeach
                                <tr data-assessment-empty @if($journalRows->isNotEmpty()) hidden @endif><td colspan="{{ $journalLessons->count() + 2 }}" class="text-muted text-center py-4">{{ $journalRows->isEmpty() ? 'В курсе пока нет учеников.' : 'Ничего не найдено.' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <x-gc-table-navigation target="assessment-table" label="Уроки главы" />
                </div>
                <p class="gc-help-text mb-0">Выберите урок — его задачи появятся ниже. <i class="fas fa-hourglass-half" aria-hidden="true"></i> — ожидает проверки.</p>
            </details>
            <section class="gc-stack gc-stack--compact gc-journal-detail" aria-label="Разбор выбранного урока" data-assessment-detail tabindex="-1">
                <div class="gc-summary-line gc-journal-detail__heading">
                    <h2 class="gc-section-title mb-0" tabindex="-1" data-assessment-detail-focus>Задачи урока</h2>
                    <button class="gc-text-action gc-journal-back" type="button" data-assessment-back hidden>К итогам главы</button>
                    <span class="visually-hidden" data-assessment-detail-title aria-live="polite">{{ $journalLessons->first()->name }}</span>
                    @if($journalLessons->count() > 1)
                        <label class="visually-hidden" for="assessment-lesson-picker">Урок для разбора</label>
                        <select id="assessment-lesson-picker" class="form-select form-select-sm gc-view-select" data-assessment-lesson-picker>
                            @foreach($journalLessons as $lesson)<option value="{{ $lesson->id }}">{{ $lesson->name }}</option>@endforeach
                        </select>
                    @else
                        <span class="gc-help-text">{{ $journalLessons->first()->name }}</span>
                    @endif
                </div>
                <div class="gc-journal-student-focus" data-assessment-student-focus hidden><span data-assessment-selected-student aria-live="polite"></span><button class="gc-text-action" type="button" data-assessment-clear-student>Снять выделение</button></div>
                <div id="assessment-detail-content" data-assessment-detail-content>
                    @include('courses.partials.assessment-detail', ['lesson' => $journalLessons->first()])
                </div>
            </section>
            @foreach($journalLessons as $lesson)
                <template data-assessment-detail-template="{{ $lesson->id }}" data-lesson-name="{{ $lesson->name }}">@include('courses.partials.assessment-detail')</template>
            @endforeach
        @endif
    </div>
@endsection
