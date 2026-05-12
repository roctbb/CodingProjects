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
        $averageProgress = round($students->avg('percent'));
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
        $riskBadgeClasses = [
            'high' => 'bg-danger text-white',
            'medium' => 'bg-warning text-dark',
            'low' => 'bg-success-subtle text-success border border-success-subtle',
            'none' => 'bg-body-tertiary text-muted',
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
                                    <strong class="report-student-percent">{{ round($student->percent) }}%</strong>
                                </div>
                            </div>
                            <div class="report-student-card__body">
                                @php
                                    $studentIntegrity = $geekPasteIntegrityStats[$student->id] ?? null;
                                    $overallIntegrity = $studentIntegrity['overall'] ?? null;
                                @endphp
                                <div class="progress report-student-progress {{ $student->percent < 40 ? 'is-low' : ($student->percent < 60 ? 'is-mid' : 'is-high') }} mb-3">
                                    @if ($student->percent < 40)
                                        <div class="progress-bar report-student-progress__bar" role="progressbar"
                                              data-progress-width="{{$student->percent}}%"
                                              aria-valuenow="{{$student->percent}}" aria-valuemin="0"
                                              aria-valuemax="100"></div>

                                    @elseif($student->percent < 60)
                                        <div class="progress-bar report-student-progress__bar" role="progressbar"
                                              data-progress-width="{{$student->percent}}%"
                                              aria-valuenow="{{$student->percent}}" aria-valuemin="0"
                                              aria-valuemax="100"></div>

                                    @else
                                        <div class="progress-bar report-student-progress__bar" role="progressbar"
                                              data-progress-width="{{$student->percent}}%"
                                              aria-valuenow="{{$student->percent}}" aria-valuemin="0"
                                              aria-valuemax="100"></div>

                                    @endif
                                </div>
                                @if ($overallIntegrity && $overallIntegrity['synced'] > 0)
                                    @php
                                        $overallRiskClass = $riskBadgeClasses[$overallIntegrity['risk_level']] ?? $riskBadgeClasses['none'];
                                    @endphp
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                        <span class="text-muted small fw-semibold">Академическая честность</span>
                                        <span class="badge rounded-pill {{ $overallRiskClass }}">риск: {{ $overallIntegrity['risk_level'] }}</span>
                                        @if ($overallIntegrity['max_llm_probability'] !== null)
                                            <span class="badge rounded-pill bg-body-tertiary text-body">LLM max {{ $overallIntegrity['max_llm_probability'] }}%</span>
                                        @endif
                                        @if ($overallIntegrity['max_similarity_percent'] !== null)
                                            <span class="badge rounded-pill bg-body-tertiary text-body">схожесть max {{ $overallIntegrity['max_similarity_percent'] }}%</span>
                                        @endif
                                        @if ($overallIntegrity['ai_warnings'] > 0)
                                            <span class="badge rounded-pill bg-info-subtle text-info border border-info-subtle">AI флаги {{ $overallIntegrity['ai_warnings'] }}</span>
                                        @endif
                                        @if ($overallIntegrity['similarity_warnings'] > 0)
                                            <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle">списывание {{ $overallIntegrity['similarity_warnings'] }}</span>
                                        @endif
                                    </div>
                                @endif
                                @if ($pulse_keys->has($student->id))
                                    <div id="pulse{{$student->id}}" class="mb-2 w-100"
                                          data-plotly-report-chart
                                         data-pulse-keys='{{ $pulse_keys[$student->id] }}'
                                         data-pulse-values='{{ $pulse_values[$student->id] }}'
                                         @if ($task_keys->has($student->id))
                                             data-task-keys='{{ $task_keys[$student->id] }}'
                                             data-task-values='{{ $task_values[$student->id] }}'
                                         @endif></div>

                                @endif
                                @if ($studentIntegrity && !empty($studentIntegrity['chapters']))
                                    <div class="d-flex align-items-center justify-content-between gap-2 mt-4 mb-2">
                                        <h5 class="mb-0">Риски по главам</h5>
                                        <span class="text-muted small">GeekPaste</span>
                                    </div>
                                    <div class="d-grid gap-2 mb-3">
                                        @foreach($studentIntegrity['chapters'] as $chapterId => $chapterIntegrity)
                                            @if ($chapterIntegrity['synced'] > 0)
                                                @php
                                                    $chapterRiskClass = $riskBadgeClasses[$chapterIntegrity['risk_level']] ?? $riskBadgeClasses['none'];
                                                    $chapterName = optional($chapterLookup->get($chapterId))->name ?: 'Без главы';
                                                @endphp
                                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border rounded-3 px-3 py-2">
                                                    <span class="fw-semibold text-truncate">{{ $chapterName }}</span>
                                                    <span class="d-flex flex-wrap align-items-center gap-1">
                                                        <span class="badge rounded-pill {{ $chapterRiskClass }}">{{ $chapterIntegrity['risk_level'] }}</span>
                                                        @if ($chapterIntegrity['max_llm_probability'] !== null)
                                                            <span class="badge rounded-pill bg-body-tertiary text-body">LLM {{ $chapterIntegrity['max_llm_probability'] }}%</span>
                                                        @endif
                                                        @if ($chapterIntegrity['max_similarity_percent'] !== null)
                                                            <span class="badge rounded-pill bg-body-tertiary text-body">схожесть {{ $chapterIntegrity['max_similarity_percent'] }}%</span>
                                                        @endif
                                                    </span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                                <div class="d-flex align-items-center justify-content-between gap-2 mt-4 mb-2">
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
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2 px-1">
                                        <h6 class="mb-0 text-muted fw-bold">{{ $groupChapter ? $groupChapter->name : 'Без главы' }}</h6>
                                        @if ($chapterIntegrity && $chapterIntegrity['synced'] > 0 && $chapterIntegrity['risk_level'] !== 'low')
                                            @php $chapterRiskClass = $riskBadgeClasses[$chapterIntegrity['risk_level']] ?? $riskBadgeClasses['none']; @endphp
                                            <span class="badge rounded-pill {{ $chapterRiskClass }}">GP {{ $chapterIntegrity['risk_level'] }}</span>
                                        @endif
                                    </div>
                                    @foreach($groupLessons as $lesson)
                                        @php
                                            $lessonStat = $lessonStats[$lesson->id][$student->id] ?? null;
                                            $lessonPercent = $lessonStat ? $lessonStat->percent : 0;
                                            $lessonPoints = $lessonStat ? $lessonStat->points : 0;
                                            $lessonMaxPoints = $lessonStat ? $lessonStat->max_points : 0;
                                            $lessonProgressWidth = max(0, min(100, (int) round($lessonPercent)));
                                            $lessonProgressClass = $lessonPercent < 40 ? 'is-low' : ($lessonPercent < 60 ? 'is-mid' : 'is-high');
                                            $lessonIntegrity = $studentIntegrity['lessons'][$lesson->id] ?? null;
                                            $lessonStartDate = $lesson->getStartDate($course);
                                        @endphp

                                        <div class="report-lesson-row">
                                            <div class="report-lesson-main">
                                                <div class="min-width-0">
                                                    <a class="report-lesson-link d-inline-flex align-items-center gap-2"
                                                       data-bs-toggle="collapse"
                                                       href="#student{{$student->id}}marks{{$lesson->id}}"
                                                       aria-expanded="false"
                                                       aria-controls="student{{$student->id}}marks{{$lesson->id}}">
                                                        <span class="text-truncate">{{$lesson->name}}</span>
                                                        <i class="fas fa-chevron-down report-lesson-link__icon"></i>
                                                    </a>
                                                    @if ($lessonStartDate)
                                                        <span class="badge rounded-pill bg-body-tertiary text-muted ms-2">
                                                            открыт {{ $lessonStartDate->format('d.m.Y') }}
                                                        </span>
                                                    @endif
                                                    @if (!$lesson->isAvailableForUser($course, $student))
                                                        <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle ms-2 report-lesson-lock">закрыт</span>
                                                    @endif
                                                </div>
                                                <div class="report-lesson-metrics">
                                                    @if ($lessonIntegrity && $lessonIntegrity['synced'] > 0 && $lessonIntegrity['risk_level'] !== 'low')
                                                        @php $lessonRiskClass = $riskBadgeClasses[$lessonIntegrity['risk_level']] ?? $riskBadgeClasses['none']; @endphp
                                                        <span class="badge rounded-pill {{ $lessonRiskClass }}">GP {{ $lessonIntegrity['risk_level'] }}</span>
                                                    @endif
                                                    <span class="report-lesson-score">{{$lessonPoints}} / {{$lessonMaxPoints}} XP</span>
                                                    <div class="report-score-progress {{ $lessonProgressClass }}"
                                                         role="progressbar"
                                                         aria-valuenow="{{$lessonProgressWidth}}"
                                                         aria-valuemin="0"
                                                         aria-valuemax="100"
                                                         aria-label="{{$lesson->name}}: {{$lessonPoints}} / {{$lessonMaxPoints}}">
                                                        <span class="report-score-progress__bar progress-width-{{$lessonProgressWidth}}" data-progress-width="{{$lessonPercent}}%"></span>
                                                        <span class="report-score-progress__value">{{round($lessonPercent)}}%</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="collapse mt-2" id="student{{$student->id}}marks{{$lesson->id}}">
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

                                                            @php $blocked = $task->isBlocked($student->id, $course->id); @endphp
                                                            @if ($taskIntegrity && $taskIntegrity['synced'] > 0 && $taskIntegrity['risk_level'] !== 'low')
                                                                @php
                                                                    $taskRiskClass = $riskBadgeClasses[$taskIntegrity['risk_level']] ?? $riskBadgeClasses['none'];
                                                                    $taskRiskTitle = trim(implode(' · ', array_filter([
                                                                        $taskIntegrity['max_llm_probability'] !== null ? 'LLM '.$taskIntegrity['max_llm_probability'].'%' : null,
                                                                        $taskIntegrity['max_similarity_percent'] !== null ? 'схожесть '.$taskIntegrity['max_similarity_percent'].'%' : null,
                                                                        $taskIntegrity['ai_warnings'] > 0 ? 'AI флаги '.$taskIntegrity['ai_warnings'] : null,
                                                                        $taskIntegrity['similarity_warnings'] > 0 ? 'списывание '.$taskIntegrity['similarity_warnings'] : null,
                                                                    ])));
                                                                @endphp
                                                                <span class="badge rounded-pill {{ $taskRiskClass }}" title="{{ $taskRiskTitle ?: 'GeekPaste' }}">
                                                                    @if ($taskIntegrity['max_llm_probability'] !== null)
                                                                        AI {{ $taskIntegrity['max_llm_probability'] }}%
                                                                    @elseif ($taskIntegrity['max_similarity_percent'] !== null)
                                                                        схожесть {{ $taskIntegrity['max_similarity_percent'] }}%
                                                                    @else
                                                                        GP
                                                                    @endif
                                                                </span>
                                                            @endif
                                                            @if ($blocked)
                                                                <span class="badge rounded-pill bg-body-tertiary report-task-mark report-task-mark--blocked">0 / {{$task->max_mark}}</span>
                                                            @elseif ($should_check)
                                                                <span class="badge rounded-pill bg-body-tertiary report-task-mark report-task-mark--review">{{$mark}} / {{$task->max_mark}}</span>
                                                            @else
                                                                <span class="badge rounded-pill {{ $markClass }} report-task-mark">{{$mark}} / {{$task->max_mark}}</span>
                                                            @endif
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
                </div>
                <div class="nav flex-column nav-pills p-2 gap-1 report-students-nav" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                    @foreach ($students as $key => $student)
                        @php
                            $studentProgressWidth = max(0, min(100, (int) round($student->percent)));
                            $studentProgressClass = $student->percent < 40 ? 'is-low' : ($student->percent < 60 ? 'is-mid' : 'is-high');
                        @endphp
                        <a class="nav-link report-student-link @if ($key == 0) active @endif" id="student{{$student->id}}-tab" data-bs-toggle="pill"
                           href="#student{{$student->id}}" role="tab"
                           aria-controls="student{{$student->id}}" aria-selected="@if ($key == 0) true @else false @endif"
                           data-plotly-resize-target="pulse{{$student->id}}"
                           data-report-student-name="{{$student->name}} {{ $student->activeCustomTitle() }} {{ $student->points }} {{ $student->max_points }}">
                            <span class="min-width-0">
                                <span class="report-student-link__name">
                                    <span class="text-truncate">{{$student->name}}</span>
                                    @include('profile.partials.custom_title_badge', ['profileUser' => $student, 'compact' => true])
                                </span>
                                <small class="report-student-link__meta">{{ $student->points }} / {{ $student->max_points }} XP</small>
                            </span>
                            <span class="report-nav-progress {{ $studentProgressClass }}" title="Прогресс: {{ round($student->percent) }}%">
                                <span class="report-nav-progress__bar progress-width-{{$studentProgressWidth}}" data-progress-width="{{$student->percent}}%"></span>
                                <span class="report-nav-progress__value">{{ round($student->percent) }}%</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    </div>


@endsection
