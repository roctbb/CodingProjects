@extends('layouts.left-menu')

@section('title')
    {{$course->name}}
@endsection

@section('tabs')

@endsection



@section('content')
    <div class="neo-course-page">
    <div class="row course-details-header course-details-header-panel">
        <div class="col">
            <div class="course-title-row">
                <h2 class="course-details-title">{{$course->name}}</h2>
                @if ($course->teachers->contains($user) || $user->role=='admin')
                    <div class="course-actions">

                                <div class="dropdown">
                                <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="dropdown">
                                    <i class="fa-solid fa-plus"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a href="{{url('/insider/courses/'.$course->id.'/create?chapter='.$chapter->id)}}"
                                       class="dropdown-item"><i
                                                class="icon fa-solid fa-pen"></i> Добавить урок</a>
                                    <a href="{{url('/insider/courses/'.$course->id.'/chapter')}}" class="dropdown-item"><i
                                                class="icon fa-solid fa-plus"></i> Добавить главу</a>
                                    <a href="{{url('/insider/courses/'.$course->id.'/edit')}}"
                                       class="dropdown-item"><i
                                                class="icon fa-solid fa-pen-to-square"></i> Изменить курс</a>
                                    <a href="{{url('/insider/courses/'.$course->id.'/export-md')}}"
                                       class="dropdown-item"><i
                                                class="icon fa-solid fa-file-lines"></i> Экспорт в MD</a>
                                    @if ($course->state=="draft")
                                        <a href="#"
                                           data-action-url="{{url('/insider/courses/'.$course->id.'/start')}}"
                                           data-action-method="POST"
                                           class="dropdown-item"><i
                                                    class="icon fa-solid fa-power-off"></i> Запустить курс</a>
                                    @elseif ($course->state=="started")
                                        <a href="#"
                                           data-action-url="{{url('/insider/courses/'.$course->id.'/stop')}}"
                                           data-action-method="POST"
                                           class="dropdown-item"><i
                                                    class="icon fa-solid fa-stop"></i> Завершить курс</a>
                                    @endif
                                </div>
                            </div>


                    </div>
                @endif
            </div>

            <p class="course-details-description">{{$course->description}}</p>

            <div class="course-header-pills">
                <span class="cp-pill cp-pill--light"><i class="icon fa-solid fa-book-open"></i> Уроков: {{ $course->program->lessons->count() }}</span>
                <span class="cp-pill cp-pill--light"><i class="icon fa-solid fa-list"></i> Глав: {{ $course->program->chapters->count() }}</span>
                <span class="cp-pill cp-pill--light"><i class="icon fa-solid fa-users"></i> Студентов: {{ $course->students->count() }}</span>
                <span class="cp-pill cp-pill--light"><i class="icon fa-solid fa-flag"></i> Статус: {{ $course->state }}</span>
            </div>

            <div class="course-header-bottom">
                <ul class="avatars course-students-avatars">
                    @foreach($course->students as $student)
                        <li>
                            <a href="{{ url('insider/profile/'.$student->id) }}" data-bs-toggle="tooltip"
                               title="{{ $student->name }}">
                                @if ($student->image!=null)
                                    <img alt="Image" src="{{url('/media/'.$student->image)}}"
                                         onerror="if(!this.dataset.fallback){this.dataset.fallback='1';this.src='{{ url('images/user.jpg') }}';}"
                                         class="avatar"/>
                                @else
                                    <img alt="Image" src="{{ url('images/user.jpg') }}" class="avatar"/>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                @if (($course->teachers->contains($user) || $user->role=='admin') && count($students) < 40)
                    <div class="course-header-links">
                        <a href="{{url('insider/courses/'.$course->id.'/assessments')}}" class="cp-header-link">
                            <i class="icon fa-solid fa-chart-column"></i> Очки опыта
                        </a>
                        <a href="{{url('insider/courses/'.$course->id.'/report')}}" class="cp-header-link">
                            <i class="icon fa-solid fa-clipboard"></i> Отчет
                        </a>
                        <a href="{{url('insider/courses/'.$course->id.'/blocked')}}" class="cp-header-link cp-header-link--warn">
                            <i class="icon fa-solid fa-lock"></i> Заблокированные
                        </a>
                    </div>
                @endif
            </div>


        </div>

    </div>
    <div class="row course-details-page g-3">
        <div class="col-12 col-xl-8 course-main-column">

            @if ($course->state=="ended" and ($course->teachers->contains($user) || $user->role=='admin'))
                <div class="course-meta-card-wrap">
                    <div class="card course-meta-card">
                        <div class="card-body">
                            <div class="row align-items-center g-2">
                                <div class="col-auto">
                                    <a data-bs-toggle="collapse" href="#certs" role="button" aria-expanded="false"
                                       aria-controls="certs"><strong>Сертификаты</strong></a>
                                </div>
                                <div class="col-auto">
                                    <a href="#"
                                       data-action-url="{{url('insider/courses/'.$course->id.'/stop')}}"
                                       data-action-method="POST"
                                       class="btn btn-primary btn-sm course-reissue-btn">Перевыпуск</a>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <div class="collapse course-certs-list" id="certs">
                                        <ul>
                                            @foreach($marks as $mark)
                                                @if ($mark->cert_link!= null)
                                                    <li><a target="_blank"
                                                           href="{{$mark->cert_link}}">{{$mark->user->name}} <span
                                                                    class="badge rounded-pill text-bg-success course-cert-mark-badge">{{$mark->mark}}</span></a>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            @endif
            <div class="course-section-head">
                <h3 class="course-section-head__title">
                    <i class="icon fa-solid fa-book-open"></i> Уроки
                </h3>
                <span class="cp-pill cp-pill--light">Доступно: {{ $lessons->count() }}</span>
            </div>
            <div class="content-list course-lessons-list">
                @foreach($lessons as $key => $lesson)
                    @if ($lesson->steps->count()!=0)
                        <div class="course-lesson-group">
                            <div class="card course-lesson-card">
                                <div class="card-body">
                                    <div class="row align-items-start g-2 course-lesson-head">
                                        <div class="col">
                                            @if ($lesson->isAvailable($course) or $course->teachers->contains($user) or $user->role=='admin')
                                                <h5 data-filter-by="text" class="course-lesson-title">{{$key+1}}. <a class="course-lesson-link"
                                                                                         href="{{url('/insider/courses/'.$course->id.'/steps/'.$lesson->steps->first()->id)}}">{{$lesson->name}}</a>
                                                </h5>
                                            @else
                                                <h5 data-filter-by="text" class="course-lesson-title">{{$key+1}}.
                                                    <span class="course-lesson-link text-muted">{{$lesson->name}}</span>
                                                </h5>
                                            @endif
                                        </div>
                                        @if ($course->teachers->contains($user) || $user->role=='admin')
                                            <div class="dropdown">
                                                <button class="btn-options" type="button" data-bs-toggle="dropdown"
                                                        aria-haspopup="true" aria-expanded="false">
                                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                                </button>

                                                <div class="dropdown-menu dropdown-menu-end">

                                                    <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/edit')}}"
                                                       class="dropdown-item"><i
                                                                class="icon fa-solid fa-pen-to-square"></i> Изменить</a>
                                                    <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/export')}}"
                                                       class="dropdown-item"><i
                                                                class="icon fa-solid fa-cloud-arrow-down"></i> Экспорт</a>
                                                    <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/export-md')}}"
                                                       class="dropdown-item"><i
                                                                class="icon fa-solid fa-file-lines"></i> Экспорт в MD</a>
                                                    <a href="#"
                                                       data-action-url="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/lower?chapter='.$chapter->id)}}"
                                                       data-action-method="POST"
                                                       class="dropdown-item"><i
                                                                class="icon fa-solid fa-arrow-up"></i> Выше</a>
                                                    <a href="#"
                                                       data-action-url="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/upper?chapter='.$chapter->id)}}"
                                                       data-action-method="POST"
                                                       class="dropdown-item"><i
                                                                class="icon fa-solid fa-arrow-down"></i> Ниже</a>
                                                </div>
                                            </div>

                                        @endif
                                    </div>

                                    <div class="row">
                                        <div class="col course-lesson-description" data-filter-by="text">
                                            @parsedown($lesson->description)
                                        </div>
                                        @if (!($user->role=='admin' || $course->teachers->contains($user)) and $lesson->percent($cstudent, $course) > 90)
                                            <div class="col-sm-auto">
                                                <img src="{{url($lesson->sticker)}}" class="course-lesson-sticker"/>
                                            </div>
                                        @endif


                                    </div>
                                </div>
                                @if ($lesson->getStartDate($course)!=null)
                                    <div class="card-footer course-lesson-footer">
                                        @if (($course->teachers->contains($user) || $user->role=='admin') && count($students) < 70)
                                            <div class="collapse course-lesson-stats" id="marks{{$lesson->id}}">
                                                @foreach($students as $student)
                                                    @php
                                                        $stats = $lessonStats[$lesson->id][$student->id] ?? null;
                                                        $percent = $stats ? $stats->percent : 0;
                                                        $points = $stats ? $stats->points : 0;
                                                        $maxPoints = $stats ? $stats->max_points : 0;
                                                    @endphp
                                                    <div class="row align-items-center g-2">
                                                        <div class="col">
                                                            {{$student->name}}
                                                        </div>
                                                        <div class="col">
                                                            <div class="progress course-inline-progress">
                                                                @if ($percent < 40)
                                                                    <div class="progress-bar progress-bar-striped bg-danger"
                                                                         role="progressbar"
                                                                         style="width: {{$percent}}%"
                                                                         aria-valuenow="{{$percent}}"
                                                                         aria-valuemin="0"
                                                                         aria-valuemax="100">
                                                                        Очки опыта: {{$points}} / {{$maxPoints}}</div>

                                                                @elseif($percent < 60)
                                                                    <div class="progress-bar progress-bar-striped bg-warning"
                                                                         role="progressbar"
                                                                         style="width: {{$percent}}%"
                                                                         aria-valuenow="{{$percent}}"
                                                                         aria-valuemin="0"
                                                                         aria-valuemax="100">
                                                                        Очки опыта: {{$points}} / {{$maxPoints}}</div>

                                                                @else
                                                                    <div class="progress-bar progress-bar-striped bg-success"
                                                                         role="progressbar"
                                                                         style="width: {{$percent}}%"
                                                                         aria-valuenow="{{$percent}}"
                                                                         aria-valuemin="0"
                                                                         aria-valuemax="100">
                                                                        Очки опыта: {{$points}} / {{$maxPoints}}</div>

                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="row">
                                            <div class="col">
                                                <small class="text-muted"><i class="fa-regular fa-clock"></i> Доступно
                                                    с {{$lesson->getStartDate($course)->format('Y-m-d')}}</small>
                                            </div>
                                            <div class="col course-lesson-progress-col">
                                                @if (($user->role=='student' || ($user->role=='teacher' && !$course->teachers->contains($user))) and $lesson->isAvailable($course))
                                                    @php
                                                        $cstats = $lessonStats[$lesson->id][$cstudent->id] ?? null;
                                                        $cpercent = $cstats ? $cstats->percent : 0;
                                                        $cpoints = $cstats ? $cstats->points : 0;
                                                        $cmaxPoints = $cstats ? $cstats->max_points : 0;
                                                    @endphp
                                                    @if ($cmaxPoints != 0)
                                                    <div class="progress course-inline-progress">
                                                        @if ($cpercent < 40)
                                                            <div class="progress-bar progress-bar-striped bg-danger"
                                                                 role="progressbar"
                                                                 style="width: {{$cpercent}}%"
                                                                 aria-valuenow="{{$cpercent}}"
                                                                 aria-valuemin="0"
                                                                 aria-valuemax="100">
                                                                Очки опыта: {{$cpoints}} / {{$cmaxPoints}}</div>

                                                        @elseif($cpercent < 60)
                                                            <div class="progress-bar progress-bar-striped bg-warning"
                                                                 role="progressbar"
                                                                 style="width: {{$cpercent}}%"
                                                                 aria-valuenow="{{$cpercent}}"
                                                                 aria-valuemin="0"
                                                                 aria-valuemax="100">
                                                                Очки опыта: {{$cpoints}} / {{$cmaxPoints}}</div>

                                                        @else
                                                            <div class="progress-bar progress-bar-striped bg-success"
                                                                 role="progressbar"
                                                                 style="width: {{$cpercent}}%"
                                                                 aria-valuenow="{{$cpercent}}"
                                                                 aria-valuemin="0"
                                                                 aria-valuemax="100">
                                                                Очки опыта: {{$cpoints}} / {{$cmaxPoints}}</div>

                                                        @endif
                                                    </div>
                                                    @endif
                                                @endif

                                                @if (($course->teachers->contains($user) || $user->role=='admin') && count($students) < 70)
                                                    <small class="text-muted course-stats-toggle">
                                                        @foreach($students as $student)
                                                            @php
                                                                $sstats = $lessonStats[$lesson->id][$student->id] ?? null;
                                                                $spercent = $sstats ? $sstats->percent : 0;
                                                            @endphp
                                                            @if ($spercent < 40)
                                                                <span class="badge text-bg-danger">&nbsp;</span>
                                                            @elseif($spercent < 60)
                                                                <span class="badge text-bg-warning">&nbsp;</span>
                                                            @else
                                                                <span class="badge text-bg-success">&nbsp;</span>
                                                            @endif
                                                        @endforeach

                                                        <a class="course-stats-link" data-bs-toggle="collapse"
                                                           href="#marks{{$lesson->id}}" aria-expanded="false"
                                                           aria-controls="marks{{$lesson->id}}"><i
                                                                    class="fa-solid fa-chart-column"></i> Статистика
                                                        </a>
                                                    </small>
                                                @endif
                                            </div>
                                        </div>
                                        @if ($lesson->is_open && ($user->role=='admin' || $course->teachers->contains($user)) )
                                            <small class="text-muted course-open-url"><i class="fa-solid fa-users"></i>
                                                Открытый URL: {{ url('/open/steps/'.$lesson->steps->first()->id) }}
                                            </small>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach

            </div>
        </div>
        <div class="col-12 col-xl-4 course-side-column">
            @if ($course->program->chapters->count()>1)
                <div class="course-section-head course-section-head--side">
                    <h3 class="course-section-head__title">
                        <i class="icon fa-solid fa-list"></i> Главы
                    </h3>
                    <span class="cp-pill cp-pill--light">{{ $course->program->chapters->count() }}</span>
                </div>
                <ul class="list-group course-chapters-list">

                    @foreach($course->program->chapters as $current_chapter)
                        @if ($user->role == 'admin' || $course->teachers->contains($user) or $current_chapter->isStarted($course))
                                        <li class="list-group-item @if ($current_chapter->id == $chapter->id)  active @endif">
                                <a class="course-chapter-link"
                                   href="{{url('/insider/courses/'.$course->id.'?chapter='.$current_chapter->id)}}">
                                    <span class="course-chapter-link__name">{{$current_chapter->name}}</span>
                                    <span class="course-chapter-link__meta">
                                        @if (($course->teachers->contains($user) || $user->role=='admin') and $current_chapter->isStarted($course))
                                            <span class="badge text-bg-primary"> {{ round($current_chapter->getStudentsPercent($course)) }} % </span>
                                        @endif
                                        @if ($user->role=='student' || ($user->role=='teacher' && !$course->teachers->contains($user)))
                                            <span class="badge text-bg-primary"> {{ round($current_chapter->getStudentPercent($course, $user)) }} % </span>
                                        @endif
                                    </span>
                                </a>

                                @if ($course->teachers->contains($user) || $user->role=='admin')
                                    <div class="course-chapter-actions">
                                        <div class="dropdown">
                                                <button class="btn-options" type="button" data-bs-toggle="dropdown"
                                                    aria-haspopup="true" aria-expanded="false">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>

                                            <div class="dropdown-menu dropdown-menu-end">

                                                <a href="{{url('insider/courses/'.$course->id.'/chapters/'.$current_chapter->id.'/edit')}}"
                                                   class="dropdown-item"><i
                                                            class="icon fa-solid fa-pen-to-square"></i> Изменить</a>
                                                <a href="#"
                                                   data-action-url="{{url('insider/courses/'.$course->id.'/chapters/'.$current_chapter->id.'/lower')}}"
                                                   data-action-method="POST"
                                                   class="dropdown-item"><i
                                                            class="icon fa-solid fa-arrow-up"></i> Выше</a>
                                                <a href="#"
                                                   data-action-url="{{url('insider/courses/'.$course->id.'/chapters/'.$current_chapter->id.'/upper')}}"
                                                   data-action-method="POST"
                                                   class="dropdown-item"><i
                                                            class="icon fa-solid fa-arrow-down"></i> Ниже</a>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="small course-chapter-description">{{$current_chapter->description}}</p>
                                @else
                                    @if ($current_chapter->isDone($course) and !($user->role=='admin' || $course->teachers->contains($user)))
                                        <span class="course-chapter-state">
                                        <i class="icon fa-solid fa-circle-check course-done-icon"></i> <span
                                                    class="course-done-text">выполнено</span>
                                        </span>
                                    @endif
                                @endif
                            </li>
                        @endif
                    @endforeach

                </ul>
            @endif


            <div class="card course-info-card">
                <div class="card-body">
                    <h4 class="card-title">Информация <img class="course-info-icon"
                                                           src="{{ url('images/icons/icons8-info-48.png') }}"></h4>
                    <p>
                        @if ($course->teachers->contains($user) || $user->role=='admin')
                            <b>Статус:</b> {{$course->state}}<br/>
                            <b>Инвайт:</b> {{$course->invite}}<br/>
                            <b>Средняя оценка:</b> {{ round($course->average_mark(), 2) }} ({{$course->marks_count()}})
                            <br/>
                            <b>Последние оценки:</b> {{ round($course->recent_mark(), 2) }}
                            ({{$course->recent_marks_count()}})<br/>

                        @endif
                        @if ($course->git!=null)
                            <b><img src="{{ url('images/icons/icons8-git-48.png') }}" title="Git" width="16"
                                    height="16"> Git
                                репозиторий:</b> <a class="course-info-link" href="{{$course->git}}">{{$course->git}}</a><br/>
                        @endif
                        @if ($course->telegram!=null)
                            <b><img src="{{ url('images/icons/icons8-telegram-app-48.png') }}" title="Telegram App"
                                    width="16"
                                    height="16"> Чат в телеграм:</b> <a
                                    class="course-info-link" href="{{$course->telegram}}">{{$course->telegram}}</a><br/>
                        @endif
                    </p>
                    <p>
                        <b>Преподаватели:</b>
                    </p>
                    <ul class="course-people-list">
                        @foreach($course->teachers as $teacher)
                            <li><a class="black-link"
                                   href="{{url('/insider/profile/'.$teacher->id)}}">{{$teacher->name}}</a></li>
                        @endforeach
                    </ul>
                    <p>
                        <b>Студенты:</b>
                    </p>
                    @if (count($students) < 70)
                        <ul class="course-people-list">
                            @php
                                // Sort students by points (descending)
                                $sortedStudents = $students->sortByDesc(function($student) {
                                    return isset($student->points) ? $student->points : 0;
                                });
                            @endphp
                            @foreach($sortedStudents as $student)
                                @php
                                    $studentPoints = isset($student->points) ? $student->points : 0;
                                    $studentPercent = isset($student->percent) ? $student->percent : 0;
                                @endphp
                                <li><a class="black-link"
                                       href="{{url('/insider/profile/'.$student->id)}}">{{$student->name}}</a> <span
                                            class="badge text-bg-primary course-student-progress-badge" title="Очки опыта: {{$studentPoints}}"> {{ round($studentPercent) }}
                                    % </span></li>
                            @endforeach
                        </ul>
                    @else
                        <ul class="course-people-list">
                            @foreach($students as $student)
                                <li><a class="black-link"
                                       href="{{url('/insider/profile/'.$student->id)}}">{{$student->name}}</a></li>
                            @endforeach
                        </ul>
                    @endif

                    @if (($course->teachers->contains($user) || $user->role=='admin') && count($students) < 40)

                        <div id="histogram" class="course-histogram"></div>

                        <script>
                                    @php
                                        $points = [];
                                        foreach ($students as $student)
                                        {
                                            array_push($points, $student->percent);
                                        }
                                    @endphp
                            var x = [{{implode(',',$points)}}];

                            var trace = {
                                x: x,
                                type: 'histogram',
                                autobinx: false,
                                marker: {
                                    color: "rgba(100, 200, 102, 0.7)",
                                    line: {
                                        color: "rgba(100, 200, 102, 1)",
                                        width: 1
                                    }
                                },
                                opacity: 0.75,
                                xbins: {
                                    end: 110,
                                    size: 15,
                                    start: 0

                                }
                            };

                            var data = [trace];
                            Plotly.newPlot('histogram', data);
                        </script>

                        <p class="course-admin-actions">
                            <a href="{{url('insider/courses/'.$course->id.'/assessments')}}"
                               class="btn btn-primary btn-sm">Очки опыта</a>
                            <a href="{{url('insider/courses/'.$course->id.'/report')}}"
                               class="btn btn-primary btn-sm">Отчет</a>
                            <a href="{{url('insider/courses/'.$course->id.'/blocked')}}"
                               class="btn btn-warning btn-sm">Заблокированные</a>
                        </p>
                    @endif

                </div>
            </div>
            @if ($user->role=='student' || ($user->role=='teacher' && !$course->teachers->contains($user)))
                <div class="card course-grades-card">
                    <div class="card-body">
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
                                    $mark = null;
                                    $mark = $filtered->max('mark');

                                    $mark = $mark == null?0:$mark;
                                    $should_check = false;
                                    if (count($filtered)!=0 && $filtered->last()->mark==null) $should_check=true;

                                    if (!$task->is_star) $max_points += $task->max_mark;
                                    $points += $mark;
                                }
                            }
                            if ($max_points != 0) {
                                $percent = min(100, $points * 100 / $max_points);
                            } else {
                                $percent = 0;
                            }
                        @endphp
                        <h4 class="card-title">Оценки <img src="{{ url('images/icons/icons8-medal-48.png') }}">
                            <small class="course-grade-summary"><span class="badge text-bg-primary">{{$points}}
                                    / {{$max_points}}</span></small>
                        </h4>
                        <div class="progress course-main-progress">
                            @if ($percent < 40)
                                <div class="progress-bar progress-bar-striped bg-danger" role="progressbar"
                                     style="width: {{$percent}}%"
                                     aria-valuenow="{{$percent}}" aria-valuemin="0"
                                     aria-valuemax="100"></div>

                            @elseif($percent < 60)
                                <div class="progress-bar progress-bar-striped bg-warning" role="progressbar"
                                     style="width: {{$percent}}%"
                                     aria-valuenow="{{$percent}}" aria-valuemin="0"
                                     aria-valuemax="100"></div>

                            @else
                                <div class="progress-bar progress-bar-striped bg-success" role="progressbar"
                                     style="width: {{$percent}}%"
                                     aria-valuenow="{{$percent}}" aria-valuemin="0"
                                     aria-valuemax="100"></div>

                            @endif
                        </div>
                        <div class="table-responsive course-grades-table-wrap">
                        <table class="table table-sm align-middle course-grades-table mb-0">
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
                                            $mark = null;
                                            $mark = $filtered->max('mark');

                                            $mark = $mark == null?0:$mark;
                                            $should_check = false;
                                            if (count($filtered)!=0 && $filtered->last()->mark==null) $should_check=true;
                                    @endphp
                                    <tr>
                                        <td class="course-grades-task-col">
                                            @if ($task->step->lesson->isAvailable($course))
                                                <a href="{{url('/insider/courses/'.$course->id.'/steps/'.$task->step_id.'#task'.$task->id)}}">{{$task->name}} @if ($task->is_star)
                                                        (*)@endif</a>
                                            @else
                                                <span class="text-muted">{{$task->name}} @if ($task->is_star)
                                                        (*)@endif</span>
                                            @endif

                                            @if (!$task->isDone($cstudent->id) and $task->getDeadline($course->id))
                                                &nbsp;
                                                @php
                                                    $deadline = \Carbon\Carbon::parse($task->getDeadline($course->id)->expiration);
                                                @endphp
                                                @if ($deadline->addDay()->lt(\Carbon\Carbon::now()))
                                                    <span class="badge text-bg-danger">
                                                            Просрочено {{$deadline->format('Y.m.d')}}
                                                        </span>
                                                @elseif (\Carbon\Carbon::now()->addDays(1)->gt($deadline))
                                                    <span class="badge text-bg-warning">
                                                            Срок {{$deadline->format('Y.m.d')}}
                                                        </span>
                                                @elseif (\Carbon\Carbon::now()->addDays(1)->lt($deadline))
                                                    <span class="badge text-bg-light">
                                                            Срок {{$deadline->format('Y.m.d')}}
                                                        </span>
                                                @endif
                                            @endif
                                        </td>
                                        @if ($should_check)
                                            <td class="course-grades-mark-col"><span class="badge text-bg-warning">{{$mark}} / {{$task->max_mark}}</span></td>
                                        @elseif ($mark == 0)
                                            <td class="course-grades-mark-col"><span class="badge text-bg-light">{{$mark}} / {{$task->max_mark}}</span></td>
                                        @elseif ($mark == $task->max_mark)
                                            <td class="course-grades-mark-col"><span class="badge text-bg-success">{{$mark}} / {{$task->max_mark}}</span></td>
                                        @else
                                            <td class="course-grades-mark-col"><span class="badge text-bg-primary">{{$mark}} / {{$task->max_mark}}</span></td>
                                        @endif

                                    </tr>
                                @endforeach
                            @endforeach
                        </table>
                        </div>
                    </div>
                </div>
            @endif

            <img src="{{url('images/ginger-cat.png')}}" class="course-cat-image"/>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.CPUI) {
                        window.CPUI.initPopovers('[data-bs-toggle="popover"]');
                    window.CPUI.initPopovers('.popover-dismiss', {trigger: 'focus'});
                }
            });
        </script>

    </div>
    </div>



@endsection
