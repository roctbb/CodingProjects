@extends('layouts.left-menu')

@section('title')
    {{$course->name}}
@endsection

@section('tabs')

@endsection

@section('head')
    <meta name="gc-plotly-src" content="{{ asset('build/js/vendor/plotly.min.js') }}">
@endsection



@section('content')
    @php
        $displayPercent = fn ($percent) => min(100, max(0, (float) $percent));
        $chapterLookup = $course->program->chapters->keyBy('id');
        $riskToneClasses = [
            'high' => 'report-risk-badge--high',
            'medium' => 'report-risk-badge--medium',
            'low' => 'report-risk-badge--low',
            'none' => 'report-risk-badge--none',
            'ai' => 'report-risk-badge--ai',
        ];
        $riskLabels = [
            'high' => 'высокий риск',
            'medium' => 'средний риск',
            'low' => 'низкий риск',
            'none' => 'нет данных о риске',
        ];
        $defaultReportChapter = collect($reportChapterGroups)->first(fn ($group) => $group['chapter']?->id === $course->default_chapter_id)
            ?? collect($reportChapterGroups)->first();
        $requestedReportChapter = $course->program->chapters->firstWhere('id', request('chapter'));
        $defaultReportChapterId = $requestedReportChapter ? (string) $requestedReportChapter->id
            : ($defaultReportChapter ? (string) ($defaultReportChapter['chapter']?->id ?? 0) : 'all');
        $requestedChapterHasTasks = !$requestedReportChapter || collect($reportChapterGroups)->contains(fn ($group) => $group['chapter']?->id === $requestedReportChapter->id);
    @endphp
    <div class="container-fluid px-0" data-report-workspace data-report-default-chapter="{{ $defaultReportChapterId }}">
        <x-gc-course-header :course="$course" :chapter="request('chapter', $defaultReportChapterId)" active="report" />
        <h2 class="visually-hidden">Отчёт</h2>
        @if($students->isEmpty())
            <div class="gc-card p-4">
                <h2 class="gc-section-title">{{ request('attention') === '1' ? 'По этому фильтру учеников нет' : 'Учеников пока нет' }}</h2>
                <p class="text-muted mb-0">{{ request('attention') === '1' ? 'По выбранному условию никто не требует внимания.' : 'Отчёт появится, когда к курсу присоединятся ученики.' }}</p>
                @if(request('attention') === '1')<a class="btn btn-outline-secondary btn-sm mt-3" href="{{ url('/insider/courses/'.$course->id.'/report') }}">Показать всех учеников</a>@endif
            </div>
        @else
    <x-gc-workspace-toolbar class="gc-report-controls {{ $lessons->isEmpty() ? 'gc-report-controls--empty' : '' }}" role="group" aria-label="Глава и фильтры уроков">
        <div class="gc-report-mobile-student">
            <button class="btn btn-outline-secondary gc-report-student-picker" type="button" data-bs-toggle="offcanvas" data-bs-target="#report-students-picker" aria-controls="report-students-picker" aria-haspopup="dialog" aria-label="Выбрать ученика"><i class="fas fa-search" aria-hidden="true"></i><span data-report-selected-name>{{ $students->first()->name }}</span><i class="fas fa-chevron-down" aria-hidden="true"></i></button>
            <button class="btn btn-outline-secondary gc-icon-button" type="button" data-report-previous aria-label="Предыдущий ученик"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
            <button class="btn btn-outline-secondary gc-icon-button" type="button" data-report-next aria-label="Следующий ученик"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
        </div>
        <label class="gc-workspace-field gc-report-chapter-filter" @if($lessons->isEmpty()) hidden @endif><span>Глава</span><select class="form-select form-select-sm" data-report-chapter-filter><option value="all">Все главы</option>@foreach($reportChapterGroups as $group)<option value="{{ $group['chapter']?->id ?? 0 }}">{{ $group['chapter']?->name ?? 'Без главы' }}</option>@endforeach @unless($requestedChapterHasTasks)<option value="{{ $requestedReportChapter->id }}">{{ $requestedReportChapter->name }} (нет заданий)</option>@endunless</select></label>
        <label class="gc-workspace-field gc-report-state-filter" @if($lessons->isEmpty()) hidden @endif><span>Уроки</span><select class="form-select form-select-sm" data-report-lesson-state><option value="all" data-label="Все уроки">Все уроки</option><option value="incomplete" data-label="Не завершены">Не завершены</option><option value="review" data-label="На проверке">На проверке</option><option value="complete" data-label="Завершены">Завершены</option></select></label>
        <div class="input-group input-group-sm gc-search-box gc-report-lesson-search" @if($lessons->isEmpty()) hidden @endif><span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span><input type="search" class="form-control" placeholder="Найти урок" aria-label="Найти урок в выбранной главе" maxlength="120" data-report-lesson-search><button class="btn d-none" type="button" data-report-lesson-clear aria-label="Очистить поиск уроков"><i class="fas fa-times" aria-hidden="true"></i></button></div>
    </x-gc-workspace-toolbar>
    <div class="gc-master-detail gc-report-workspace gc-roster-workspace">
        <x-gc-roster id="report-students-picker" labelledby="report-students-title" class="gc-report-roster">
            <div class="gc-card gc-roster-surface report-students-card overflow-hidden">
                <div class="gc-section-header gc-section-header--between report-students-card__header">
                    <div class="min-width-0">
                        <h6 class="mb-0" id="report-students-title">Ученики</h6>
                        <span class="text-muted small">Сводка по прогрессу</span>
                    </div>
                    <span class="badge rounded-pill bg-body-tertiary report-student-search-count flex-shrink-0" data-report-student-count>{{ $students->count() }} из {{ $students->count() }}</span>
                    <button class="btn-close d-md-none" type="button" data-bs-dismiss="offcanvas" data-bs-target="#report-students-picker" aria-label="Закрыть выбор ученика"></button>
                </div>
                <form class="gc-roster-filters" method="GET"><input type="hidden" name="chapter" value="{{ request('chapter', $defaultReportChapterId) }}" data-report-chapter-value><input type="hidden" name="lesson_state" data-report-lesson-state-value><input type="hidden" name="lesson_q" data-report-lesson-query-value><label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="attention" value="1" @checked(request('attention') === '1') onchange="this.form.submit()" aria-describedby="report-attention-help"> Требуют внимания</label><span class="visually-hidden" id="report-attention-help">Прогресс за курс ниже 50% или предупреждения по работам</span><a class="gc-text-action" href="{{ url('/insider/reviews?course='.$course->id) }}"><i class="fas fa-hourglass-half" aria-hidden="true"></i> На проверке: {{ $pendingSolutionsCount }}</a><noscript><button class="btn btn-outline-secondary btn-sm">Применить</button></noscript></form>
                <div class="p-2 report-student-search">
                    <div class="input-group input-group-sm gc-search-box">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="search" class="form-control" placeholder="Найти ученика" aria-label="Найти ученика" data-report-student-search data-report-student-list="#v-pills-tab">
                        <button class="btn d-none" type="button" data-report-student-clear aria-label="Очистить поиск"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="report-student-sort-row mt-2">
                        <label for="report-student-sort" class="small text-muted mb-0">Сортировка</label>
                        <select id="report-student-sort"
                                class="form-select form-select-sm report-student-sort"
                                data-report-student-sort
                                data-report-student-list="#v-pills-tab">
                            <option value="name">по имени</option>
                            <option value="percent">по проценту</option>
                            <option value="similarity">по списыванию</option>
                            <option value="llm">по LLM</option>
                        </select>
                    </div>
                </div>
                <div class="nav flex-column nav-pills p-2 gap-1 report-students-nav" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                    @foreach ($students as $key => $student)
                        @php
                            $studentDisplayPercent = $student->max_points > 0 ? $displayPercent($student->percent) : 0;
                            $studentProgressWidth = (int) round($studentDisplayPercent);
                            $studentProgressClass = $student->percent < 40 ? 'is-low' : ($student->percent < 60 ? 'is-mid' : 'is-high');
                            $navIntegrity = $geekPasteIntegrityStats[$student->id]['overall'] ?? null;
                            $navRiskLevel = $navIntegrity['risk_level'] ?? 'none';
                            $navRiskRank = ['none' => 0, 'low' => 1, 'medium' => 2, 'high' => 3][$navRiskLevel] ?? 0;
                            $navLlmProbability = $navIntegrity['max_llm_probability'] ?? -1;
                            $navSimilarityPercent = $navIntegrity['max_similarity_percent'] ?? -1;
                            $navLlmLabel = $navLlmProbability >= 0 ? 'LLM '.$navLlmProbability.'%' : 'LLM -';
                            $navSimilarityLabel = $navSimilarityPercent >= 0 ? 'схож. '.$navSimilarityPercent.'%' : 'схож. -';
                        @endphp
                        <a class="nav-link report-student-link @if ($key == 0) active @endif" id="student{{$student->id}}-tab" data-bs-toggle="pill"
                           href="#student{{$student->id}}" role="tab"
                           aria-controls="student{{$student->id}}" aria-selected="@if ($key == 0) true @else false @endif"
                           data-plotly-resize-target="pulse{{$student->id}}"
                           data-report-student-name="{{$student->name}} {{ $student->activeCustomTitle() }} {{ $student->points }} {{ $student->max_points }}" data-report-student-label="{{ $student->name }}"
                           data-report-student-sort-name="{{ mb_strtolower($student->name) }}"
                           data-report-student-sort-percent="{{ round($student->percent, 2) }}"
                           data-report-student-sort-risk="{{ $navRiskRank }}"
                           data-report-student-sort-llm="{{ $navLlmProbability }}"
                           data-report-student-sort-similarity="{{ $navSimilarityPercent }}">
                            <span class="min-width-0">
                                <span class="report-student-link__name">
                                    <span class="report-student-risk-slot">
                                    @if ($navRiskLevel === 'high' || $navRiskLevel === 'medium')
                                        <span class="report-student-risk-dot report-student-risk-dot--{{ $navRiskLevel }}"
                                              title="Анализ авторства: {{ $navRiskLevel === 'high' ? 'высокий риск' : 'средний риск' }}">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </span>
                                    @endif
                                    </span>
                                    <span class="text-truncate">{{$student->name}}</span>
                                    @include('profile.partials.custom_title_badge', ['profileUser' => $student, 'compact' => true])
                                </span>
                                <small class="report-student-link__meta">{{ $student->points }} / {{ $student->max_points }} XP</small>
                            </span>
                            <span class="report-nav-progress {{ $studentProgressClass }}" title="Прогресс: {{ round($studentDisplayPercent) }}%">
                                <span class="report-nav-progress__bar progress-width-{{$studentProgressWidth}}" data-progress-width="{{$studentDisplayPercent}}%"></span>
                                <span class="report-nav-progress__value" data-report-sort-value="percent">{{ round($studentDisplayPercent) }}%</span>
                                <span class="report-nav-progress__value d-none" data-report-sort-value="llm">{{ $navLlmLabel }}</span>
                                <span class="report-nav-progress__value d-none" data-report-sort-value="similarity">{{ $navSimilarityLabel }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
                <p class="gc-help-text text-center p-3" data-report-roster-empty hidden>Ученики не найдены. Попробуйте другое имя.</p>
            </div>
        </x-gc-roster>
        <div class="gc-master-detail__content">
            <div class="gc-card p-4" data-report-search-empty hidden><h2 class="h6">Ученики не найдены</h2><p class="gc-help-text">Измените имя или сбросьте поиск, чтобы вернуться к отчётам.</p><button type="button" class="btn btn-outline-secondary btn-sm" data-report-reset-search>Сбросить поиск</button></div>
            <div class="tab-content" id="v-pills-tabContent">
                @foreach ($students as $key => $student)
                    <div class="tab-pane fade show @if ($key == 0) active @endif" id="student{{$student->id}}"
                         role="tabpanel"
                         aria-labelledby="student{{$student->id}}-tab">

                        <div class="gc-card report-student-card overflow-hidden w-100" id="cardbody{{$student->id}}">
                            <div class="gc-section-header gc-section-header--between report-student-card__header">
                                <div class="report-student-head d-flex align-items-start justify-content-between gap-3">
                                    <div class="d-flex align-items-center gap-2 min-width-0">
                                        <x-gc-avatar :frame="false" :user="$student" size="md" class="flex-shrink-0" alt="" />
                                        <div class="min-width-0">
                                            <div class="d-flex flex-wrap align-items-center gap-1 mb-1 min-width-0">
                                                <h4 class="fw-medium mb-0">{{ $student->name }}</h4>
                                                @include('profile.partials.custom_title_badge', ['profileUser' => $student, 'compact' => true])
                                            </div>
                                            <small class="text-muted">{{ $student->points }} / {{ $student->max_points }} XP · весь курс</small>
                                        </div>
                                    </div>
                                    @php
                                        $studentDisplayPercent = $student->max_points > 0 ? $displayPercent($student->percent) : 0;
                                    @endphp
                                    <div class="report-chapter-score"><span class="gc-help-text" data-report-summary-label>В выбранной главе</span><strong data-report-summary-value>{{ $student->points }} / {{ $student->max_points }} XP</strong></div>
                                </div>
                            </div>
                            <div class="report-student-card__body">
                                @php
                                    $studentIntegrity = $geekPasteIntegrityStats[$student->id] ?? null;
                                    $overallIntegrity = $studentIntegrity['overall'] ?? null;
                                    $riskyChapterIntegrity = [];
                                    foreach (($studentIntegrity['chapters'] ?? []) as $chapterId => $chapterIntegrity) {
                                        if (($chapterIntegrity['synced'] ?? 0) > 0 && in_array($chapterIntegrity['risk_level'] ?? 'none', ['high', 'medium'])) {
                                            $riskyChapterIntegrity[$chapterId] = $chapterIntegrity;
                                        }
                                    }
                                @endphp
                                @if ($overallIntegrity && $overallIntegrity['synced'] > 0)
                                    @php
                                        $overallRiskTone = $riskToneClasses[$overallIntegrity['risk_level']] ?? $riskToneClasses['none'];
                                        $overallRiskLabel = $riskLabels[$overallIntegrity['risk_level']] ?? $overallIntegrity['risk_level'];
                                    @endphp
                                    <details class="report-integrity-summary {{ in_array($overallIntegrity['risk_level'], ['high', 'medium']) ? 'is-warning' : '' }}"><summary>Анализ авторства · {{ in_array($overallIntegrity['risk_level'], ['high', 'medium']) ? 'есть предупреждения' : 'нет активных предупреждений' }}</summary><div class="report-integrity-strip mb-3">
                                        <span class="text-muted small fw-medium">Академическая честность</span>
                                        <span class="report-integrity-icon {{ $overallRiskTone }}" title="Риск: {{ $overallRiskLabel }}">
                                            <i class="fas fa-shield-alt"></i>
                                        </span>
                                        @if ($overallIntegrity['max_llm_probability'] !== null)
                                            <span class="report-integrity-metric" title="LLM max {{ $overallIntegrity['max_llm_probability'] }}%">
                                                <i class="fas fa-robot"></i>
                                                <span>{{ $overallIntegrity['max_llm_probability'] }}%</span>
                                            </span>
                                        @endif
                                        @if ($overallIntegrity['max_similarity_percent'] !== null)
                                            <span class="report-integrity-metric" title="Схожесть max {{ $overallIntegrity['max_similarity_percent'] }}%">
                                                <i class="fas fa-copy"></i>
                                                <span>{{ $overallIntegrity['max_similarity_percent'] }}%</span>
                                            </span>
                                        @endif
                                        @if ($overallIntegrity['ai_warnings'] > 0)
                                            <span class="report-integrity-metric report-risk-badge--ai" title="AI флаги: {{ $overallIntegrity['ai_warnings'] }}">
                                                <i class="fas fa-robot"></i>
                                                <span>{{ $overallIntegrity['ai_warnings'] }}</span>
                                            </span>
                                        @endif
                                        @if ($overallIntegrity['similarity_warnings'] > 0)
                                            <span class="report-integrity-metric report-risk-badge--high" title="Подозрения на списывание: {{ $overallIntegrity['similarity_warnings'] }}">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <span>{{ $overallIntegrity['similarity_warnings'] }}</span>
                                            </span>
                                        @endif
                                        @if (($overallIntegrity['dismissed'] ?? 0) > 0)
                                            <span class="report-integrity-metric is-muted" title="Скрыто учителем: {{ $overallIntegrity['dismissed'] }}">
                                                <i class="fas fa-eye-slash"></i>
                                                <span>{{ $overallIntegrity['dismissed'] }}</span>
                                            </span>
                                        @endif
                                        @if ($overallIntegrity['risk_level'] !== 'low' && $overallIntegrity['risk_level'] !== 'none')
                                            <form method="POST"
                                                  action="{{ url('/insider/courses/'.$course->id.'/report/students/'.$student->id.'/geekpaste-warning/reset') }}"
                                                  class="ms-auto"
                                                  data-confirm="Снять предупреждение об авторстве по ученику? Оно больше не будет отображаться в отчёте.">
                                                @csrf
                                                <button type="submit" class="btn btn-sm report-integrity-reset">
                                                    <i class="fas fa-check"></i>
                                                    <span>Снять</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div></details>
                                @endif


                                <div class="report-section-head mb-2" @if($lessons->isEmpty()) hidden @endif>
                                    <h5 class="mb-0">Прогресс по урокам</h5>
                                    <span class="text-muted small" data-report-lesson-count>{{ $lessons->count() }} уроков</span>
                                </div>
                                <div class="report-lessons-list">
                                @foreach($reportChapterGroups as $chapterGroup)
                                    @php
                                        $groupChapter = $chapterGroup['chapter'];
                                        $groupLessons = $chapterGroup['lessons'];
                                        $chapterIntegrity = $studentIntegrity['chapters'][$groupChapter ? $groupChapter->id : 0] ?? null;
                                    @endphp
                                    <section class="report-chapter-group" data-report-chapter="{{ $groupChapter?->id ?? 0 }}">
                                    <div class="report-chapter-heading">
                                        <h6 class="mb-0">{{ $groupChapter ? $groupChapter->name : 'Без главы' }}</h6>
                                        @if ($chapterIntegrity && $chapterIntegrity['synced'] > 0 && $chapterIntegrity['risk_level'] !== 'low')
                                            @php
                                                $chapterRiskTone = $riskToneClasses[$chapterIntegrity['risk_level']] ?? $riskToneClasses['none'];
                                                $chapterSuspicionLabel = (($chapterIntegrity['similarity_warnings'] ?? 0) > 0 || $chapterIntegrity['max_similarity_percent'] !== null) ? 'списывание' : 'AI';
                                            @endphp
                                            <span class="report-suspicion-icon {{ $chapterRiskTone }}"
                                                  title="{{ $chapterSuspicionLabel }}">
                                                <i class="fas {{ $chapterSuspicionLabel === 'AI' ? 'fa-robot' : 'fa-exclamation-triangle' }}"></i>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="report-column-head" aria-hidden="true"><span>Урок / задание</span><span>Зачтено XP</span><span>Прогресс</span></div>
                                    @foreach($groupLessons as $lesson)
                                        @php
                                            $lessonStat = $lessonStats[$lesson->id][$student->id] ?? null;
                                            $lessonPercent = $lessonStat ? $lessonStat->percent : 0;
                                            $lessonDisplayPercent = ($lessonStat?->max_points ?? 0) > 0 ? $displayPercent($lessonPercent) : 0;
                                            $lessonPoints = $lessonStat ? $lessonStat->points : 0;
                                            $lessonMaxPoints = $lessonStat ? $lessonStat->max_points : 0;
                                            $lessonProgressWidth = (int) round($lessonDisplayPercent);
                                            $lessonProgressClass = $lessonPercent < 40 ? 'is-low' : ($lessonPercent < 60 ? 'is-mid' : 'is-high');
                                            $lessonIntegrity = $studentIntegrity['lessons'][$lesson->id] ?? null;
                                            $lessonHasIntegrityRisk = $lessonIntegrity && $lessonIntegrity['synced'] > 0 && $lessonIntegrity['risk_level'] !== 'low';
                                            $lessonStartDate = $lesson->getStartDate($course);
                                            $lessonPendingCount = $lesson->steps->flatMap->tasks->filter(fn ($task) => isset($pendingReviewPairs[$student->id.':'.$task->id]))->count();
                                            $lessonState = $lessonPendingCount ? 'review' : ($lessonMaxPoints > 0 && $lessonPoints >= $lessonMaxPoints ? 'complete' : ($lessonMaxPoints > 0 ? 'incomplete' : 'unscored'));
                                        @endphp

                                        <div class="report-lesson-row" data-report-points="{{ $lessonPoints }}" data-report-maximum="{{ $lessonMaxPoints }}" data-report-lesson-id="{{ $lesson->id }}" data-report-lesson-state-value="{{ $lessonState }}" data-report-lesson-name="{{ $lesson->name }}">
                                            <button type="button" class="report-lesson-main report-lesson-link" data-bs-toggle="collapse" data-bs-target="#student{{$student->id}}marks{{$lesson->id}}" aria-expanded="false" aria-controls="student{{$student->id}}marks{{$lesson->id}}">
                                                <span class="report-lesson-info min-width-0">
                                                    <span class="report-lesson-title">
                                                        <span class="report-lesson-link__name">{{$lesson->name}}</span>
                                                    </span>
                                                    <span class="report-lesson-meta">
                                                        @if($lessonState === 'review')<span class="gc-lesson-state gc-lesson-state--review"><i class="fas fa-hourglass-half" aria-hidden="true"></i> На проверке: {{ $lessonPendingCount }}</span>
                                                        @elseif($lessonState === 'complete')<span class="gc-lesson-state"><i class="fas fa-check" aria-hidden="true"></i> Завершено</span>
                                                        @elseif($lessonState === 'incomplete')<span class="gc-lesson-state">{{ $lessonPoints > 0 ? 'В работе' : 'Без баллов' }}</span>@endif
                                                        @if (!$lesson->isAvailableForUser($course, $student))
                                                            <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle report-lesson-lock">закрыт</span>
                                                            @if($lessonStartDate)<span class="report-lesson-date">до {{ $lessonStartDate->format('d.m.Y') }}</span>@endif
                                                        @endif
                                                    @if ($lessonHasIntegrityRisk)
                                                        <span class="report-lesson-risk-slot">
                                                            @php
                                                                $lessonRiskTone = $riskToneClasses[$lessonIntegrity['risk_level']] ?? $riskToneClasses['none'];
                                                                $lessonSuspicionLabel = (($lessonIntegrity['similarity_warnings'] ?? 0) > 0 || $lessonIntegrity['max_similarity_percent'] !== null) ? 'списывание' : 'AI';
                                                            @endphp
                                                            <span class="report-suspicion-icon {{ $lessonRiskTone }}"
                                                                  title="{{ $lessonSuspicionLabel }}">
                                                                <i class="fas {{ $lessonSuspicionLabel === 'AI' ? 'fa-robot' : 'fa-exclamation-triangle' }}"></i>
                                                            </span>
                                                        </span>
                                                    @endif
                                                    </span>
                                                </span>
                                                <span class="report-lesson-metrics">
                                                    <span class="report-lesson-score">
                                                        @if($lessonMaxPoints > 0)
                                                            {{$lessonPoints}} / {{$lessonMaxPoints}} XP
                                                        @else
                                                            Без оценки
                                                        @endif
                                                    </span>
                                                    @if($lessonMaxPoints > 0)
                                                    <span class="report-score-progress {{ $lessonProgressClass }}"
                                                         role="progressbar"
                                                         aria-valuenow="{{$lessonProgressWidth}}"
                                                         aria-valuemin="0"
                                                         aria-valuemax="100"
                                                         aria-label="{{$lesson->name}}: {{$lessonPoints}} / {{$lessonMaxPoints}}">
                                                        <span class="report-score-progress__bar progress-width-{{$lessonProgressWidth}}" data-progress-width="{{$lessonDisplayPercent}}%"></span>
                                                    </span>
                                                    @endif
                                                </span>
                                                <i class="fas fa-chevron-down report-lesson-link__icon" aria-hidden="true"></i>
                                            </button>

                                            <div class="collapse report-lesson-details" id="student{{$student->id}}marks{{$lesson->id}}">
                                                <template data-report-task-template><ul class="report-task-list list-unstyled mb-0">
                                                @php $stepsWithTasks = $lesson->steps->filter(fn ($step) => $step->tasks->isNotEmpty()); @endphp
                                                @foreach($stepsWithTasks as $step)
                                                    @php
                                                        $tasks = $step->tasks;
                                                    @endphp
                                                    @if($stepsWithTasks->count() > 1)<li class="report-task-step">{{ $step->name }}</li>@endif
                                                    @foreach($tasks as $task)
                                                        @php
                                                            $filtered = $task->solutions->filter(function ($value) use ($student) {
                                                                return $value->user_id == $student->id;
                                                            });
                                                            $bestSolution = \App\Solution::bestScoredIn($filtered);
                                                            $mark = $bestSolution ? $bestSolution->mark : 0;
                                                            $latestSolution = $filtered->whereNotNull('submitted')->sortByDesc('submitted')->first();
                                                            $scoreNotes = [];
                                                            if ($latestSolution?->hasActiveDeadlinePenalty() && $latestSolution->id !== $bestSolution?->id) {
                                                                $scoreNotes[] = 'Последняя сдача: '.$latestSolution->mark.' XP, штраф −'.$latestSolution->deadline_penalty_amount.' XP. Зачтена лучшая сдача';
                                                            } elseif ($bestSolution?->hasActiveDeadlinePenalty()) {
                                                                $scoreNotes[] = 'Применён штраф: −'.$bestSolution->deadline_penalty_amount.' XP';
                                                            } elseif ($bestSolution?->isDeadlinePenaltyRemoved()) {
                                                                $scoreNotes[] = 'Штраф снят';
                                                            }
                                                            if (($bestSolution?->xp_booster_amount ?? 0) > 0) $scoreNotes[] = 'Бустер: +'.$bestSolution->xp_booster_amount.' баллов до штрафа';
                                                            $should_check = isset($pendingReviewPairs[$student->id.':'.$task->id]);
                                                            $taskIntegrity = $studentIntegrity['tasks'][$task->id] ?? null;
                                                        @endphp
                                                        <li class="report-task-row">
                                                            <div class="report-task-name"><i class="fas {{ $task->is_code ? 'fa-code' : ($task->is_quiz ? 'fa-circle-question' : 'fa-pen') }}" aria-hidden="true"></i><div><a class="report-task-link text-decoration-none" title="{{ $task->name }}"
                                                               href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id)}}">{{$task->name}}</a>
                                                            </div></div>

                                                            <span class="report-task-meta">
                                                                @php $blocked = $task->isBlocked($student->id, $course->id); @endphp
                                                                @if ($taskIntegrity && $taskIntegrity['synced'] > 0 && $taskIntegrity['risk_level'] !== 'low')
                                                                    @php
                                                                        $taskRiskTone = $riskToneClasses[$taskIntegrity['risk_level']] ?? $riskToneClasses['none'];
                                                                        $taskSuspicionLabel = (($taskIntegrity['similarity_warnings'] ?? 0) > 0 || $taskIntegrity['max_similarity_percent'] !== null) ? 'списывание' : 'AI';
                                                                        $taskRiskTitle = trim(implode(' · ', array_filter([
                                                                            $taskIntegrity['max_llm_probability'] !== null ? 'LLM '.$taskIntegrity['max_llm_probability'].'%' : null,
                                                                            $taskIntegrity['max_similarity_percent'] !== null ? 'схожесть '.$taskIntegrity['max_similarity_percent'].'%' : null,
                                                                            $taskIntegrity['ai_warnings'] > 0 ? 'AI флаги '.$taskIntegrity['ai_warnings'] : null,
                                                                            $taskIntegrity['similarity_warnings'] > 0 ? 'списывание '.$taskIntegrity['similarity_warnings'] : null,
                                                                        ])));
                                                                    @endphp
                                                                    <a class="report-task-suspicion-dot {{ $taskRiskTone }}" href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id)}}"
                                                                          aria-label="Анализ авторства: {{ $task->name }}" title="{{ $taskSuspicionLabel }}{{ $taskRiskTitle ? ': '.$taskRiskTitle : '' }}">
                                                                        <i class="fas {{ $taskSuspicionLabel === 'AI' ? 'fa-robot' : 'fa-exclamation-triangle' }}" aria-hidden="true"></i>
                                                                    </a>
                                                                @endif
                                                                @if ($blocked)
                                                                    <span class="report-task-mark report-task-mark--blocked" title="Заблокировано"><i class="fas fa-lock me-1" aria-hidden="true"></i>0 / {{$task->max_mark}}<span class="visually-hidden"> · Заблокировано</span></span>
                                                                @elseif ($should_check)
                                                                    <span class="report-task-mark report-task-mark--review" title="На проверке"><i class="fas fa-hourglass-half me-1" aria-hidden="true"></i>{{$mark}} / {{$task->max_mark}}<span class="visually-hidden"> · На проверке</span></span>
                                                                @else
                                                                    <span class="report-task-mark {{ $scoreNotes ? 'report-task-mark--modified' : '' }}">{{$mark}} / {{$task->max_mark}}</span>
                                                                @endif
                                                            </span>
                                                            <a class="report-task-open" href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id)}}" aria-label="Открыть решение: {{ $task->name }}"><span>Открыть</span> <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                                                            @if($scoreNotes)<span class="report-task-adjustment {{ $latestSolution?->hasActiveDeadlinePenalty() || $bestSolution?->hasActiveDeadlinePenalty() ? 'is-penalty' : '' }}">{{ implode(' · ', $scoreNotes) }}</span>@endif
                                                        </li>
                                                    @endforeach
                                                @endforeach
                                                </ul></template>
                                            </div>
                                        </div>
                                    @endforeach
                                    </section>
                                @endforeach
                                </div>
                                <div class="gc-result-empty" data-report-lessons-empty hidden>
                                    @if($lessons->isEmpty())
                                        <h6>Пока нет открытых уроков с заданиями</h6><p class="gc-help-text mb-0">Уроки только с теорией остаются в программе курса. Здесь появятся результаты по заданиям.</p>
                                    @else
                                        <h6>По этим условиям уроков нет</h6><p class="gc-help-text">Измените главу, состояние или название урока.</p><button type="button" class="btn btn-outline-secondary btn-sm" data-report-reset-lessons>Сбросить фильтры уроков</button>
                                    @endif
                                </div>
                                @if (!empty($riskyChapterIntegrity))
                                    <div class="report-section-head mt-4 mb-2">
                                        <h5 class="mb-0">Риски по главам</h5>
                                        <span class="text-muted small">{{ count($riskyChapterIntegrity) }} с риском</span>
                                    </div>
                                    <div class="report-chapter-risk-list mb-3">
                                        @foreach($riskyChapterIntegrity as $chapterId => $chapterIntegrity)
                                            @php
                                                $chapterRiskTone = $riskToneClasses[$chapterIntegrity['risk_level']] ?? $riskToneClasses['none'];
                                                $chapterRiskLabel = $riskLabels[$chapterIntegrity['risk_level']] ?? $chapterIntegrity['risk_level'];
                                                $chapterName = optional($chapterLookup->get($chapterId))->name ?: 'Без главы';
                                            @endphp
                                            <div class="report-chapter-risk-row">
                                                <span class="fw-medium text-truncate">{{ $chapterName }}</span>
                                                <span class="report-integrity-icons">
                                                    <span class="report-integrity-icon {{ $chapterRiskTone }}" title="Риск: {{ $chapterRiskLabel }}">
                                                        <i class="fas fa-shield-alt"></i>
                                                    </span>
                                                    @if ($chapterIntegrity['max_llm_probability'] !== null)
                                                        <span class="report-integrity-metric" title="LLM {{ $chapterIntegrity['max_llm_probability'] }}%">
                                                            <i class="fas fa-robot"></i>
                                                            <span>{{ $chapterIntegrity['max_llm_probability'] }}%</span>
                                                        </span>
                                                    @endif
                                                    @if ($chapterIntegrity['max_similarity_percent'] !== null)
                                                        <span class="report-integrity-metric" title="Схожесть {{ $chapterIntegrity['max_similarity_percent'] }}%">
                                                            <i class="fas fa-copy"></i>
                                                            <span>{{ $chapterIntegrity['max_similarity_percent'] }}%</span>
                                                        </span>
                                                    @endif
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @include('courses.partials.report-activity')
                            </div>

                        </div>
                    </div>
                @endforeach

            </div>
        </div>
    </div>
        @endif
    </div>


@endsection
