@extends('layouts.left-menu')

@section('title')
    {{$course->name}}
@endsection

@section('tabs')
@endsection


@section('head')
    <meta name="gc-plotly-src" content="{{ asset('build/js/vendor/plotly.min.js') }}">
    <script type="module" src="{{ asset('build/js/course-stats.js') }}"></script>
@endsection


@section('content')
    @php
        $isManager = $course->teachers->contains($user) || $user->role == 'admin';
        $isLearner = !$isManager && $course->students->contains('id', $user->id);
        $studentsCount = $students->count();
        $userBalance = $isLearner ? $user->balance() : 0;
        $displayPercent = fn ($percent) => min(100, max(0, (float) $percent));
        $pathLessons = $lessons->filter(fn($lesson) => $lesson->steps->count() > 0)->values();
        $courseStateLabels = [
            'draft' => 'Черновик',
            'started' => 'Начат',
            'ended' => 'Завершён',
        ];
        $studentsCountLastTwoDigits = $studentsCount % 100;
        $studentsCountLastDigit = $studentsCount % 10;
        $studentsCountLabel = $studentsCountLastDigit === 1 && $studentsCountLastTwoDigits !== 11
            ? 'ученик'
            : (in_array($studentsCountLastDigit, [2, 3, 4], true) && !in_array($studentsCountLastTwoDigits, [12, 13, 14], true)
                ? 'ученика'
                : 'учеников');
        $availablePathLessons = $pathLessons->filter(fn($lesson) => $lesson->isAvailableForUser($course, $user));
        $courseEnded = $course->state === 'ended';
        $lessonViews = $isLearner ? $availablePathLessons->mapWithKeys(fn ($lesson) => [$lesson->id => \App\Support\CourseLessonView::forStudent($lesson, $course, $user, $lessonStats[$lesson->id][$user->id] ?? null, $lessonCards->get($lesson->id)['rows'])]) : collect();
        $studyOutcome = $isLearner ? \App\Support\CourseStudyOutcome::forChapter($course, $chapter, $user, $availablePathLessons, $lessonViews) : null;
        $nextLesson = $isLearner && !$courseEnded ? $availablePathLessons->first(function ($lesson) use ($lessonViews) {
            return !($lessonViews->get($lesson->id)['completed'] ?? false);
        }) : null;
        $focusLesson = $isLearner && $courseEnded ? null : ($currentLesson ? $pathLessons->firstWhere('id', $currentLesson->id) : $nextLesson);
        $currentPathLessonId = $focusLesson?->id;
        $returnToCurrentLesson = $courseEnded ? null : ($currentLesson ?? $focusLesson);
        $currentLessonUrl = $returnToCurrentLesson
            ? url('/insider/courses/'.$course->id.'?chapter='.$returnToCurrentLesson->chapter_id.'#lesson-'.$returnToCurrentLesson->id) : null;
        $focusView = $focusLesson ? $lessonViews->get($focusLesson->id) : null;
        $nextLearningStep = $focusView['nextStep'] ?? null;
        $coursePath = \App\Support\ProjectPath::forChapters($course->program->chapters, $course->program->lessons, $course, $user, $projectPathStats, $chapter->id);
    @endphp

    <div class="course-page">
        <x-gc-course-header :course="$course" :chapter="$chapter" :deadline-items="$courseDeadlineItems" active="program" />

        <div class="gc-course-program-layout">
            <main class="gc-course-program-main">
                @if($course->program->chapters->count() > 1)
                <div class="gc-card gc-course-program-controls">
                    <x-gc-chapter-timeline :course="$course" :chapter="$chapter" :viewer="$user" :project-path="$coursePath" :current-chapter-id="$currentLesson?->chapter_id" embedded>
                        <x-slot:actions>@include('courses.partials.program-actions')</x-slot:actions>
                    </x-gc-chapter-timeline>
                    @if($isManager && !$courseEnded && !$course->current_lesson_id)
                        <p class="gc-help-text gc-current-lesson-help mb-0">Отметьте текущий урок в его меню. Доступ к материалам задаётся расписанием.</p>
                    @endif
                </div>
                @elseif($courseDeadlines->isNotEmpty())
                    <div class="gc-course-single-due gc-card">@include('courses.partials.due-summary')</div>
                @endif
                @include('courses.partials.study-outcome')
                <div class="gc-course-program-lessons">
                <div class="course-lessons-toolbar {{ $course->program->chapters->count() === 1 ? 'course-lessons-toolbar--single' : '' }}">
                    <div class="course-lessons-toolbar__title">
                        <h2 class="course-section-label mb-0">Уроки</h2>
                        <span class="course-lessons-toolbar__count">· {{ $pathLessons->count() }}</span>
                    </div>
                    <div class="gc-course-lesson-actions">
                    @if($isManager && ($coursePendingCount ?? 0))
                        <a class="gc-quiet-link small" href="{{ url('/insider/reviews?course='.$course->id) }}">Ждут проверки: {{ $coursePendingCount }}</a>
                    @endif
                    @if($returnToCurrentLesson && (!$focusLesson || $pathLessons->first()?->id !== $focusLesson->id))
                        <a class="btn btn-outline-secondary btn-sm gc-course-current-link" href="{{ $currentLessonUrl }}" title="{{ $returnToCurrentLesson->name }}" aria-label="К текущему уроку: {{ $returnToCurrentLesson->name }}"><i class="fas fa-location-arrow" aria-hidden="true"></i> К текущему уроку</a>
                    @endif
                    @if($isManager)
                        <a class="btn btn-outline-secondary btn-sm gc-course-add-lesson" aria-label="Добавить урок" href="{{ url('/insider/courses/'.$course->id.'/create?chapter='.$chapter->id) }}">
                            <i class="fas fa-plus" aria-hidden="true"></i><span class="gc-course-add-long">Добавить урок</span><span class="gc-course-add-short">Урок</span>
                        </a>
                        @if($course->program->chapters->count() === 1)
                            @include('courses.partials.program-actions')
                        @endif
                    @endif
                    </div>
                </div>

                @if ($pathLessons->isEmpty())
	                    @include('courses.partials.home_empty_state', [
	                        'icon' => $isManager ? 'fas fa-layer-group' : 'fas fa-hourglass-half',
	                        'title' => $isManager ? 'В этой главе пока нет уроков' : 'Уроки ещё готовятся',
	                        'text' => $isManager ? 'Добавьте первый урок, чтобы ученикам было куда двигаться.' : 'Когда преподаватель откроет материалы, они появятся здесь.',
	                        'actionUrl' => $isManager ? url('/insider/courses/'.$course->id.'/create?chapter='.$chapter->id) : null,
	                        'actionText' => $isManager ? 'Добавить урок' : null,
	                    ])
	                @else
                <div class="course-learning-path">
                    @foreach($pathLessons as $pathIndex => $lesson)
                        @php
                            $lessonView = $lessonViews->get($lesson->id);
                            $lessonCard = $lessonCards->get($lesson->id);
                            $isLessonStarted = $lesson->isStarted($course);
                            $hasEarlyAccess = $isLearner && !$isLessonStarted && $lesson->hasEarlyAccess($course, $user);
                            $canBuyEarlyAccess = $isLearner && $lesson->canBuyEarlyAccess($course, $user);
                            $earlyAccessCost = $isLearner ? $user->earlyLessonAccessCost($lesson) : $lesson->earlyAccessCost();
                            $canAffordEarlyAccess = $canBuyEarlyAccess && ($earlyAccessCost <= 0 || $userBalance >= $earlyAccessCost);
                            $isAvailable = $isManager || ($isLearner && ($isLessonStarted || $hasEarlyAccess));
                            $showTeacherLock = $isManager && !$isLessonStarted;
                            $startDate = $lesson->getStartDate($course);
                            $cstats = $isLearner ? ($lessonStats[$lesson->id][$cstudent->id] ?? null) : null;
                            $hasScoredStats = $cstats && $cstats->max_points > 0;
                            $cmaxPoints = $hasScoredStats ? $cstats->max_points : ($lessonCard['maximum'] ?? 0);
                            $cpoints = $hasScoredStats ? $cstats->points : ($lessonCard['points'] ?? 0);
                            $cpercent = $hasScoredStats ? $cstats->percent : ($cmaxPoints ? $cpoints * 100 / $cmaxPoints : 0);
                            $cdisplayPercent = $displayPercent($cpercent);
                            $cprogressWidth = (int) round($cdisplayPercent);
	                            $isDone = $isLearner && ($lessonView['completed'] ?? false);
	                            $isCurrent = $currentPathLessonId == $lesson->id;
                                $classLesson = $isLearner && $isCurrent && $currentLesson?->id === $lesson->id;
                                $repeatLesson = $isCurrent && $isDone && $lessonView['action'] === 'Повторить урок';
                                $lessonTargetStep = $isCurrent ? $nextLearningStep : ($lessonView['nextStep'] ?? null);
                                $lessonUrl = $lessonView['targetUrl'] ?? url('/insider/courses/'.$course->id.'/steps/'.($lessonTargetStep?->id ?? $lesson->steps->first()->id));
	                            $lessonTaskCount = $lesson->steps->sum(function ($step) {
	                                return $step->tasks->count();
	                            });
	                            $lessonDeadline = $lesson->sharedDeadline($course->id);
                                $lessonDeadlines = $lessonCardDeadlines->filter(fn ($deadline) => $deadline->task->step->lesson_id == $lesson->id);
                                $lessonCalendarDeadline = $lessonDeadlines->first(fn ($deadline) => ($deadline instanceof \App\Support\DeadlineRule
                                    ? $deadline->cutoffAt() : $deadline->expiration->copy()->addDay())->isFuture()) ?? $lessonDeadlines->last();
	                        @endphp

                        <article id="lesson-{{ $lesson->id }}" class="course-path-item @if ($isDone) is-done @endif @if ($isCurrent) is-current @endif @if (!$isAvailable) is-locked @endif @if (!$isDone && ($hasEarlyAccess || $canBuyEarlyAccess)) is-early-access @endif">
                            @php
                                $showLessonSticker = $isLearner && $lessonTaskCount > 0 && filled($lesson->sticker);
                                $rewardStatus = $isManager ? 'catalog' : ($cpercent > 90 ? 'earned' : 'preview');
                                $hasLessonStatistics = $isManager && $lessonTaskCount > 0 && $students->isNotEmpty();
                                $hasPersonalStatistics = $isLearner && $isAvailable && ($lessonView['rows']->isNotEmpty() ?? false);
                                $lessonDisplayStats = $hasLessonStatistics
                                    ? \App\Support\CourseLessonStatistics::forStudents($lessonStats->get($lesson->id, collect()), $students) : collect();
                                $lessonSummary = \App\Support\CourseLessonStatistics::summary($lessonDisplayStats, $students->count());
                            @endphp

                            <div class="gc-card course-path-card course-lesson-panel {{ $isCurrent ? 'course-path-card--expanded' : 'course-path-card--compact' }}">
                                @include('courses.partials.lesson-summary')
                                @if($isCurrent)
                                    @if(filled($lesson->description) && !$repeatLesson)
                                    <div class="course-path-card__details">
                                        <div class="course-path-description" data-filter-by="text">@parsedown($lesson->description)</div>
                                    </div>
                                    @endif
                                    @if($isAvailable && !$repeatLesson)
                                        <div class="course-path-card__footer">
                                            <a class="btn btn-primary btn-sm course-continue" href="{{ $isManager && $lessonCard['pending'] ? $lessonCard['reviewUrl'] : $lessonUrl }}">{{ $isManager ? ($lessonCard['pending'] ? 'Проверить работы · '.$lessonCard['pending'] : 'Открыть материалы') : ($classLesson && $lessonView['action'] === 'Продолжить урок' ? 'К уроку класса' : $lessonView['action']) }} <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                                        </div>
                                    @endif
                                @endif
                                @if($hasLessonStatistics)
                                    @include('courses.partials.lesson-statistics-panel', ['stats' => $lessonDisplayStats, 'compact' => true])
                                @elseif($hasPersonalStatistics)
                                    @include('courses.partials.lesson-personal-statistics')
                                @endif
                            </div>
		                        </article>
		                        @if ($isManager && $lessonTaskCount > 0)
		                            @include('courses.partials.lesson_deadline_modal', ['lesson' => $lesson])
		                        @endif
		                    @endforeach
	                </div>
	                @endif
                </div>
            </main>

            <aside class="gc-course-program-sidebar">
                @if ($isLearner)
                <div class="gc-course-program-summary">
                    <section class="course-support-card gc-course-learning-focus" aria-label="Ваша работа в главе">
                        <div>
                            @php
                                $max_points = 0;
                                $points = 0;
                                foreach ($steps as $step) {
                                    $tasks = $step->tasks;
                                    foreach ($tasks as $task) {
                                        if (!$task->isVisible($user, $course)) continue;
                                        if ($task->answer != null) continue;
                                        $filtered = $task->solutions->filter(function ($value) use ($user) {
                                            return $value->user_id == $user->id && !$value->is_quiz;
                                        });
                                        $mark = $filtered->max('mark');
                                        $mark = $mark == null ? 0 : $mark;

                                        if (!$task->is_star) $max_points += $task->max_mark;
                                        $points += $mark;
                                    }
                                }
                                $percent = $max_points != 0 ? min(100, $points * 100 / $max_points) : 0;
                                $gradeProgressWidth = max(0, min(100, (int) round($percent)));
                            @endphp

                            <h4 class="h5 d-flex justify-content-between align-items-center">
                                Баллы за главу
                                <span class="badge rounded-pill bg-body-tertiary">{{$points}} / {{$max_points}}</span>
                            </h4>

                            <div class="progress mb-2">
                                <div class="progress-bar progress-width-{{$gradeProgressWidth}}" role="progressbar" data-progress-width="{{$percent}}%" aria-valuenow="{{$gradeProgressWidth}}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>


                        </div>
                        <x-gc-deadline-preview :items="$courseDeadlines" :all-url="route('courses.deadlines', $course->id)" embedded :limit="2" class="gc-course-learning-focus__deadlines" />
                    </section>
                </div>
                @endif
                <div class="gc-course-program-about">
                @if($isManager)
                    <x-gc-deadline-preview :items="$courseDeadlines" :all-url="route('courses.deadlines', $course->id)" :personal="false" class="mb-3" />
                @endif
                <section class="course-info-panel mb-3">
                    <div class="course-info-panel__inner">
                        <div class="course-side-header">
                            <h2 class="course-side-title mb-0">О курсе</h2>
                            @if($isManager)
                                <span class="gc-course-state" title="{{ $courseStateLabels[$course->state] ?? $course->state }}">
                                    <i class="fas {{ $course->state === 'started' ? 'fa-circle-play' : ($course->state === 'ended' ? 'fa-circle-check' : 'fa-pen-to-square') }}" aria-hidden="true"></i>
                                    {{ $course->state === 'started' ? 'Идёт' : ($courseStateLabels[$course->state] ?? $course->state) }}
                                </span>
                            @endif
                        </div>

                        <div class="course-info-list">
                            @if ($isManager)
                                <div class="course-info-row min-width-0">
                                    <span>Код приглашения</span>
                                    <x-gc-copy-value :value="$course->invite" label="Скопировать код приглашения" />
                                </div>
                            @endif
                            @if ($course->git != null)
                                <div class="course-info-row min-width-0"><span>Git</span><a class="text-truncate d-block" href="{{ safe_url($course->git) }}">{{$course->git}}</a></div>
                            @endif
                            @if ($course->telegram != null)
                                <div class="course-info-row min-width-0"><span>Telegram</span><a class="text-truncate d-block" href="{{ safe_url($course->telegram) }}">{{$course->telegram}}</a></div>
                            @endif
                        </div>

	                        <div class="course-side-section">
	                            <h5 class="course-side-title">Преподаватели</h5>
	<ul class="list-unstyled gc-person-rows mb-0">
                                @foreach($statsTeachers as $teacher)
                                    <li><x-gc-person-row :user="$teacher" subtitle="Преподаватель" /></li>
                                @endforeach
                            </ul>
	                        </div>

                        @if($isManager || !$course->hide_leaderboard)
                            @php
                                $sortedStudents = $students->sortByDesc(fn ($student) => $student->points ?? 0)->values();
                                $leaderboardPreviewLimit = 6;
                                $leaderboardPreviewStudents = $sortedStudents->take($leaderboardPreviewLimit);
                                $currentStudentIndex = $isLearner ? $sortedStudents->search(fn ($student) => $student->id == $user->id) : false;
                                if ($currentStudentIndex !== false && $currentStudentIndex >= $leaderboardPreviewLimit) {
                                    $leaderboardPreviewStudents = $leaderboardPreviewStudents->concat($sortedStudents->where('id', $user->id));
                                }
                                $leaderboardExtraStudents = $sortedStudents->whereNotIn('id', $leaderboardPreviewStudents->pluck('id'))->values();
                            @endphp

                            <div class="course-leaderboard-header" id="course-leaderboard">
                                <div>
                                    <h5 class="course-side-title mb-0">Рейтинг учеников</h5>
                                    @if($course->hide_leaderboard)
                                        <span class="badge rounded-pill bg-body-tertiary text-body-secondary mt-1">Скрыт от учеников</span>
                                    @endif
                                </div>
                                <span class="badge rounded-pill bg-body-tertiary">{{ $students->count() }}</span>
                                @if($isManager && $students->isNotEmpty())
                                    <button class="btn btn-outline-secondary btn-sm gc-icon-button" type="button" data-bs-toggle="collapse" data-bs-target="#course-histogram" aria-expanded="false" aria-controls="course-histogram" aria-label="Распределение результатов" title="Распределение результатов"><i class="fas fa-chart-bar" aria-hidden="true"></i></button>
                                @endif
                            </div>
                            <ul class="list-unstyled gc-person-rows mb-2">
                                @foreach($leaderboardPreviewStudents as $student)
                                    @include('courses.partials.leaderboard_item', ['studentRank' => $sortedStudents->search(fn($rankedStudent) => $rankedStudent->id === $student->id) + 1])
                                @endforeach
                            </ul>

                            @if ($leaderboardExtraStudents->count())
                                <div class="collapse" id="course-leaderboard-all">
                                    <ul class="list-unstyled gc-person-rows mb-2">
                                        @foreach($leaderboardExtraStudents as $student)
                                            @include('courses.partials.leaderboard_item', ['studentRank' => $sortedStudents->search(fn($rankedStudent) => $rankedStudent->id === $student->id) + 1])
                                        @endforeach
                                    </ul>
                                </div>
                                <button type="button" class="btn course-leaderboard-more" data-bs-toggle="collapse" data-bs-target="#course-leaderboard-all" aria-expanded="false" aria-controls="course-leaderboard-all">
                                    <span class="course-leaderboard-more__show">Показать всех · {{$students->count()}}</span>
                                    <span class="course-leaderboard-more__hide">Свернуть</span>
                                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                                </button>
                            @endif
                            @if ($isManager && $students->isNotEmpty())
                                <div class="collapse course-histogram-plot" id="course-histogram">
                                    <div id="histogram" data-plotly-histogram='@json($students->pluck('percent')->values())'></div>
                                </div>
                            @endif
                        @endif
                    </div>
                </section>




                </div>
            </aside>
        </div>
    </div>
@endsection
