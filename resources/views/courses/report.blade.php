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
    <div class="container-fluid px-0" data-report-workspace data-report-detail-url="{{ url('/insider/courses/'.$course->id.'/report') }}" data-report-default-chapter="{{ $defaultReportChapterId }}">
        <x-gc-course-header :course="$course" :chapter="request('chapter', $defaultReportChapterId)" active="report" />
        <h2 class="visually-hidden">Отчёт</h2>
        @include('courses.partials.scope-statistics')
        @if($students->isEmpty())
            <div class="gc-card p-4">
                <h2 class="gc-section-title">{{ request('attention') === '1' ? 'По этому фильтру учеников нет' : 'Учеников пока нет' }}</h2>
                <p class="text-muted mb-0">{{ request('attention') === '1' ? 'По выбранному условию никто не требует внимания.' : 'Отчёт появится, когда к курсу присоединятся ученики.' }}</p>
                @if(request('attention') === '1')<a class="btn btn-outline-secondary btn-sm mt-3" href="{{ url('/insider/courses/'.$course->id.'/report') }}">Показать всех учеников</a>@endif
            </div>
        @else
    <div class="gc-report-overview" aria-label="Сводка класса">
        <span>Ученики: <strong>{{ $reportSummary['students'] }}</strong></span>
        <a href="{{ request()->fullUrlWithQuery(['attention' => request('attention') === '1' ? null : '1', 'student' => null, 'lesson' => null]) }}" @if(request('attention') === '1') aria-current="true" @endif>Требуют внимания: <strong>{{ $reportSummary['attention'] }}</strong></a>
        <a href="{{ url('/insider/reviews?course='.$course->id) }}"><i class="fas fa-hourglass-half" aria-hidden="true"></i>На проверке: <strong>{{ $reportSummary['pending'] }}</strong></a>
    </div>

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
                <form class="gc-roster-filters" method="GET"><input type="hidden" name="chapter" value="{{ request('chapter', $defaultReportChapterId) }}" data-report-chapter-value><input type="hidden" name="lesson_state" data-report-lesson-state-value><input type="hidden" name="lesson_q" data-report-lesson-query-value><label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="attention" value="1" @checked(request('attention') === '1') onchange="this.form.submit()" aria-describedby="report-attention-help"> Требуют внимания</label><span class="visually-hidden" id="report-attention-help">Прогресс за курс ниже 50% или предупреждения по работам</span><noscript><button class="btn btn-outline-secondary btn-sm">Применить</button></noscript></form>
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
                            <option value="pending">по работам на проверке</option>
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
                        <a class="nav-link report-student-link @if ($selectedStudent?->id === $student->id) active @endif" id="student{{$student->id}}-tab" data-bs-toggle="pill" data-no-tab-history
                           href="{{ request()->fullUrlWithQuery(['student' => $student->id]) }}#student{{$student->id}}" data-bs-target="#student{{$student->id}}" role="tab"
                           aria-controls="student{{$student->id}}" aria-selected="{{ $selectedStudent?->id === $student->id ? 'true' : 'false' }}"
                           data-plotly-resize-target="pulse{{$student->id}}"
                           data-report-student-name="{{$student->name}} {{ $student->activeCustomTitle() }} {{ $student->points }} {{ $student->max_points }}" data-report-student-label="{{ $student->name }}"
                           data-report-student-sort-name="{{ mb_strtolower($student->name) }}"
                           data-report-student-sort-percent="{{ round($student->percent, 2) }}"
                           data-report-student-sort-pending="{{ $student->pending_count }}"
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
                                @if($student->pending_count)<small class="report-student-link__pending">На проверке: {{ $student->pending_count }}</small>@endif
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
    <x-gc-workspace-toolbar class="gc-report-controls {{ $lessons->isEmpty() ? 'gc-report-controls--empty' : '' }}" role="group" aria-label="Глава и фильтры уроков">
        <div class="gc-report-mobile-student">
            <button class="btn btn-outline-secondary gc-report-student-picker" type="button" data-bs-toggle="offcanvas" data-bs-target="#report-students-picker" aria-controls="report-students-picker" aria-haspopup="dialog" aria-label="Выбрать ученика"><i class="fas fa-search" aria-hidden="true"></i><span data-report-selected-name>{{ $selectedStudent->name }}</span><i class="fas fa-chevron-down" aria-hidden="true"></i></button>
            <button class="btn btn-outline-secondary gc-icon-button" type="button" data-report-previous aria-label="Предыдущий ученик"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
            <button class="btn btn-outline-secondary gc-icon-button" type="button" data-report-next aria-label="Следующий ученик"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
        </div>
        <label class="gc-workspace-field gc-report-chapter-filter" @if($lessons->isEmpty()) hidden @endif><span>Глава</span><select class="form-select form-select-sm" data-report-chapter-filter><option value="all">Все главы</option>@foreach($reportChapterGroups as $group)<option value="{{ $group['chapter']?->id ?? 0 }}">{{ $group['chapter']?->name ?? 'Без главы' }}</option>@endforeach @unless($requestedChapterHasTasks)<option value="{{ $requestedReportChapter->id }}">{{ $requestedReportChapter->name }} (нет заданий)</option>@endunless</select></label>
        <label class="gc-workspace-field gc-report-state-filter" @if($lessons->isEmpty()) hidden @endif><span>Уроки</span><select class="form-select form-select-sm" data-report-lesson-state><option value="all" data-label="Все уроки">Все уроки</option><option value="incomplete" data-label="Не завершены">Не завершены</option><option value="review" data-label="На проверке">На проверке</option><option value="complete" data-label="Завершены">Завершены</option></select></label>
        <div class="input-group input-group-sm gc-search-box gc-report-lesson-search" @if($lessons->isEmpty()) hidden @endif><span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span><input type="search" class="form-control" placeholder="Найти урок" aria-label="Найти урок в выбранной главе" maxlength="120" data-report-lesson-search><button class="btn d-none" type="button" data-report-lesson-clear aria-label="Очистить поиск уроков"><i class="fas fa-times" aria-hidden="true"></i></button></div>
    </x-gc-workspace-toolbar>
            <div class="gc-card p-4" data-report-search-empty hidden><h2 class="h6">Ученики не найдены</h2><p class="gc-help-text">Измените имя или сбросьте поиск, чтобы вернуться к отчётам.</p><button type="button" class="btn btn-outline-secondary btn-sm" data-report-reset-search>Сбросить поиск</button></div>
            <div class="tab-content" id="v-pills-tabContent">
                @foreach ($students as $key => $student)
                    <div class="tab-pane fade show @if ($selectedStudent?->id === $student->id) active @endif" id="student{{$student->id}}"
                         role="tabpanel"
                         aria-labelledby="student{{$student->id}}-tab" data-report-student-pane data-report-student-id="{{ $student->id }}" data-report-loaded="{{ $selectedStudent?->id === $student->id ? 'true' : 'false' }}">

                        @if($selectedStudent?->id === $student->id)
                            @include('courses.partials.report-student')
                        @endif
                    </div>
                @endforeach

            </div>
        </div>
    </div>
        @endif
    </div>


@endsection
