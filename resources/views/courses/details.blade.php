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
        $sortedStudents = $students->sortByDesc(function ($student) {
            return isset($student->points) ? $student->points : 0;
        })->values();
        $leaderboardPreviewLimit = 6;
        $leaderboardPreviewStudents = $sortedStudents->take($leaderboardPreviewLimit);
        $currentStudentRank = null;

        if ($isLearner && $course->students->contains($user)) {
            $currentStudentIndex = $sortedStudents->search(fn ($student) => $student->id == $user->id);
            if ($currentStudentIndex !== false) {
                $currentStudentRank = $currentStudentIndex + 1;
            }
        }

        $leaderboardPreviewStudents = $isLearner && $currentStudentRank > $leaderboardPreviewLimit
            ? $leaderboardPreviewStudents->concat($sortedStudents->where('id', $user->id))
            : $leaderboardPreviewStudents;
        $leaderboardExtraStudents = $sortedStudents->whereNotIn('id', $leaderboardPreviewStudents->pluck('id'))->values();
        $availablePathLessons = $pathLessons->filter(fn($lesson) => $lesson->isAvailableForUser($course, $user));
        $courseEnded = $course->state === 'ended';
        $lessonViews = $isLearner ? $availablePathLessons->mapWithKeys(fn ($lesson) => [$lesson->id => \App\Support\CourseLessonView::forStudent($lesson, $course, $user, $lessonStats[$lesson->id][$user->id] ?? null)]) : collect();
        $studyOutcome = $isLearner ? \App\Support\CourseStudyOutcome::forChapter($course, $chapter, $user, $availablePathLessons, $lessonViews) : null;
        $nextLesson = $isLearner && !$courseEnded ? $availablePathLessons->first(function ($lesson) use ($lessonStats, $user) {
            return ($lessonStats[$lesson->id][$user->id]->percent ?? 0) <= 90;
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
        <x-gc-course-header :course="$course" :chapter="$chapter" active="program" />

        <div class="gc-course-program-layout">
            <main class="gc-course-program-main">
                @if($course->program->chapters->count() > 1)
                <div class="gc-card gc-course-program-controls">
                    <x-gc-chapter-timeline :course="$course" :chapter="$chapter" :viewer="$user" :project-path="$coursePath" embedded>
                        <x-slot:actions>@include('courses.partials.program-actions')</x-slot:actions>
                    </x-gc-chapter-timeline>
                    @if($isManager && !$course->current_lesson_id)
                        <p class="gc-help-text gc-current-lesson-help mb-0">Отметьте текущий урок в его меню. Доступ к материалам задаётся расписанием.</p>
                    @endif
                    @include('courses.partials.due-summary')
                </div>
                @elseif($courseDeadlines->isNotEmpty())
                    <div class="gc-course-single-due gc-card">@include('courses.partials.due-summary')</div>
                @endif
                @include('courses.partials.study-outcome')
                <div class="gc-course-program-lessons">
                <div class="course-lessons-toolbar {{ $course->program->chapters->count() === 1 ? 'course-lessons-toolbar--single' : '' }} mb-2">
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
                            $isLessonStarted = $lesson->isStarted($course);
                            $hasEarlyAccess = $isLearner && $lesson->hasEarlyAccess($course, $user);
                            $canBuyEarlyAccess = $isLearner && $lesson->canBuyEarlyAccess($course, $user);
                            $earlyAccessCost = $isLearner ? $user->earlyLessonAccessCost($lesson) : $lesson->earlyAccessCost();
                            $canAffordEarlyAccess = $canBuyEarlyAccess && ($earlyAccessCost <= 0 || $userBalance >= $earlyAccessCost);
                            $isAvailable = $isManager || ($isLearner && ($isLessonStarted || $hasEarlyAccess));
                            $showTeacherLock = $isManager && !$isLessonStarted;
                            $startDate = $lesson->getStartDate($course);
                            $cstats = $isLearner ? ($lessonStats[$lesson->id][$cstudent->id] ?? null) : null;
                            $cpercent = $cstats ? $cstats->percent : 0;
                            $cdisplayPercent = $displayPercent($cpercent);
                            $cprogressWidth = (int) round($cdisplayPercent);
                            $cpoints = $cstats ? $cstats->points : 0;
	                            $cmaxPoints = $cstats ? $cstats->max_points : 0;
	                            $isDone = $isLearner && $cmaxPoints != 0 && $cpercent > 90;
	                            $isCurrent = $currentPathLessonId == $lesson->id;
	                            $lessonTaskCount = $lesson->steps->sum(function ($step) {
	                                return $step->tasks->count();
	                            });
	                            $lessonDeadline = $lesson->sharedDeadline($course->id);
	                            $lessonStateLabel = null;
	                            if ($isCurrent) {
                                $lessonStateLabel = $courseEnded ? 'Выбранный урок' : ($showTeacherLock ? 'Текущий урок · не открыт' : 'Текущий урок');
	                            } elseif ($showTeacherLock) {
	                                $lessonStateLabel = 'Не открыт';
	                            } elseif ($lessonView && $lessonView['status']) {
	                                $lessonStateLabel = $lessonView['status'];
	                            } elseif ($hasEarlyAccess) {
	                                $lessonStateLabel = 'Ранний доступ';
	                            } elseif ($canBuyEarlyAccess) {
	                                $lessonStateLabel = 'Можно открыть раньше';
	                            } elseif (!$isAvailable) {
	                                $lessonStateLabel = 'Закрыто';
	                            }
	                        @endphp

                        <article id="lesson-{{ $lesson->id }}" class="course-path-item @if ($isDone) is-done @endif @if ($isCurrent) is-current @endif @if (!$isAvailable) is-locked @endif @if (!$isDone && ($hasEarlyAccess || $canBuyEarlyAccess)) is-early-access @endif">
                            @php
                                $showLessonSticker = $lessonTaskCount > 0 && filled($lesson->sticker);
                                $rewardStatus = $isManager ? 'catalog' : ($cpercent > 90 ? 'earned' : 'preview');
                            @endphp

                            <div class="gc-card course-path-card course-lesson-panel">
                                <div class="course-path-card__main">
                                    <div class="course-path-card__heading @if($showLessonSticker || $isCurrent) has-visual @endif">
                                        <div class="course-path-eyebrow">
                                            <span>Урок {{ $pathIndex + 1 }}</span>
                                            @if($lessonStateLabel)<span class="course-path-status">{{ $lessonStateLabel }}</span>@endif
                                        </div>
                                        <h3 class="course-path-title" data-filter-by="text">
                                            @if($isAvailable)
                                                <a href="{{ url('/insider/courses/'.$course->id.'/steps/'.($isCurrent && $nextLearningStep ? $nextLearningStep->id : $lesson->steps->first()->id)) }}">{{ $lesson->name }}</a>
                                            @else
                                                <span>{{ $lesson->name }}</span>
                                            @endif
                                        </h3>
                                        @if ($isManager)
                                            <div class="course-lesson-actions course-path-actions">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-options" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Действия с уроком {{ $lesson->name }}">
                                                        <i class="fas fa-ellipsis-h" aria-hidden="true"></i>
                                                    </button>

                                                    <div class="dropdown-menu dropdown-menu-end">
                                                            <form method="POST" action="{{ route('courses.current-lesson', $course->id) }}">
                                                                @csrf
                                                                <input type="hidden" name="lesson_id" value="{{ $course->current_lesson_id == $lesson->id ? '' : $lesson->id }}">
                                                                <input type="hidden" name="chapter" value="{{ $chapter->id }}">
                                                                <button type="submit" class="dropdown-item"><i class="fas fa-thumbtack" aria-hidden="true"></i> {{ $course->current_lesson_id == $lesson->id ? 'Снять отметку текущего урока' : 'Сделать текущим уроком' }}</button>
                                                            </form>
                                                                <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/edit')}}" class="dropdown-item"><i class="fas fa-pen" aria-hidden="true"></i> Изменить</a>
                                                                @if ($lessonTaskCount > 0)
                                                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#lesson-deadline-modal-{{$lesson->id}}"><i class="fas fa-calendar-days" aria-hidden="true"></i> Дедлайн задач</button>
                                                                @endif
                                                                <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/export')}}" class="dropdown-item"><i class="fas fa-download" aria-hidden="true"></i> Экспорт</a>
                                                                <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/export-points')}}" class="dropdown-item"><i class="fas fa-chart-column" aria-hidden="true"></i> Экспорт баллов</a>
                                                            <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/export-md')}}" class="dropdown-item"><i class="fas fa-file-export" aria-hidden="true"></i> Экспорт в MD</a>
                                                            <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/lower?chapter='.$chapter->id)}}" class="dropdown-item"><i class="fas fa-arrow-up" aria-hidden="true"></i> Выше</a>
                                                            <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/upper?chapter='.$chapter->id)}}" class="dropdown-item"><i class="fas fa-arrow-down" aria-hidden="true"></i> Ниже</a>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                        @if(filled($lesson->description))
                                            <div class="course-path-description" data-filter-by="text">@parsedown($lesson->description)</div>
                                        @endif
                                        @if($showLessonSticker || $isCurrent)
                                            <x-gc-lesson-reward :lesson="$lesson" :course="$course" :sticker="$showLessonSticker" :status="$rewardStatus" />
                                        @endif
                                        @if($isAvailable && $isCurrent)
                                            <x-gc-lesson-outline :course="$course" :lesson="$lesson" expanded :next-step="$nextLearningStep" :next-label="$focusView['hint'] ?? 'Продолжить'" />
                                        @endif
                                    </div>

                                    @if($isLearner)
                                        @php $lessonDeadlines = $courseDeadlines->filter(fn ($deadline) => $deadline->task->step->lesson_id == $lesson->id); @endphp
                                        @if($lessonDeadlines->isNotEmpty())
                                            <details class="gc-disclosure gc-disclosure--inline mt-3" @if($isCurrent || $lessonDeadlines->contains('is_overdue', true) || $lessonDeadlines->contains('is_today', true)) open @endif>
                                                <summary>Сроки сдачи · {{ $lessonDeadlines->count() }}</summary>
                                                <ul class="gc-lesson-deadlines list-unstyled mb-0">
                                                    @foreach($lessonDeadlines as $deadline)
                                                        <li><a href="{{ url('/insider/courses/'.$course->id.'/steps/'.$deadline->task->step_id.'#task'.$deadline->task_id) }}">{{ $deadline->task->name }}</a><span><x-gc-deadline-label :expiration="$deadline->expiration" :rule="$deadline" /> · {{ (int) round($deadline->penalty * 100) }}%</span></li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @endif
                                    @endif
                                    @if ($isManager && $lessonTaskCount > 0 && $students->isNotEmpty())
                                        @php $lessonSummary = \App\Support\CourseLessonStatistics::summary($lessonStats->get($lesson->id, collect()), $students->count()); @endphp
                                        <div class="course-lesson-stats d-none mt-3" id="marks{{$lesson->id}}" data-course-stats-panel data-course-stats-url="{{ route('courses.lesson-statistics', [$course->id, $lesson->id]) }}" aria-live="polite"></div>
                                    @endif
                                </div>

                                <div class="course-path-card__footer">
                                    @if($isCurrent && $isAvailable)
                                        <a class="btn btn-primary btn-sm course-continue" href="{{ url('/insider/courses/'.$course->id.'/steps/'.($nextLearningStep?->id ?? $lesson->steps->first()->id)) }}">{{ $isManager ? ($courseEnded ? 'Открыть материалы' : 'Открыть урок') : $focusView['action'] }} <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                                    @endif
                                    <div class="course-path-meta">
                                        @unless($isCurrent)<span class="course-path-step-count">Ступеней: {{ $lesson->steps->count() }}</span>@endunless
                                        @if ($lessonDeadline && (!$isLearner || $lessonDeadlines->isEmpty()))
                                            <small class="course-lesson-date"><i class="fas fa-calendar-days" aria-hidden="true"></i> <x-gc-deadline-label :expiration="$lessonDeadline->expiration" :rule="$lessonDeadline" /></small>
                                            <x-gc-deadline-chain :rule="$lessonDeadline" />
                                        @endif

                                        @if ($startDate != null && $startDate->isFuture())
                                            <small class="course-lesson-date"><i class="fas fa-clock" aria-hidden="true"></i> Доступно с {{$startDate->translatedFormat('j F Y')}}</small>
                                        @endif

                                        @if ($lesson->is_open && $isManager)
                                            <a class="btn btn-outline-secondary btn-sm rounded-3 course-secondary-action course-open-url-action" href="{{ url('/open/steps/'.$lesson->steps->first()->id) }}" target="_blank" rel="noopener"><i class="fas fa-users" aria-hidden="true"></i> Открытый URL</a>
                                        @endif

                                        @if ($canBuyEarlyAccess)
                                            @if ($canAffordEarlyAccess)
                                                <form method="POST" action="{{ url('/insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/early-access') }}" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm solution-special-action course-early-access-action"
                                                            data-confirm="{{ $earlyAccessCost > 0 ? 'Купить ранний доступ к этому уроку за '.$earlyAccessCost.' GC?' : 'Открыть этот урок бесплатно с помощью Брр Брр Потапим?' }}">
                                                        <i class="fas fa-key" aria-hidden="true"></i>
                                                        @if ($earlyAccessCost > 0)
                                                            Открыть раньше за {{ $earlyAccessCost }} GC
                                                        @else
                                                            Открыть раньше бесплатно
                                                        @endif
                                                    </button>
                                                </form>
                                            @else
                                                <small class="course-early-access-note"><i class="fas fa-key" aria-hidden="true"></i> Ранний доступ: {{ $earlyAccessCost }} GC</small>
                                            @endif
                                        @endif
                                    </div>

                                    <div class="course-path-progress">
                                        @if ($isLearner && $lessonTaskCount > 0 && $lesson->isAvailable($course) && $cmaxPoints != 0)
                                            <span class="course-path-progress__label">{{$cpoints}} / {{$cmaxPoints}} XP</span>
                                            <div class="progress">
                                                <div class="progress-bar progress-width-{{$cprogressWidth}}" role="progressbar" data-progress-width="{{$cdisplayPercent}}%" aria-valuenow="{{$cprogressWidth}}" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        @endif

                                        @if ($isManager && $lessonTaskCount > 0 && $students->isNotEmpty())
                                            <div class="course-lesson-progress-summary" data-course-stats-summary="#marks{{$lesson->id}}" title="Средний прогресс: {{ $lessonSummary['average'] }}%">
                                                <span>Наклейка: {{ $lessonSummary['rewarded'] }} из {{ $lessonSummary['total'] }}</span>
                                                <span class="course-lesson-progress-summary__track" aria-hidden="true">
                                                    <span data-progress-width="{{ $lessonSummary['average'] }}%"></span>
                                                </span>
                                            </div>
                                            <button type="button" class="btn btn-outline-secondary btn-sm course-secondary-action course-stats-toggle course-lesson-stat-action" data-course-stats-toggle data-course-stats-target="#marks{{$lesson->id}}" aria-expanded="false" aria-controls="marks{{$lesson->id}}"><i class="fas fa-chart-bar" aria-hidden="true"></i> <span data-course-stats-label>Статистика</span></button>
                                        @endif
                                    </div>
                                </div>
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
                    <x-gc-deadline-preview :items="$courseDeadlines" :all-url="route('courses.deadlines', $course->id)" class="mb-3" />
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

                        <div class="course-leaderboard-header" id="course-leaderboard">
                            <h5 class="course-side-title mb-0">Рейтинг учеников</h5>
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
                    </div>
                </section>




                </div>
            </aside>
        </div>
    </div>
@endsection
