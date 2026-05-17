@extends('layouts.left-menu')

@section('title')
    {{$course->name}}
@endsection

@section('tabs')

@endsection

@section('head')
    <script src="{{ asset('build/js/vendor/plotly.min.js') }}"></script>
@endsection



@section('content')
    @php
        $reportTasks = $lessons->flatMap(function ($lesson) {
            return $lesson->steps->flatMap(function ($step) {
                return $step->tasks;
            });
        });
        $displayPercent = fn ($percent) => min(100, max(0, (float) $percent));
        $averageProgress = round($students->avg(fn ($student) => $displayPercent($student->percent)) ?? 0);
        $studentsAtRisk = $students->filter(fn ($student) => $student->percent < 50)->count();
        $totalStudentLessons = $students->count() * $lessons->count();
        $completedStudentLessons = $students->sum(function ($student) use ($lessons, $lessonStats) {
            return $lessons->filter(function ($lesson) use ($student, $lessonStats) {
                $stats = $lessonStats[$lesson->id][$student->id] ?? null;
                return $stats && $stats->percent >= 100;
            })->count();
        });
        $lessonCompletionPercent = $totalStudentLessons > 0 ? round($completedStudentLessons / $totalStudentLessons * 100) : 0;
        $pendingSolutionsCount = $reportTasks
            ->flatMap(fn ($task) => $task->solutions)
            ->filter(fn ($solution) => $solution->submitted && $solution->mark === null && !$solution->review_skipped && $students->contains('id', $solution->user_id))
            ->count();
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
    @endphp
    <div class="container-fluid px-0">
        <div class="gc-card gc-page-header report-page-header mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id) }}"><i class="icon ion-chevron-left"></i> К курсу</a>
                <h2 class="mb-1">Отчет по курсу</h2>
                <p class="mb-0 text-muted text-truncate">{{ $course->name }}</p>
            </div>
            <div class="row row-cols-3 g-2 flex-shrink-0 report-summary">
                <div class="col"><div class="gc-summary-tile"><strong>{{ $students->count() }}</strong><span>учеников</span></div></div>
                <div class="col"><div class="gc-summary-tile"><strong>{{ $lessons->count() }}</strong><span>уроков</span></div></div>
                <div class="col"><div class="gc-summary-tile"><strong>{{ $reportTasks->count() }}</strong><span>задач</span></div></div>
            </div>
        </div>

        <div class="report-overview-grid mb-3">
            <div class="report-overview-card">
                <span>Средний прогресс</span>
                <strong>{{ $averageProgress }}%</strong>
                <small>по всем ученикам</small>
            </div>
            <div class="report-overview-card">
                <span>На проверке</span>
                <strong>{{ $pendingSolutionsCount }}</strong>
                <small>решений ждут оценки</small>
            </div>
            <div class="report-overview-card">
                <span>Ниже 50%</span>
                <strong>{{ $studentsAtRisk }}</strong>
                <small>учеников требуют внимания</small>
            </div>
            <div class="report-overview-card">
                <span>Освоение уроков</span>
                <strong>{{ $lessonCompletionPercent }}%</strong>
                <small>{{ $completedStudentLessons }} из {{ $totalStudentLessons }}</small>
            </div>
        </div>

    <div class="row g-3 align-items-start">

        <div class="col-12 col-xl-9">
            <div class="tab-content" id="v-pills-tabContent">
                @foreach ($students as $key => $student)
                    <div class="tab-pane fade show @if ($key == 0) active @endif" id="student{{$student->id}}"
                         role="tabpanel"
                         aria-labelledby="student{{$student->id}}-tab">

                        <div class="gc-card report-student-card overflow-hidden w-100" id="cardbody{{$student->id}}">
                            <div class="gc-section-header gc-section-header--between report-student-card__header">
                                <div class="report-student-head d-flex align-items-start justify-content-between gap-3">
                                    <div class="d-flex align-items-center gap-2 min-width-0">
                                        <x-gc-avatar :user="$student" size="md" class="flex-shrink-0" alt="" />
                                        <div class="min-width-0">
                                            <div class="d-flex flex-wrap align-items-center gap-1 mb-1 min-width-0">
                                                <h4 class="fw-bold mb-0 text-truncate">{{ $student->name }}</h4>
                                                @include('profile.partials.custom_title_badge', ['profileUser' => $student, 'compact' => true])
                                            </div>
                                            <small class="text-muted">{{ $student->points }} / {{ $student->max_points }} XP</small>
                                        </div>
                                    </div>
                                    @php
                                        $studentDisplayPercent = $displayPercent($student->percent);
                                    @endphp
                                    <strong class="report-student-percent">{{ round($studentDisplayPercent) }}%</strong>
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
                                <div class="progress report-student-progress {{ $student->percent < 40 ? 'is-low' : ($student->percent < 60 ? 'is-mid' : 'is-high') }} mb-3">
                                    @if ($student->percent < 40)
                                        <div class="progress-bar report-student-progress__bar" role="progressbar"
                                              data-progress-width="{{$studentDisplayPercent}}%"
                                              aria-valuenow="{{$studentDisplayPercent}}" aria-valuemin="0"
                                              aria-valuemax="100"></div>

                                    @elseif($student->percent < 60)
                                        <div class="progress-bar report-student-progress__bar" role="progressbar"
                                              data-progress-width="{{$studentDisplayPercent}}%"
                                              aria-valuenow="{{$studentDisplayPercent}}" aria-valuemin="0"
                                              aria-valuemax="100"></div>

                                    @else
                                        <div class="progress-bar report-student-progress__bar" role="progressbar"
                                              data-progress-width="{{$studentDisplayPercent}}%"
                                              aria-valuenow="{{$studentDisplayPercent}}" aria-valuemin="0"
                                              aria-valuemax="100"></div>

                                    @endif
                                </div>
                                @if ($overallIntegrity && $overallIntegrity['synced'] > 0)
                                    @php
                                        $overallRiskTone = $riskToneClasses[$overallIntegrity['risk_level']] ?? $riskToneClasses['none'];
                                        $overallRiskLabel = $riskLabels[$overallIntegrity['risk_level']] ?? $overallIntegrity['risk_level'];
                                    @endphp
                                    <div class="report-integrity-strip mb-3">
                                        <span class="text-muted small fw-semibold">Академическая честность</span>
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
                                                  data-confirm="Снять предупреждение GeekPaste по ученику? Старые флаги будут скрыты в отчете.">
                                                @csrf
                                                <button type="submit" class="btn btn-sm report-integrity-reset">
                                                    <i class="fas fa-check"></i>
                                                    <span>Снять</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                                @if ($pulse_keys->has($student->id))
                                    <div class="report-chart-card mb-3">
                                        <div class="report-chart-legend">
                                            <span><i class="report-chart-dot report-chart-dot--pulse"></i> просмотры</span>
                                            @if ($task_keys->has($student->id))
                                                <span><i class="report-chart-dot report-chart-dot--tasks"></i> опыт</span>
                                            @endif
                                        </div>
                                        <div id="pulse{{$student->id}}" class="report-chart w-100"
                                             data-plotly-report-chart
                                             data-pulse-keys='{{ $pulse_keys[$student->id] }}'
                                             data-pulse-values='{{ $pulse_values[$student->id] }}'
                                             @if ($task_keys->has($student->id))
                                                 data-task-keys='{{ $task_keys[$student->id] }}'
                                                 data-task-values='{{ $task_values[$student->id] }}'
                                             @endif></div>
                                    </div>
                                @endif
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
                                                <span class="fw-semibold text-truncate">{{ $chapterName }}</span>
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
                                <div class="report-section-head mt-4 mb-2">
                                    <h5 class="mb-0">Прогресс по урокам</h5>
                                    <span class="text-muted small">{{ $lessons->count() }} уроков</span>
                                </div>
                                <div class="report-lessons-list d-grid gap-2">
                                @foreach($reportChapterGroups as $chapterGroup)
                                    @php
                                        $groupChapter = $chapterGroup['chapter'];
                                        $groupLessons = $chapterGroup['lessons'];
                                        $chapterIntegrity = $studentIntegrity['chapters'][$groupChapter ? $groupChapter->id : 0] ?? null;
                                    @endphp
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
                                    @foreach($groupLessons as $lesson)
                                        @php
                                            $lessonStat = $lessonStats[$lesson->id][$student->id] ?? null;
                                            $lessonPercent = $lessonStat ? $lessonStat->percent : 0;
                                            $lessonDisplayPercent = $displayPercent($lessonPercent);
                                            $lessonPoints = $lessonStat ? $lessonStat->points : 0;
                                            $lessonMaxPoints = $lessonStat ? $lessonStat->max_points : 0;
                                            $lessonProgressWidth = (int) round($lessonDisplayPercent);
                                            $lessonProgressClass = $lessonPercent < 40 ? 'is-low' : ($lessonPercent < 60 ? 'is-mid' : 'is-high');
                                            $lessonIntegrity = $studentIntegrity['lessons'][$lesson->id] ?? null;
                                            $lessonHasIntegrityRisk = $lessonIntegrity && $lessonIntegrity['synced'] > 0 && $lessonIntegrity['risk_level'] !== 'low';
                                            $lessonStartDate = $lesson->getStartDate($course);
                                        @endphp

                                        <div class="report-lesson-row">
                                            <div class="report-lesson-main">
                                                <div class="report-lesson-info min-width-0">
                                                    <a class="report-lesson-link d-inline-flex align-items-center gap-2"
                                                       data-bs-toggle="collapse"
                                                       href="#student{{$student->id}}marks{{$lesson->id}}"
                                                       aria-expanded="false"
                                                       aria-controls="student{{$student->id}}marks{{$lesson->id}}">
                                                        <span class="text-truncate">{{$lesson->name}}</span>
                                                        <i class="fas fa-chevron-down report-lesson-link__icon"></i>
                                                    </a>
                                                    <div class="report-lesson-meta">
                                                        @if ($lessonStartDate)
                                                            <span class="report-lesson-date">открыт {{ $lessonStartDate->format('d.m.Y') }}</span>
                                                        @endif
                                                        @if (!$lesson->isAvailableForUser($course, $student))
                                                            <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle report-lesson-lock">закрыт</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="report-lesson-metrics">
                                                    <span class="report-lesson-risk-slot {{ $lessonHasIntegrityRisk ? '' : 'is-empty' }}">
                                                        @if ($lessonHasIntegrityRisk)
                                                            @php
                                                                $lessonRiskTone = $riskToneClasses[$lessonIntegrity['risk_level']] ?? $riskToneClasses['none'];
                                                                $lessonSuspicionLabel = (($lessonIntegrity['similarity_warnings'] ?? 0) > 0 || $lessonIntegrity['max_similarity_percent'] !== null) ? 'списывание' : 'AI';
                                                            @endphp
                                                            <span class="report-suspicion-icon {{ $lessonRiskTone }}"
                                                                  title="{{ $lessonSuspicionLabel }}">
                                                                <i class="fas {{ $lessonSuspicionLabel === 'AI' ? 'fa-robot' : 'fa-exclamation-triangle' }}"></i>
                                                            </span>
                                                        @endif
                                                    </span>
                                                    <span class="report-lesson-score">{{$lessonPoints}} / {{$lessonMaxPoints}} XP</span>
                                                    <div class="report-score-progress {{ $lessonProgressClass }}"
                                                         role="progressbar"
                                                         aria-valuenow="{{$lessonProgressWidth}}"
                                                         aria-valuemin="0"
                                                         aria-valuemax="100"
                                                         aria-label="{{$lesson->name}}: {{$lessonPoints}} / {{$lessonMaxPoints}}">
                                                        <span class="report-score-progress__bar progress-width-{{$lessonProgressWidth}}" data-progress-width="{{$lessonDisplayPercent}}%"></span>
                                                        <span class="report-score-progress__value">{{round($lessonDisplayPercent)}}%</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="collapse report-lesson-details" id="student{{$student->id}}marks{{$lesson->id}}">
                                                <ul class="report-task-list list-unstyled mb-0">
                                                @foreach($lesson->steps as $step)
                                                    @php
                                                        $tasks = $step->tasks;
                                                    @endphp
                                                    @foreach($tasks as $task)
                                                        @php
                                                            $filtered = $task->solutions->filter(function ($value) use ($student) {
                                                                return $value->user_id == $student->id;
                                                            });
                                                            $bestSolution = \App\Solution::bestScoredIn($filtered);
                                                            $mark = $bestSolution ? $bestSolution->mark : 0;
                                                            $markClass = $bestSolution ? $bestSolution->scoreBadgeClass('bg-body-tertiary') : 'bg-body-tertiary';
                                                            $should_check = $filtered->filter(fn ($solution) => $solution->submitted && ($solution->mark === null || $solution->recheck_requested) && !$solution->review_skipped)->isNotEmpty();
                                                            $taskIntegrity = $studentIntegrity['tasks'][$task->id] ?? null;
                                                        @endphp
                                                        <li class="report-task-row">
                                                            <a class="report-task-link text-decoration-none"
                                                               target="_blank"
                                                               href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id)}}">{{$task->name}}</a>

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
                                                                    <span class="report-task-suspicion-dot {{ $taskRiskTone }}"
                                                                          title="{{ $taskSuspicionLabel }}{{ $taskRiskTitle ? ': '.$taskRiskTitle : '' }}">
                                                                        <i class="fas {{ $taskSuspicionLabel === 'AI' ? 'fa-robot' : 'fa-exclamation-triangle' }}"></i>
                                                                    </span>
                                                                @endif
                                                                @if ($blocked)
                                                                    <span class="badge rounded-pill bg-body-tertiary report-task-mark report-task-mark--blocked">0 / {{$task->max_mark}}</span>
                                                                @elseif ($should_check)
                                                                    <span class="badge rounded-pill bg-body-tertiary report-task-mark report-task-mark--review">{{$mark}} / {{$task->max_mark}}</span>
                                                                @else
                                                                    <span class="badge rounded-pill {{ $markClass }} report-task-mark">{{$mark}} / {{$task->max_mark}}</span>
                                                                @endif
                                                            </span>
                                                        </li>
                                                    @endforeach
                                                @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    @endforeach
                                @endforeach
                                </div>
                            </div>

                        </div>
                    </div>
                @endforeach

            </div>
        </div>
        <div class="col-12 col-xl-3">
            <div class="gc-card report-students-card overflow-hidden sticky-xl-top">
                <div class="gc-section-header gc-section-header--between report-students-card__header">
                    <div class="min-width-0">
                        <h6 class="mb-0">Ученики</h6>
                        <span class="text-muted small">Сводка по прогрессу</span>
                    </div>
                    <span class="badge rounded-pill bg-body-tertiary report-student-search-count flex-shrink-0" data-report-student-count>{{ $students->count() }} из {{ $students->count() }}</span>
                </div>
                <div class="p-2 border-bottom report-student-search">
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
                            $studentDisplayPercent = $displayPercent($student->percent);
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
                           data-report-student-name="{{$student->name}} {{ $student->activeCustomTitle() }} {{ $student->points }} {{ $student->max_points }}"
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
                                              title="GeekPaste: {{ $navRiskLevel === 'high' ? 'высокий риск' : 'средний риск' }}">
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
            </div>
        </div>
    </div>
    </div>


@endsection
