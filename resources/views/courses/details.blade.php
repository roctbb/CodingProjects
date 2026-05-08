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
    @endphp

    <div class="course-page">
        <div class="courses-page-heading course-details-heading mb-3">
            <div class="course-details-heading__main min-width-0">
                <h2 class="mb-1">{{$course->name}}</h2>
                <p class="course-description mb-0">{{$course->description}}</p>
                <ul class="avatars course-header-avatars">
                    @foreach($course->students as $student)
                        @if ($loop->iteration > 12)
                            @continue
                        @endif
                        <li>
                            <a href="{{ url('insider/profile/'.$student->id) }}" data-bs-toggle="tooltip" title="{{ $student->name }}">
                                <img alt="Image" src="{{ $student->imageUrl() }}" class="avatar"/>
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
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="dropdown" data-bs-target="#project-add-modal" aria-haspopup="true" aria-expanded="false">
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
                    <section class="card shadow-sm mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <a data-bs-toggle="collapse" href="#certs" role="button" aria-expanded="false" aria-controls="certs"><strong>Сертификаты</strong></a>
                                <a href="{{url('insider/courses/'.$course->id.'/stop')}}" class="btn btn-success btn-sm ms-2">Перевыпуск</a>
                            </div>

                            <div class="collapse mt-3" id="certs">
                                <ul class="mb-0 ps-3">
                                    @foreach($marks as $mark)
                                        @if ($mark->cert_link != null)
                                            <li>
                                                <a target="_blank" href="{{$mark->cert_link}}" class="course-row-between">
                                                    <span>{{$mark->user->name}}</span>
                                                    <span class="badge rounded-pill bg-success">{{$mark->mark}}</span>
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
                                $activeChapterPercent = round($chapter->getStudentsPercent($course));
                            } elseif ($isLearner) {
                                $activeChapterPercent = round($chapter->getStudentPercent($course, $user));
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
                                                    $chapterPercent = round($current_chapter->getStudentsPercent($course));
                                                } elseif ($isLearner) {
                                                    $chapterPercent = round($current_chapter->getStudentPercent($course, $user));
                                                }
                                            @endphp

                                            <a href="{{url('/insider/courses/'.$course->id.'?chapter='.$current_chapter->id)}}" class="dropdown-item course-chapter-switcher__item @if ($current_chapter->id == $chapter->id) active @endif">
                                                <span class="course-chapter-switcher__item-name text-truncate">{{$current_chapter->name}}</span>
                                                @if (!is_null($chapterPercent))
                                                    <span class="course-chapter-switcher__item-percent">{{$chapterPercent}} %</span>
                                                @endif
                                                @if (!$isManager && $current_chapter->isDone($course))
                                                    <i class="icon ion-checkmark-circled course-chapter-switcher__done"></i>
                                                @endif
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>

                            @if ($isManager)
                                <div class="dropdown course-current-chapter-actions">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>

                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a href="{{url('insider/courses/'.$course->id.'/chapters/'.$chapter->id.'/edit')}}" class="dropdown-item"><i class="icon ion-android-create"></i> Изменить главу</a>
                                        <a href="{{url('insider/courses/'.$course->id.'/chapters/'.$chapter->id.'/lower')}}" class="dropdown-item"><i class="icon ion-arrow-up-c"></i> Выше</a>
                                        <a href="{{url('insider/courses/'.$course->id.'/chapters/'.$chapter->id.'/upper')}}" class="dropdown-item"><i class="icon ion-arrow-down-c"></i> Ниже</a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="content-list">
                    @foreach($lessons as $key => $lesson)
                        @if ($lesson->steps->count() == 0)
                            @continue
                        @endif

                        <article class="card course-lesson-card shadow-sm mb-3">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div class="pe-2">
                                        @if ($lesson->isAvailable($course) || $isManager)
                                            <h5 class="course-lesson-title mb-0" data-filter-by="text">
                                                <span class="course-lesson-number">{{$key + 1}}.</span>
                                                <a href="{{url('/insider/courses/'.$course->id.'/steps/'.$lesson->steps->first()->id)}}">{{$lesson->name}}</a>
                                            </h5>
                                        @else
                                            <h5 class="course-lesson-title mb-0" data-filter-by="text">
                                                <span class="course-lesson-number">{{$key + 1}}.</span>
                                                <span class="text-muted">{{$lesson->name}}</span>
                                            </h5>
                                        @endif
                                    </div>

                                    @if ($isManager)
                                        <div class="course-lesson-actions">
                                            <div class="dropdown">
                                                <button class="btn-options" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>

                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/edit')}}" class="dropdown-item"><i class="icon ion-android-create"></i> Изменить</a>
                                                    <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/export')}}" class="dropdown-item"><i class="icon ion-ios-cloud-download"></i> Экспорт</a>
                                                    <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/export-md')}}" class="dropdown-item"><i class="icon ion-document-text"></i> Экспорт в MD</a>
                                                    <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/lower?chapter='.$chapter->id)}}" class="dropdown-item"><i class="icon ion-arrow-up-c"></i> Выше</a>
                                                    <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/upper?chapter='.$chapter->id)}}" class="dropdown-item"><i class="icon ion-arrow-down-c"></i> Ниже</a>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="row">
                                    <div class="col course-lesson-description" data-filter-by="text">
                                        @parsedown($lesson->description)
                                    </div>

                                    @if (!($user->role == 'admin' || $course->teachers->contains($user)) && $lesson->percent($cstudent, $course) > 90)
                                        <div class="col-sm-auto">
                                            <img src="{{url($lesson->sticker)}}" width="200" alt=""/>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if ($lesson->getStartDate($course) != null)
                                <div class="card-footer course-card-footer py-2 px-3">
                                    @if ($isManager && count($students) < 70)
                                        <div class="course-lesson-stats d-none mb-2" id="marks{{$lesson->id}}" data-course-stats-panel>
                                            @foreach($students as $student)
                                                @php
                                                    $stats = $lessonStats[$lesson->id][$student->id] ?? null;
                                                    $percent = $stats ? $stats->percent : 0;
                                                    $progressWidth = max(0, min(100, (int) round($percent)));
                                                    $points = $stats ? $stats->points : 0;
                                                    $maxPoints = $stats ? $stats->max_points : 0;
                                                @endphp

                                                <div class="course-stat-row small">
                                                    <div class="course-stat-name" title="{{$student->name}}">{{$student->name}}</div>
                                                    <div class="progress">
                                                        @if ($maxPoints == 0)
                                                            <div class="progress-bar bg-light text-muted progress-width-0" role="progressbar" data-progress-width="0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0 / 0</div>
                                                        @elseif ($percent < 40)
                                                            <div class="progress-bar progress-bar-striped bg-danger progress-width-{{$progressWidth}}" role="progressbar" data-progress-width="{{$percent}}%" aria-valuenow="{{$progressWidth}}" aria-valuemin="0" aria-valuemax="100">{{$points}} / {{$maxPoints}}</div>
                                                        @elseif($percent < 60)
                                                            <div class="progress-bar progress-bar-striped bg-warning progress-width-{{$progressWidth}}" role="progressbar" data-progress-width="{{$percent}}%" aria-valuenow="{{$progressWidth}}" aria-valuemin="0" aria-valuemax="100">{{$points}} / {{$maxPoints}}</div>
                                                        @else
                                                            <div class="progress-bar progress-bar-striped bg-success progress-width-{{$progressWidth}}" role="progressbar" data-progress-width="{{$percent}}%" aria-valuenow="{{$progressWidth}}" aria-valuemin="0" aria-valuemax="100">{{$points}} / {{$maxPoints}}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="course-lesson-footer-main">
                                        <small class="text-muted me-3"><i class="ion ion-clock"></i> Доступно с {{$lesson->getStartDate($course)->format('Y-m-d')}}</small>

                                        <div class="course-lesson-footer-actions">
                                            @if ($isLearner && $lesson->isAvailable($course))
                                                @php
                                                    $cstats = $lessonStats[$lesson->id][$cstudent->id] ?? null;
                                                    $cpercent = $cstats ? $cstats->percent : 0;
                                                    $cprogressWidth = max(0, min(100, (int) round($cpercent)));
                                                    $cpoints = $cstats ? $cstats->points : 0;
                                                    $cmaxPoints = $cstats ? $cstats->max_points : 0;
                                                @endphp

                                                @if ($cmaxPoints != 0)
                                                    <div class="progress my-1">
                                                        @if ($cpercent < 40)
                                                            <div class="progress-bar progress-bar-striped bg-danger progress-width-{{$cprogressWidth}}" role="progressbar" data-progress-width="{{$cpercent}}%" aria-valuenow="{{$cprogressWidth}}" aria-valuemin="0" aria-valuemax="100">
                                                                Очки опыта: {{$cpoints}} / {{$cmaxPoints}}</div>
                                                        @elseif($cpercent < 60)
                                                            <div class="progress-bar progress-bar-striped bg-warning progress-width-{{$cprogressWidth}}" role="progressbar" data-progress-width="{{$cpercent}}%" aria-valuenow="{{$cprogressWidth}}" aria-valuemin="0" aria-valuemax="100">
                                                                Очки опыта: {{$cpoints}} / {{$cmaxPoints}}</div>
                                                        @else
                                                            <div class="progress-bar progress-bar-striped bg-success progress-width-{{$cprogressWidth}}" role="progressbar" data-progress-width="{{$cpercent}}%" aria-valuenow="{{$cprogressWidth}}" aria-valuemin="0" aria-valuemax="100">
                                                                Очки опыта: {{$cpoints}} / {{$cmaxPoints}}</div>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endif

                                            @if ($isManager && count($students) < 70)
                                                <div class="course-lesson-stat-badges" data-course-stats-summary="#marks{{$lesson->id}}">
                                                    @foreach($students as $student)
                                                        @php
                                                            $stats = $lessonStats[$lesson->id][$student->id] ?? null;
                                                            $percent = $stats ? $stats->percent : 0;
                                                            $badgeClass = $percent < 40 ? 'is-low' : ($percent < 60 ? 'is-mid' : 'is-high');
                                                        @endphp
                                                        <span class="course-lesson-stat-badge {{$badgeClass}}" title="{{$student->name}}: {{round($percent)}}%"></span>
                                                    @endforeach
                                                </div>
                                                <button type="button" class="btn btn-link btn-sm p-0 course-secondary-action course-stats-toggle" data-course-stats-toggle data-course-stats-target="#marks{{$lesson->id}}" aria-expanded="false" aria-controls="marks{{$lesson->id}}"><i class="ion ion-stats-bars"></i> <span data-course-stats-label>Статистика</span></button>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($lesson->is_open && $isManager)
                                        <a class="course-secondary-action d-inline-block mt-1" href="{{ url('/open/steps/'.$lesson->steps->first()->id) }}" target="_blank" rel="noopener"><i class="ion ion-android-contacts"></i> Открытый URL</a>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </main>

            <aside class="col-xl-3 col-lg-4 course-sidebar">
                <section class="card shadow-sm mb-3 course-info-card">
                    <div class="card-body p-3">
                        <div class="course-info-card__header">
                            <span class="courses-birthday-title__icon"><i class="fas fa-circle-info"></i></span>
                            <div>
                                <h4 class="card-title h5 mb-0">Информация</h4>
                                <small class="text-muted">Участники и ссылки</small>
                            </div>
                        </div>

                        <div class="course-info-meta mb-3">
                            @if ($isManager)
                                <div><span>Статус</span><strong>{{$course->state}}</strong></div>
                                <div><span>Инвайт</span><strong>{{$course->invite}}</strong></div>
                            @endif
                            @if ($course->git != null)
                                <div><span>Git</span><a class="text-truncate" href="{{$course->git}}">{{$course->git}}</a></div>
                            @endif
                            @if ($course->telegram != null)
                                <div><span>Telegram</span><a class="text-truncate" href="{{$course->telegram}}">{{$course->telegram}}</a></div>
                            @endif
                        </div>

                        <p class="course-info-label">Преподаватели</p>
                        <ul class="course-info-people mb-3">
                            @foreach($course->teachers as $teacher)
                                <li>
                                    <img src="{{ $teacher->imageUrl() }}" class="avatar sm" alt="">
                                    <a class="text-truncate" href="{{url('/insider/profile/'.$teacher->id)}}">{{$teacher->name}}</a>
                                </li>
                            @endforeach
                        </ul>

                        <p class="course-info-label">Студенты</p>
                        @php
                            $sortedStudents = $students->sortByDesc(function($student) {
                                return isset($student->points) ? $student->points : 0;
                            });
                        @endphp

                        <ul class="course-info-students mb-2">
                            @foreach($sortedStudents as $student)
                                @php
                                    $studentPoints = isset($student->points) ? $student->points : 0;
                                    $studentPercent = isset($student->percent) ? $student->percent : 0;
                                @endphp
                                <li>
                                    <span class="course-row-between">
                                        <a class="text-dark" href="{{url('/insider/profile/'.$student->id)}}">{{$student->name}}</a>
                                        <span class="badge bg-primary" title="Очки опыта: {{$studentPoints}}">{{ round($studentPercent) }} %</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        @if ($isManager && count($students) < 40)
                            <hr>
                            <button type="button" class="btn btn-outline-secondary btn-sm mb-2" data-bs-toggle="collapse" data-bs-target="#course-histogram" aria-expanded="false" aria-controls="course-histogram">Распределение</button>
                            <div class="collapse course-histogram-plot" id="course-histogram">
                                <div id="histogram" data-plotly-histogram='@json($students->pluck('percent')->values())'></div>
                            </div>

                            <div class="course-actions-row mt-2">
                                <a href="{{url('insider/courses/'.$course->id.'/assessments')}}" class="btn btn-outline-success btn-sm">Очки опыта</a>
                                <a href="{{url('insider/courses/'.$course->id.'/report')}}" class="btn btn-outline-success btn-sm">Отчет</a>
                                <a href="{{url('insider/courses/'.$course->id.'/blocked')}}" class="btn btn-outline-warning btn-sm">Заблокированные</a>
                            </div>
                        @endif
                    </div>
                </section>

                @if ($isLearner)
                    <section class="card shadow-sm mb-3">
                        <div class="card-body p-3">
                            @php
                                $max_points = 0;
                                $points = 0;
                                foreach ($steps as $step) {
                                    $tasks = $step->tasks;
                                    foreach ($tasks as $task) {
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

                            <h4 class="card-title h5 d-flex justify-content-between align-items-center">
                                Оценки
                                <span class="badge bg-primary">{{$points}} / {{$max_points}}</span>
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

                            <table class="table table-sm mb-0">
                                @foreach($steps as $step)
                                    @php
                                        $tasks = $step->tasks;
                                    @endphp
                                    @foreach($tasks as $task)
                                        @php
                                            if ($task->answer != null) continue;
                                            $filtered = $task->solutions->filter(function ($value) use ($user) {
                                                return $value->user_id == $user->id && !$value->is_quiz;
                                            });
                                            $mark = $filtered->max('mark');
                                            $mark = $mark == null ? 0 : $mark;
                                            $should_check = false;
                                            if (count($filtered) != 0 && $filtered->last()->mark == null) $should_check = true;
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
                                                        <span class="badge bg-danger">Просрочено {{$deadline->format('Y.m.d')}}</span>
                                                    @elseif (\Carbon\Carbon::now()->addDays(1)->gt($deadline))
                                                        <span class="badge bg-warning text-dark">Срок {{$deadline->format('Y.m.d')}}</span>
                                                    @elseif (\Carbon\Carbon::now()->addDays(1)->lt($deadline))
                                                        <span class="badge bg-light text-dark">Срок {{$deadline->format('Y.m.d')}}</span>
                                                    @endif
                                                @endif
                                            </td>
                                            @if ($should_check)
                                                <td><span class="badge bg-warning text-dark">{{$mark}} / {{$task->max_mark}}</span></td>
                                            @elseif ($mark == 0)
                                                <td><span class="badge bg-light text-dark">{{$mark}} / {{$task->max_mark}}</span></td>
                                            @elseif ($mark == $task->max_mark)
                                                <td><span class="badge bg-success">{{$mark}} / {{$task->max_mark}}</span></td>
                                            @else
                                                <td><span class="badge bg-primary">{{$mark}} / {{$task->max_mark}}</span></td>
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
