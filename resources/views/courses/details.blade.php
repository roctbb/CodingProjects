@extends('layouts.left-menu')

@section('title')
    {{$course->name}}
@endsection

@section('tabs')
@endsection


@section('head')
    <script src="{{ asset('build/js/vendor/plotly.min.js') }}"></script>
    <script type="module" src="{{ asset('build/js/course-stats.js') }}"></script>
@endsection


@section('content')
    @php
        $isManager = $course->teachers->contains($user) || $user->role == 'admin';
        $isLearner = $user->role == 'student' || ($user->role == 'teacher' && !$course->teachers->contains($user));
        $studentsCount = $course->students->count();
        $userBalance = $isLearner ? $user->balance() : 0;
    @endphp

    <div class="course-page">
        <div class="gc-title-row course-details-heading">
            <div class="d-flex flex-column align-items-start gap-2 min-width-0">
                <h2 class="mb-1">{{$course->name}}</h2>
                <p class="course-description mb-0">{{$course->description}}</p>
                <ul class="avatars course-header-avatars">
                    @foreach($course->students as $student)
                        @if ($loop->iteration > 12)
                            @continue
                        @endif
                        <li>
                            <a href="{{ url('insider/profile/'.$student->id) }}" data-bs-toggle="tooltip" title="{{ $student->name }}@if($student->activeCustomTitle()) · {{ $student->activeCustomTitle() }}@endif">
                                <x-gc-avatar :user="$student" size="sm" class="course-header-avatar" alt="" />
                            </a>
                        </li>
                    @endforeach

                    @if ($studentsCount > 12)
                        <li><span class="course-avatar-more">+{{ $studentsCount - 12 }}</span></li>
                    @endif
                </ul>
            </div>

            @if ($isManager)
                <div class="dropdown course-actions ms-md-3">
                    <button class="btn btn-outline-success btn-sm course-actions__toggle" data-bs-toggle="dropdown" data-bs-target="#project-add-modal" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-plus me-1"></i> Действия
                    </button>

                    <div class="dropdown-menu dropdown-menu-end">
                        <a href="{{url('/insider/courses/'.$course->id.'/create?chapter='.$chapter->id)}}" class="dropdown-item"><i class="icon ion-compose"></i> Добавить урок</a>
                        <a href="{{url('/insider/courses/'.$course->id.'/chapter')}}" class="dropdown-item"><i class="icon ion-plus"></i> Добавить главу</a>
                        <a href="{{url('/insider/courses/'.$course->id.'/edit')}}" class="dropdown-item"><i class="icon ion-android-create"></i> Изменить курс</a>
                        <a href="{{url('/insider/courses/'.$course->id.'/export-md')}}" class="dropdown-item"><i class="icon ion-document-text"></i> Экспорт в MD</a>
                        @if ($course->state == "draft")
                            <a href="{{url('/insider/courses/'.$course->id.'/start')}}" class="dropdown-item"><i class="icon ion-power"></i> Запустить курс</a>
                        @elseif ($course->state == "started")
                            <a href="{{url('/insider/courses/'.$course->id.'/stop')}}" class="dropdown-item"><i class="icon ion-stop"></i> Завершить курс</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="row align-items-start course-layout-row">
            <main class="col-xl-9 col-lg-8">
                @if ($course->state == "ended" && $isManager)
                    <section class="gc-card mb-3 course-support-card">
                        <div class="p-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <a data-bs-toggle="collapse" href="#certs" role="button" aria-expanded="false" aria-controls="certs"><strong>Сертификаты</strong></a>
                                <a href="{{url('insider/courses/'.$course->id.'/stop')}}" class="btn btn-outline-secondary btn-sm rounded-3 ms-2">Перевыпуск</a>
                            </div>

                            <div class="collapse mt-3" id="certs">
                                <ul class="mb-0 ps-3">
                                    @foreach($marks as $mark)
                                        @if ($mark->cert_link != null)
                                            <li>
                                                <a target="_blank" href="{{$mark->cert_link}}" class="course-row-between">
                                                    <span class="d-inline-flex align-items-center gap-1 min-width-0">
                                                        <span class="text-truncate">{{$mark->user->name}}</span>
                                                        @include('profile.partials.custom_title_badge', ['profileUser' => $mark->user, 'compact' => true])
                                                    </span>
                                                    <span class="badge rounded-pill bg-body-tertiary">{{$mark->mark}}</span>
                                                </a>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </section>
                @endif

                <div class="course-lessons-toolbar mb-2">
                    <div class="course-lessons-toolbar__title">
                        <div class="course-section-label">Уроки</div>
                        <small class="text-muted">{{ $lessons->count() }}</small>
                    </div>

                    @if ($course->program->chapters->count() > 1)
                        @php
                            $activeChapterPercent = null;
                            if ($isManager && $chapter->isStarted($course)) {
                                $activeChapterPercent = round($chapterProgress[$chapter->id] ?? 0);
                            } elseif ($isLearner) {
                                $activeChapterPercent = round($chapterProgress[$chapter->id] ?? 0);
                            }
                        @endphp

                        <div class="course-chapter-switcher-wrap">
                            <div class="dropdown course-chapter-switcher">
                                <button class="btn course-chapter-switcher__toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="course-chapter-switcher__label">Глава</span>
                                    <span class="course-chapter-switcher__name text-truncate">{{$chapter->name}}</span>
                                    @if (!is_null($activeChapterPercent))
                                        <span class="course-chapter-switcher__percent">{{$activeChapterPercent}} %</span>
                                    @endif
                                    <i class="fas fa-chevron-down"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end course-chapter-switcher__menu">
                                    @foreach($course->program->chapters as $current_chapter)
                                        @if ($user->role == 'admin' || $course->teachers->contains($user) || $current_chapter->isStarted($course))
                                            @php
                                                $chapterPercent = null;
                                                if ($isManager && $current_chapter->isStarted($course)) {
                                                    $chapterPercent = round($chapterProgress[$current_chapter->id] ?? 0);
                                                } elseif ($isLearner) {
                                                    $chapterPercent = round($chapterProgress[$current_chapter->id] ?? 0);
                                                }
                                            @endphp

                                            <a href="{{url('/insider/courses/'.$course->id.'?chapter='.$current_chapter->id)}}" class="dropdown-item course-chapter-switcher__item @if ($current_chapter->id == $chapter->id) active @endif">
                                                <span class="course-chapter-switcher__item-name text-truncate">{{$current_chapter->name}}</span>
                                                @if ($course->default_chapter_id == $current_chapter->id)
                                                    <span class="course-chapter-switcher__default">по умолчанию</span>
                                                @endif
                                                @if (!is_null($chapterPercent))
                                                    <span class="course-chapter-switcher__item-percent">{{$chapterPercent}} %</span>
                                                @endif
                                                @if (!$isManager && ($chapterProgress[$current_chapter->id] ?? 0) >= 100)
                                                    <i class="icon ion-checkmark-circled course-chapter-switcher__done"></i>
                                                @endif
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>

                            @if ($isManager)
                                <div class="dropdown course-current-chapter-actions">
                                    <button class="btn btn-outline-secondary btn-sm rounded-3 gc-icon-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Действия с главой">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>

                                    <div class="dropdown-menu dropdown-menu-end">
                                        @if ($course->default_chapter_id == $chapter->id)
                                            <span class="dropdown-item disabled"><i class="fas fa-bullseye"></i> Открывается по умолчанию</span>
                                        @else
                                            <form method="POST" action="{{ url('insider/courses/'.$course->id.'/chapters/'.$chapter->id.'/default') }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item"><i class="fas fa-bullseye"></i> Открывать по умолчанию</button>
                                            </form>
                                        @endif
                                        <a href="{{url('insider/courses/'.$course->id.'/chapters/'.$chapter->id.'/edit')}}" class="dropdown-item"><i class="icon ion-android-create"></i> Изменить главу</a>
                                        <a href="{{url('insider/courses/'.$course->id.'/chapters/'.$chapter->id.'/lower')}}" class="dropdown-item"><i class="icon ion-arrow-up-c"></i> Выше</a>
                                        <a href="{{url('insider/courses/'.$course->id.'/chapters/'.$chapter->id.'/upper')}}" class="dropdown-item"><i class="icon ion-arrow-down-c"></i> Ниже</a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                @php
                    $pathLessons = $lessons->filter(fn($lesson) => $lesson->steps->count() > 0)->values();
                    $currentPathLessonId = null;
                    $spotlightPathLesson = null;

                    if ($isLearner) {
                        foreach ($pathLessons as $pathLesson) {
                            if (!$pathLesson->isAvailable($course)) {
                                continue;
                            }

                            $pathStats = $lessonStats[$pathLesson->id][$cstudent->id] ?? null;
                            $pathPercent = $pathStats ? $pathStats->percent : 0;

                            if ($pathPercent < 90) {
                                $currentPathLessonId = $pathLesson->id;
                                break;
                            }
                        }

                        foreach ($pathLessons as $pathLesson) {
                            if (!$pathLesson->isAvailable($course)) {
                                continue;
                            }

                            $spotlightPathLesson = $pathLesson;

                            if ($currentPathLessonId == $pathLesson->id) {
                                break;
                            }
                        }
	                    } else {
	                        $spotlightPathLesson = $pathLessons->first();
	                    }
	                @endphp

	                @if ($pathLessons->isEmpty())
	                    @include('courses.partials.home_empty_state', [
	                        'icon' => $isManager ? 'fas fa-layer-group' : 'fas fa-hourglass-half',
	                        'title' => $isManager ? 'В этой главе пока нет уроков' : 'Уроки ещё готовятся',
	                        'text' => $isManager ? 'Добавьте первый урок, чтобы ученикам было куда двигаться.' : 'Когда преподаватель откроет материалы, они появятся здесь.',
	                        'actionUrl' => $isManager ? url('/insider/courses/'.$course->id.'/create?chapter='.$chapter->id) : null,
	                        'actionText' => $isManager ? 'Добавить урок' : null,
	                    ])
	                @else
	                @if ($spotlightPathLesson)
	                    @php
	                        $spotlightIndex = $pathLessons->search(fn($pathLesson) => $pathLesson->id == $spotlightPathLesson->id);
                        $spotlightStats = $isLearner ? ($lessonStats[$spotlightPathLesson->id][$cstudent->id] ?? null) : null;
                        $spotlightPercent = $spotlightStats ? $spotlightStats->percent : 0;
                        $spotlightProgressWidth = max(0, min(100, (int) round($spotlightPercent)));
                        $spotlightPoints = $spotlightStats ? $spotlightStats->points : 0;
                        $spotlightMaxPoints = $spotlightStats ? $spotlightStats->max_points : 0;
                        $spotlightStartDate = $spotlightPathLesson->getStartDate($course);
                    @endphp

                    <section class="course-path-spotlight p-3 p-md-4 mb-3 rounded-3 border bg-body">
                        <div class="course-path-spotlight__content">
                            <span class="text-muted text-uppercase fw-semibold small d-block mb-2">{{ $isLearner ? 'Следующий урок' : 'Первый урок главы' }}</span>
                            <h3>{{$spotlightPathLesson->name}}</h3>
                            <div class="course-path-spotlight__meta d-flex flex-wrap gap-2 mt-2 text-muted small">
                                <span class="badge rounded-pill bg-body-tertiary">Урок {{$spotlightIndex + 1}}</span>
                                @if ($spotlightStartDate != null)
                                    <span class="badge rounded-pill bg-body-tertiary"><i class="ion ion-clock me-1"></i>{{$spotlightStartDate->format('Y-m-d')}}</span>
                                @endif
                                @if ($isLearner && $spotlightMaxPoints != 0)
                                    <span class="badge rounded-pill bg-body-tertiary">{{$spotlightPoints}} / {{$spotlightMaxPoints}} XP</span>
                                @endif
                            </div>
                        </div>

                        @if ($isLearner && $spotlightMaxPoints != 0)
                            <div class="course-path-spotlight__progress">
                                <strong>{{round($spotlightPercent)}}%</strong>
                                <div class="progress">
                                    <div class="progress-bar progress-width-{{$spotlightProgressWidth}}" role="progressbar" data-progress-width="{{$spotlightPercent}}%" aria-valuenow="{{$spotlightProgressWidth}}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        @endif

                        <a class="btn btn-success course-path-spotlight__button" href="{{url('/insider/courses/'.$course->id.'/steps/'.$spotlightPathLesson->steps->first()->id)}}">
                            {{ $isLearner ? 'Продолжить' : 'Открыть урок' }}
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </section>
                @endif

                <div class="course-learning-path">
                    @foreach($pathLessons as $pathIndex => $lesson)
                        @php
                            $isLessonStarted = $lesson->isStarted($course);
                            $hasEarlyAccess = $isLearner && $lesson->hasEarlyAccess($course, $user);
                            $canBuyEarlyAccess = $isLearner && $lesson->canBuyEarlyAccess($course, $user);
                            $earlyAccessCost = $lesson->earlyAccessCost();
                            $canAffordEarlyAccess = $canBuyEarlyAccess && $userBalance >= $earlyAccessCost;
                            $isAvailable = $lesson->isAvailable($course) || $isManager;
                            $showTeacherLock = $isManager && !$isLessonStarted;
                            $startDate = $lesson->getStartDate($course);
                            $cstats = $isLearner ? ($lessonStats[$lesson->id][$cstudent->id] ?? null) : null;
                            $cpercent = $cstats ? $cstats->percent : 0;
                            $cprogressWidth = max(0, min(100, (int) round($cpercent)));
                            $cpoints = $cstats ? $cstats->points : 0;
	                            $cmaxPoints = $cstats ? $cstats->max_points : 0;
	                            $isDone = $isLearner && $cmaxPoints != 0 && $cpercent >= 90;
	                            $isCurrent = $isLearner && $currentPathLessonId == $lesson->id;
	                            $lessonTaskCount = $lesson->steps->sum(function ($step) {
	                                return $step->tasks->count();
	                            });
	                        @endphp

                        <article class="course-path-item @if ($isDone) is-done @endif @if ($isCurrent) is-current @endif @if (!$isAvailable) is-locked @endif @if (!$isDone && ($hasEarlyAccess || $canBuyEarlyAccess)) is-early-access @endif">
                            <div class="course-path-node" aria-hidden="true">
                                @if ($isDone)
                                    <i class="fas fa-check"></i>
                                @elseif ($showTeacherLock || !$isAvailable)
                                    <i class="fas fa-lock"></i>
                                @else
                                    <span>{{$pathIndex + 1}}</span>
                                @endif
                            </div>

                            @php
                                $showLessonSticker = $isLearner && $cpercent > 90;
                            @endphp

                            <div class="course-path-card rounded-3 border bg-body">
                                <div class="course-path-card__main p-3">
                                    <div class="course-path-card__topline d-flex justify-content-between gap-3 mb-2">
                                        <div class="course-path-kicker">
                                            Урок {{$pathIndex + 1}}
                                            @if ($isCurrent)
                                                <span>Текущий шаг</span>
                                            @elseif ($isDone)
                                                <span>Завершено</span>
                                            @elseif ($hasEarlyAccess)
                                                <span>Ранний доступ</span>
                                            @elseif ($canBuyEarlyAccess)
                                                <span>Можно открыть раньше</span>
                                            @elseif (!$isAvailable)
                                                <span>Закрыто</span>
                                            @endif
                                        </div>

                                        @if ($isManager)
                                            <div class="course-lesson-actions course-path-actions">
                                                <div class="dropdown">
                                                    <button class="btn btn-outline-secondary btn-sm rounded-3 gc-icon-button" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>

                                                    <div class="dropdown-menu dropdown-menu-end">
	                                                        <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/edit')}}" class="dropdown-item"><i class="icon ion-android-create"></i> Изменить</a>
	                                                        @if ($lessonTaskCount > 0)
	                                                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#lesson-deadline-modal-{{$lesson->id}}"><i class="icon ion-ios-calendar"></i> Дедлайн задач</button>
	                                                        @endif
	                                                        <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/export')}}" class="dropdown-item"><i class="icon ion-ios-cloud-download"></i> Экспорт</a>
                                                        <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/export-md')}}" class="dropdown-item"><i class="icon ion-document-text"></i> Экспорт в MD</a>
                                                        <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/lower?chapter='.$chapter->id)}}" class="dropdown-item"><i class="icon ion-arrow-up-c"></i> Выше</a>
                                                        <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/upper?chapter='.$chapter->id)}}" class="dropdown-item"><i class="icon ion-arrow-down-c"></i> Ниже</a>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="course-path-card__content @if($showLessonSticker) has-sticker @endif">
                                        <div class="min-width-0">
                                            <h5 class="course-path-title" data-filter-by="text">
                                                @if ($isAvailable)
                                                    <a href="{{url('/insider/courses/'.$course->id.'/steps/'.$lesson->steps->first()->id)}}">{{$lesson->name}}</a>
                                                @else
                                                    <span>{{$lesson->name}}</span>
                                                @endif
                                            </h5>

                                            <div class="course-path-description" data-filter-by="text">
                                                @parsedown($lesson->description)
                                            </div>
                                        </div>

                                        @if ($showLessonSticker)
                                            <div class="course-path-sticker-wrap">
                                                <img src="{{url($lesson->sticker)}}" class="course-path-sticker" alt=""/>
                                            </div>
                                        @endif
                                    </div>

                                    @if ($isManager && count($students) < 70)
                                        @php
                                            $lessonStatRows = $students->map(function ($student) use ($lessonStats, $lesson) {
                                                $stats = $lessonStats[$lesson->id][$student->id] ?? null;
                                                $percent = $stats ? $stats->percent : 0;

                                                return [
                                                    'student' => $student,
                                                    'percent' => $percent,
                                                    'progressWidth' => max(0, min(100, (int) round($percent))),
                                                    'points' => $stats ? $stats->points : 0,
                                                    'maxPoints' => $stats ? $stats->max_points : 0,
                                                ];
                                            });
                                            $lessonAveragePercent = round($lessonStatRows->avg('percent') ?? 0);
                                            $lessonCompletedCount = $lessonStatRows->filter(fn($row) => $row['percent'] >= 90)->count();
                                        @endphp

                                        <div class="course-lesson-stats d-none mt-3" id="marks{{$lesson->id}}" data-course-stats-panel>
                                            <div class="course-lesson-stats__summary">
                                                <div>
                                                    <span>Средний прогресс</span>
                                                    <strong>{{$lessonAveragePercent}}%</strong>
                                                </div>
                                                <div>
                                                    <span>Завершили</span>
                                                    <strong>{{$lessonCompletedCount}} / {{$students->count()}}</strong>
                                                </div>
                                                <div>
                                                    <span>Участников</span>
                                                    <strong>{{$students->count()}}</strong>
                                                </div>
                                            </div>

                                            <div class="course-lesson-stats__rows">
                                                @foreach($lessonStatRows as $statRow)
                                                    @php
                                                        $student = $statRow['student'];
                                                        $percent = $statRow['percent'];
                                                        $progressWidth = $statRow['progressWidth'];
                                                        $points = $statRow['points'];
                                                        $maxPoints = $statRow['maxPoints'];
                                                    @endphp

                                                    <div class="course-stat-row small {{ $percent < 40 ? 'is-low' : ($percent < 60 ? 'is-mid' : 'is-high') }}" title="{{$student->name}}@if($student->activeCustomTitle()) · {{ $student->activeCustomTitle() }}@endif: {{$points}} / {{$maxPoints}} ({{round($percent)}}%)" aria-label="{{$student->name}}@if($student->activeCustomTitle()) · {{ $student->activeCustomTitle() }}@endif: {{$points}} / {{$maxPoints}} ({{round($percent)}}%)">
                                                        <span class="course-stat-name">
                                                            <span class="text-truncate">{{$student->name}}</span>
                                                        </span>
                                                        <span class="course-stat-mini">
                                                            <span class="course-stat-mini__bar progress-width-{{$progressWidth}}" data-progress-width="{{$percent}}%"></span>
                                                            <span class="course-stat-mini__value">{{$points}} / {{$maxPoints}}</span>
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="course-path-card__footer px-3 py-2">
                                    <div class="course-path-meta">
                                        @if ($startDate != null)
                                            <small class="course-lesson-date"><i class="ion ion-clock"></i> Доступно с {{$startDate->format('Y-m-d')}}</small>
                                        @endif

                                        @if ($lesson->is_open && $isManager)
                                            <a class="btn btn-outline-secondary btn-sm rounded-3 course-secondary-action course-open-url-action" href="{{ url('/open/steps/'.$lesson->steps->first()->id) }}" target="_blank" rel="noopener"><i class="ion ion-android-contacts"></i> Открытый URL</a>
                                        @endif

                                        @if ($canBuyEarlyAccess)
                                            @if ($canAffordEarlyAccess)
                                                <form method="POST" action="{{ url('/insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/early-access') }}" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm solution-special-action course-early-access-action"
                                                            data-confirm="Купить ранний доступ к этому уроку за {{ $earlyAccessCost }} GC?">
                                                        <i class="fas fa-key"></i>
                                                        Открыть раньше за {{ $earlyAccessCost }} GC
                                                    </button>
                                                </form>
                                            @else
                                                <small class="course-early-access-note"><i class="fas fa-key"></i> Ранний доступ: {{ $earlyAccessCost }} GC</small>
                                            @endif
                                        @endif
                                    </div>

                                    <div class="course-path-progress">
                                        @if ($isLearner && $lesson->isAvailable($course) && $cmaxPoints != 0)
                                            <span class="course-path-progress__label">{{$cpoints}} / {{$cmaxPoints}} XP</span>
                                            <div class="progress">
                                                @if ($cpercent < 40)
                                                    <div class="progress-bar progress-bar-striped bg-danger progress-width-{{$cprogressWidth}}" role="progressbar" data-progress-width="{{$cpercent}}%" aria-valuenow="{{$cprogressWidth}}" aria-valuemin="0" aria-valuemax="100">{{round($cpercent)}}%</div>
                                                @elseif($cpercent < 60)
                                                    <div class="progress-bar progress-bar-striped bg-warning progress-width-{{$cprogressWidth}}" role="progressbar" data-progress-width="{{$cpercent}}%" aria-valuenow="{{$cprogressWidth}}" aria-valuemin="0" aria-valuemax="100">{{round($cpercent)}}%</div>
                                                @else
                                                    <div class="progress-bar progress-bar-striped bg-success progress-width-{{$cprogressWidth}}" role="progressbar" data-progress-width="{{$cpercent}}%" aria-valuenow="{{$cprogressWidth}}" aria-valuemin="0" aria-valuemax="100">{{round($cpercent)}}%</div>
                                                @endif
                                            </div>
                                        @endif

                                        @if ($isManager && count($students) < 70)
                                            <div class="course-lesson-stat-badges" data-course-stats-summary="#marks{{$lesson->id}}">
                                                @foreach($students as $student)
                                                    @php
                                                        $stats = $lessonStats[$lesson->id][$student->id] ?? null;
                                                        $percent = $stats ? $stats->percent : 0;
                                                        $badgeClass = $percent < 40 ? 'is-low' : ($percent < 60 ? 'is-mid' : 'is-high');
                                                    @endphp
                                                    <span class="course-lesson-stat-badge {{$badgeClass}}" title="{{$student->name}}@if($student->activeCustomTitle()) · {{ $student->activeCustomTitle() }}@endif: {{round($percent)}}%" aria-label="{{$student->name}}@if($student->activeCustomTitle()) · {{ $student->activeCustomTitle() }}@endif: {{round($percent)}}%"></span>
                                                @endforeach
                                            </div>
                                            <button type="button" class="btn btn-outline-secondary btn-sm course-secondary-action course-stats-toggle course-lesson-stat-action" data-course-stats-toggle data-course-stats-target="#marks{{$lesson->id}}" aria-expanded="false" aria-controls="marks{{$lesson->id}}"><i class="ion ion-stats-bars"></i> <span data-course-stats-label>Статистика</span></button>
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
	            </main>

            <aside class="col-xl-3 col-lg-4 sticky-lg-top">
                <section class="gc-card overflow-hidden mb-3">
                    <div class="p-3">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-circle-info"></i></span>
                            <div>
                                <h4 class="h5 mb-0">Информация</h4>
                                <small class="text-muted">Участники и ссылки</small>
                            </div>
                        </div>

                        <div class="d-flex flex-column gap-2 mb-3">
                            @if ($isManager)
                                <div class="gc-info-tile min-width-0"><span>Статус</span><strong>{{$course->state}}</strong></div>
                                <div class="gc-info-tile min-width-0"><span>Инвайт</span><strong>{{$course->invite}}</strong></div>
                            @endif
                            @if ($course->git != null)
                                <div class="gc-info-tile min-width-0"><span>Git</span><a class="text-truncate d-block" href="{{$course->git}}">{{$course->git}}</a></div>
                            @endif
                            @if ($course->telegram != null)
                                <div class="gc-info-tile min-width-0"><span>Telegram</span><a class="text-truncate d-block" href="{{$course->telegram}}">{{$course->telegram}}</a></div>
                            @endif
                        </div>

                        <p class="gc-eyebrow mb-2">Преподаватели</p>
	                        <ul class="list-unstyled d-flex flex-column gap-2 mb-3">
	                            @foreach($course->teachers as $teacher)
	                                <li class="d-flex align-items-center gap-2 min-width-0">
	                                    <x-gc-avatar :user="$teacher" size="sm" alt="" />
	                                    <a class="text-decoration-none min-width-0 d-inline-flex align-items-center gap-1" href="{{url('/insider/profile/'.$teacher->id)}}">
                                            <span class="text-truncate">{{$teacher->name}}</span>
                                            @include('profile.partials.custom_title_badge', ['profileUser' => $teacher, 'compact' => true])
                                        </a>
	                                </li>
	                            @endforeach
	                        </ul>

	                        @php
	                            $deadlineItems = isset($courseDeadlines) ? $courseDeadlines : collect();
	                            $overdueDeadlines = $deadlineItems->where('is_overdue', true);
	                            $upcomingDeadlineCount = $deadlineItems->where('is_overdue', false)->count();
	                        @endphp

	                        @if ($deadlineItems->count() || $isLearner || $isManager)
	                            <div class="course-deadlines">
	                                <div class="course-deadlines__header">
	                                    <div>
	                                        <p class="gc-eyebrow">Дедлайны</p>
	                                        <h5 class="mb-0">Ближайшие сроки</h5>
	                                    </div>
	                                    @if ($overdueDeadlines->count())
	                                        <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle">{{$overdueDeadlines->count()}}</span>
	                                    @elseif ($upcomingDeadlineCount)
	                                        <span class="badge rounded-pill bg-body-tertiary">{{$upcomingDeadlineCount}}</span>
	                                    @endif
	                                </div>

	                                @if ($deadlineItems->count())
	                                    <div class="course-deadlines__list">
	                                        @foreach($deadlineItems->take(6) as $deadline)
	                                            @php
	                                                $deadlineTask = $deadline->task;
	                                                $deadlineStep = $deadlineTask ? $deadlineTask->step : null;
	                                                $deadlineLesson = $deadlineStep ? $deadlineStep->lesson : null;
	                                                $deadlineUrl = $deadlineStep ? url('/insider/courses/'.$course->id.'/steps/'.$deadlineStep->id.'#task'.$deadlineTask->id) : '#';
	                                            @endphp
	                                            <a class="course-deadline-item @if ($deadline->is_overdue) is-overdue @elseif ($deadline->is_soon) is-soon @endif" href="{{$deadlineUrl}}">
	                                                <span class="course-deadline-date">
	                                                    <strong>{{$deadline->expiration->format('d.m')}}</strong>
	                                                    <small>{{$deadline->expiration->format('Y')}}</small>
	                                                </span>
	                                                <span class="course-deadline-body min-width-0">
	                                                    <strong class="text-truncate">{{$deadlineTask ? $deadlineTask->name : 'Задача'}}</strong>
	                                                    <small class="text-muted text-truncate">{{$deadlineLesson ? $deadlineLesson->name : $chapter->name}}</small>
	                                                </span>
	                                                <span class="course-deadline-state">
	                                                    @if ($deadline->is_overdue)
	                                                        Просрочено
	                                                    @elseif ($deadline->is_soon)
	                                                        Скоро
	                                                    @else
	                                                        Срок
	                                                    @endif
	                                                </span>
	                                            </a>
	                                        @endforeach
	                                    </div>
	                                @else
	                                    <div class="course-deadlines__empty">
	                                        {{ $isLearner ? 'Нет срочных задач по этой главе.' : 'В этой главе дедлайны не настроены.' }}
	                                    </div>
	                                @endif
	                            </div>
	                        @endif

	                        <div class="course-leaderboard-header">
	                            <div>
                                <p class="gc-eyebrow">Лидерборд</p>
                                <h5 class="mb-0">Прогресс учеников</h5>
                            </div>
                            <span class="badge rounded-pill bg-body-tertiary">{{ $students->count() }}</span>
                        </div>
                        @php
                            $sortedStudents = $students->sortByDesc(function($student) {
                                return isset($student->points) ? $student->points : 0;
                            })->values();
                            $leaderboardStudents = $sortedStudents;
                            $leaderboardOffset = 0;

                            if (!$isManager && $course->students->contains($user)) {
                                $currentStudentIndex = $sortedStudents->search(function ($student) use ($user) {
                                    return $student->id == $user->id;
                                });

                                if ($currentStudentIndex !== false) {
                                    $leaderboardLimit = 6;
                                    $leaderboardOffset = max(0, $currentStudentIndex - 2);

                                    if ($leaderboardOffset + $leaderboardLimit > $sortedStudents->count()) {
                                        $leaderboardOffset = max(0, $sortedStudents->count() - $leaderboardLimit);
                                    }

                                    $leaderboardStudents = $sortedStudents->slice($leaderboardOffset, $leaderboardLimit)->values();
                                }
                            }
                        @endphp

                        <ul class="course-leaderboard-list mb-2">
                            @foreach($leaderboardStudents as $student)
                                @php
                                    $studentRank = $leaderboardOffset + $loop->iteration;
                                    $studentPoints = isset($student->points) ? $student->points : 0;
                                    $studentPercent = isset($student->percent) ? $student->percent : 0;
                                    $studentProgressWidth = max(0, min(100, (int) round($studentPercent)));
                                @endphp
                                <li>
                                    <a class="course-leaderboard-item @if ($studentRank <= 3) is-top-{{$studentRank}} @endif @if ($student->id == $user->id) is-current-user @endif" href="{{url('/insider/profile/'.$student->id)}}">
                                        <span class="course-student-rank">{{$studentRank}}</span>
                                        <x-gc-avatar :user="$student" size="sm" alt="" />
                                        <span class="course-leaderboard-person min-width-0">
                                            <strong class="text-truncate">{{$student->name}}</strong>
                                            <span class="course-leaderboard-meta-row">
                                                @include('profile.partials.custom_title_badge', ['profileUser' => $student, 'compact' => true])
                                                <small class="text-muted text-truncate">{{$studentPoints}} XP</small>
                                            </span>
                                        </span>
                                        <span class="course-student-progress" title="Прогресс: {{ round($studentPercent) }}%">
                                            <span class="course-student-progress__bar" data-progress-width="{{ $studentProgressWidth }}%"></span>
                                            <span class="course-student-progress__value">{{ round($studentPercent) }}%</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>

                        @if ($isManager && count($students) < 40)
                            <hr>
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-3 fw-semibold mb-2 d-inline-flex align-items-center gap-2" data-bs-toggle="collapse" data-bs-target="#course-histogram" aria-expanded="false" aria-controls="course-histogram">
                                <i class="fas fa-chart-bar"></i>
                                Распределение
                            </button>
                            <div class="collapse course-histogram-plot" id="course-histogram">
                                <div id="histogram" data-plotly-histogram='@json($students->pluck('percent')->values())'></div>
                            </div>

                            <div class="course-actions-row mt-2">
                                <a href="{{url('insider/courses/'.$course->id.'/assessments')}}" class="btn btn-outline-secondary btn-sm rounded-3">Очки опыта</a>
                                <a href="{{url('insider/courses/'.$course->id.'/report')}}" class="btn btn-outline-secondary btn-sm rounded-3">Отчет</a>
                                <a href="{{url('insider/courses/'.$course->id.'/blocked')}}" class="btn btn-outline-secondary btn-sm rounded-3">Заблокированные</a>
                            </div>
                        @endif
                    </div>
                </section>

                @if ($isLearner)
                    <section class="gc-card mb-3 course-support-card">
                        <div class="p-3">
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
                                Оценки
                                <span class="badge rounded-pill bg-body-tertiary">{{$points}} / {{$max_points}}</span>
                            </h4>

                            <div class="progress mb-2">
                                @if ($percent < 40)
                                    <div class="progress-bar progress-bar-striped bg-danger progress-width-{{$gradeProgressWidth}}" role="progressbar" data-progress-width="{{$percent}}%" aria-valuenow="{{$gradeProgressWidth}}" aria-valuemin="0" aria-valuemax="100"></div>
                                @elseif($percent < 60)
                                    <div class="progress-bar progress-bar-striped bg-warning progress-width-{{$gradeProgressWidth}}" role="progressbar" data-progress-width="{{$percent}}%" aria-valuenow="{{$gradeProgressWidth}}" aria-valuemin="0" aria-valuemax="100"></div>
                                @else
                                    <div class="progress-bar progress-bar-striped bg-success progress-width-{{$gradeProgressWidth}}" role="progressbar" data-progress-width="{{$percent}}%" aria-valuenow="{{$gradeProgressWidth}}" aria-valuemin="0" aria-valuemax="100"></div>
                                @endif
                            </div>

                            <table class="table table-sm mb-0 gc-data-table course-grade-table">
                                @foreach($steps as $step)
                                    @php
                                        $tasks = $step->tasks;
                                    @endphp
                                    @foreach($tasks as $task)
                                        @php
                                            if (!$task->isVisible($user, $course)) continue;
                                            if ($task->answer != null) continue;
                                            $filtered = $task->solutions->filter(function ($value) use ($user) {
                                                return $value->user_id == $user->id && !$value->is_quiz;
                                            });
                                            $bestSolution = \App\Solution::bestScoredIn($filtered);
                                            $mark = $bestSolution ? $bestSolution->mark : 0;
                                            $markClass = $bestSolution ? $bestSolution->scoreBadgeClass('bg-body-tertiary') : 'bg-body-tertiary';
                                            $should_check = $filtered->filter(fn ($solution) => $solution->submitted && $solution->mark === null && !$solution->review_skipped)->isNotEmpty();
                                        @endphp

                                        <tr>
                                            <td>
                                                @if ($task->step->lesson->isAvailable($course))
                                                    <a href="{{url('/insider/courses/'.$course->id.'/steps/'.$task->step_id.'#task'.$task->id)}}">{{$task->name}} @if ($task->is_star)(*)@endif</a>
                                                @else
                                                    <span class="text-muted">{{$task->name}} @if ($task->is_star)(*)@endif</span>
                                                @endif

                                                @if (!$task->isDone($cstudent->id) && $task->getDeadline($course->id))
                                                    @php
                                                        $deadline = \Carbon\Carbon::parse($task->getDeadline($course->id)->expiration);
                                                    @endphp
                                                    @if ($deadline->addDay()->lt(\Carbon\Carbon::now()))
                                                        <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle fw-semibold">Просрочено {{$deadline->format('Y.m.d')}}</span>
                                                    @elseif (\Carbon\Carbon::now()->addDays(1)->gt($deadline))
                                                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold">Срок {{$deadline->format('Y.m.d')}}</span>
                                                    @elseif (\Carbon\Carbon::now()->addDays(1)->lt($deadline))
                                                        <span class="badge rounded-pill bg-body-tertiary">Срок {{$deadline->format('Y.m.d')}}</span>
                                                    @endif
                                                @endif
                                            </td>
                                            @if ($should_check)
                                                <td><span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold">{{$mark}} / {{$task->max_mark}}</span></td>
                                            @elseif ($mark == 0)
                                                <td><span class="badge rounded-pill bg-body-tertiary">{{$mark}} / {{$task->max_mark}}</span></td>
                                            @elseif ($mark == $task->max_mark)
                                                <td><span class="badge rounded-pill {{ $markClass }}">{{$mark}} / {{$task->max_mark}}</span></td>
                                            @else
                                                <td><span class="badge rounded-pill {{ $markClass }}">{{$mark}} / {{$task->max_mark}}</span></td>
                                            @endif
                                        </tr>
                                    @endforeach
                                @endforeach
                            </table>
                        </div>
                    </section>
                @endif
            </aside>
        </div>
    </div>
@endsection
