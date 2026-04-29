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
    <div class="row">
        <div class="col">
            <h2 class="font-weight-light">{{$course->name}} @if ($course->teachers->contains($user) || $user->role=='admin')
                    <div class="float-right mt-2">

                        <div class="dropdown">
                            <button class="btn btn-round" data-toggle="dropdown" data-target="#project-add-modal">
                                <i class="fas fa-plus"></i>
                            </button>

                            <div class="dropdown-menu dropdown-menu-right">
                                <a href="{{url('/insider/courses/'.$course->id.'/create?chapter='.$chapter->id)}}"
                                   class="dropdown-item"><i
                                            class="icon ion-compose"></i> Добавить урок</a>
                                <a href="{{url('/insider/courses/'.$course->id.'/chapter')}}" class="dropdown-item"><i
                                            class="icon ion-plus"></i> Добавить главу</a>
                                <a href="{{url('/insider/courses/'.$course->id.'/edit')}}"
                                   class="dropdown-item"><i
                                            class="icon ion-android-create"></i> Изменить курс</a>
                                <a href="{{url('/insider/courses/'.$course->id.'/export-md')}}"
                                   class="dropdown-item"><i
                                            class="icon ion-document-text"></i> Экспорт в MD</a>
                                @if ($course->state=="draft")
                                    <a href="{{url('/insider/courses/'.$course->id.'/start')}}"
                                       class="dropdown-item"><i
                                                class="icon ion-power"></i> Запустить курс</a>
                                @elseif ($course->state=="started")
                                    <a href="{{url('/insider/courses/'.$course->id.'/stop')}}"
                                       class="dropdown-item"><i
                                                class="icon ion-stop"></i> Завершить курс</a>
                                @endif
                            </div>
                        </div>


                    </div>@endif</h2>

            <p>{{$course->description}}</p>


            <ul class="avatars">
                @foreach($course->students as $student)
                    <li>
                        <a href="{{ url('insider/profile/'.$student->id) }}" data-toggle="tooltip"
                           title="{{ $student->name }}">
                            <img alt="Image" src="{{ $student->imageUrl() }}" class="avatar"/>
                        </a>
                    </li>
                @endforeach

            </ul>


        </div>

    </div>
    <div class="row">
        <div class="col-md-8">

            @if ($course->state=="ended" and ($course->teachers->contains($user) || $user->role=='admin'))
                <div class="card-group my-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col">
                                    <a data-toggle="collapse" href="#certs" role="button" aria-expanded="false"
                                       aria-controls="certs"><strong>Сертификаты</strong></a>
                                    <a href="{{url('insider/courses/'.$course->id.'/stop')}}"
                                       class="float-right btn btn-success btn-sm">Перевыпуск</a>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <div class="collapse" id="certs">
                                        <p>
                                        <ul>
                                            @foreach($marks as $mark)
                                                @if ($mark->cert_link!= null)
                                                    <li><a target="_blank"
                                                           href="{{$mark->cert_link}}">{{$mark->user->name}} <span
                                                                    class="float-right badge badge-pill badge-success">{{$mark->mark}}</span></a>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                        </p>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            @endif
            <div class="content-list">
                @foreach($lessons as $key => $lesson)
                    @if ($lesson->steps->count()!=0)
                        <div class="card-group my-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col">
                                            @if ($lesson->isAvailable($course) or $course->teachers->contains($user) or $user->role=='admin')
                                                <h5 data-filter-by="text">{{$key+1}}. <a
                                                                                         href="{{url('/insider/courses/'.$course->id.'/steps/'.$lesson->steps->first()->id)}}">{{$lesson->name}}</a>
                                                </h5>
                                            @else
                                                <h5 data-filter-by="text">{{$key+1}}. <span
                                                            class="text-muted">{{$lesson->name}}</span>
                                                </h5>
                                            @endif
                                        </div>
                                        @if ($course->teachers->contains($user) || $user->role=='admin')
                                            <div class="col-auto d-flex align-items-start">
                                                <div class="dropdown">
                                                <button class="btn-options" type="button" data-toggle="dropdown"
                                                        aria-haspopup="true" aria-expanded="false">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>

                                                <div class="dropdown-menu dropdown-menu-right">

                                                    <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/edit')}}"
                                                       class="dropdown-item"><i
                                                                class="icon ion-android-create"></i> Изменить</a>
                                                    <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/export')}}"
                                                       class="dropdown-item"><i
                                                                class="icon ion-ios-cloud-download"></i> Экспорт</a>
                                                    <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/export-md')}}"
                                                       class="dropdown-item"><i
                                                                class="icon ion-document-text"></i> Экспорт в MD</a>
                                                    <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/lower?chapter='.$chapter->id)}}"
                                                       class="dropdown-item"><i
                                                                class="icon ion-arrow-up-c"></i> Выше</a>
                                                    <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/upper?chapter='.$chapter->id)}}"
                                                       class="dropdown-item"><i
                                                                class="icon ion-arrow-down-c"></i> Ниже</a>
                                                </div>
                                                </div>
                                            </div>

                                        @endif
                                    </div>

                                    <div class="row">
                                        <div class="col" data-filter-by="text">
                                            @parsedown($lesson->description)
                                        </div>
                                        @if (!($user->role=='admin' || $course->teachers->contains($user)) and $lesson->percent($cstudent, $course) > 90)
                                            <div class="col-sm-auto">
                                                <img src="{{url($lesson->sticker)}}" width="200" alt=""/>
                                            </div>
                                        @endif


                                    </div>
                                </div>
                                @if ($lesson->getStartDate($course)!=null)
                                    <div class="card-footer">
                                        @if (($course->teachers->contains($user) || $user->role=='admin') && count($students) < 70)
                                            <div class="collapse" id="marks{{$lesson->id}}">
                                                @foreach($students as $student)
                                                    @php
                                                        $stats = $lessonStats[$lesson->id][$student->id] ?? null;
                                                        $percent = $stats ? $stats->percent : 0;
                                                        $points = $stats ? $stats->points : 0;
                                                        $maxPoints = $stats ? $stats->max_points : 0;
                                                    @endphp
                                                    <div class="row">
                                                        <div class="col">
                                                            {{$student->name}}
                                                        </div>
                                                        <div class="col">
                                                            <div class="progress m-1">
                                                                @if ($percent < 40)
                                                                    <div class="progress-bar progress-bar-striped bg-danger"
                                                                         role="progressbar"
                                                                          data-progress-width="{{$percent}}%"
                                                                         aria-valuenow="{{$percent}}"
                                                                         aria-valuemin="0"
                                                                         aria-valuemax="100">
                                                                        Очки опыта: {{$points}} / {{$maxPoints}}</div>

                                                                @elseif($percent < 60)
                                                                    <div class="progress-bar progress-bar-striped bg-warning"
                                                                         role="progressbar"
                                                                          data-progress-width="{{$percent}}%"
                                                                         aria-valuenow="{{$percent}}"
                                                                         aria-valuemin="0"
                                                                         aria-valuemax="100">
                                                                        Очки опыта: {{$points}} / {{$maxPoints}}</div>

                                                                @else
                                                                    <div class="progress-bar progress-bar-striped bg-success"
                                                                         role="progressbar"
                                                                          data-progress-width="{{$percent}}%"
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
                                                <small class="text-muted"><i class="ion ion-clock"></i> Доступно
                                                    с {{$lesson->getStartDate($course)->format('Y-m-d')}}</small>
                                            </div>
                                            <div class="col">
                                                @if (($user->role=='student' || ($user->role=='teacher' && !$course->teachers->contains($user))) and $lesson->isAvailable($course))
                                                    @php
                                                        $cstats = $lessonStats[$lesson->id][$cstudent->id] ?? null;
                                                        $cpercent = $cstats ? $cstats->percent : 0;
                                                        $cpoints = $cstats ? $cstats->points : 0;
                                                        $cmaxPoints = $cstats ? $cstats->max_points : 0;
                                                    @endphp
                                                    @if ($cmaxPoints != 0)
                                                    <div class="progress m-1">
                                                        @if ($cpercent < 40)
                                                            <div class="progress-bar progress-bar-striped bg-danger"
                                                                 role="progressbar"
                                                                  data-progress-width="{{$cpercent}}%"
                                                                 aria-valuenow="{{$cpercent}}"
                                                                 aria-valuemin="0"
                                                                 aria-valuemax="100">
                                                                Очки опыта: {{$cpoints}} / {{$cmaxPoints}}</div>

                                                        @elseif($cpercent < 60)
                                                            <div class="progress-bar progress-bar-striped bg-warning"
                                                                 role="progressbar"
                                                                  data-progress-width="{{$cpercent}}%"
                                                                 aria-valuenow="{{$cpercent}}"
                                                                 aria-valuemin="0"
                                                                 aria-valuemax="100">
                                                                Очки опыта: {{$cpoints}} / {{$cmaxPoints}}</div>

                                                        @else
                                                            <div class="progress-bar progress-bar-striped bg-success"
                                                                 role="progressbar"
                                                                  data-progress-width="{{$cpercent}}%"
                                                                 aria-valuenow="{{$cpercent}}"
                                                                 aria-valuemin="0"
                                                                 aria-valuemax="100">
                                                                Очки опыта: {{$cpoints}} / {{$cmaxPoints}}</div>

                                                        @endif
                                                    </div>
                                                    @endif
                                                @endif

                                                @if (($course->teachers->contains($user) || $user->role=='admin') && count($students) < 70)
                                                    <small class="text-muted float-right mr-3">
                                                        @foreach($students as $student)
                                                            @php
                                                                $sstats = $lessonStats[$lesson->id][$student->id] ?? null;
                                                                $spercent = $sstats ? $sstats->percent : 0;
                                                            @endphp
                                                            @if ($spercent < 40)
                                                                <span class="badge badge-danger">&nbsp;</span>
                                                            @elseif($spercent < 60)
                                                                <span class="badge badge-warning">&nbsp;</span>
                                                            @else
                                                                <span class="badge badge-success">&nbsp;</span>
                                                            @endif
                                                        @endforeach

                                                        <a class="ml-2" data-toggle="collapse"
                                                           href="#marks{{$lesson->id}}" aria-expanded="false"
                                                           aria-controls="marks{{$lesson->id}}"><i
                                                                    class="ion ion-stats-bars"></i> Статистика
                                                        </a>
                                                    </small>
                                                @endif
                                            </div>
                                        </div>
                                        @if ($lesson->is_open && ($user->role=='admin' || $course->teachers->contains($user)) )
                                            <small class="text-muted"><i class="ion ion-android-contacts"></i>
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
        <div class="col-md-4">
            @if ($course->program->chapters->count()>1)
                <ul class="list-group my-3">

                    @foreach($course->program->chapters as $current_chapter)
                        @if ($user->role == 'admin' || $course->teachers->contains($user) or $current_chapter->isStarted($course))
                            <li class="list-group-item @if ($current_chapter->id == $chapter->id) list-group-item-success @endif">
                                <a
                                        href="{{url('/insider/courses/'.$course->id.'?chapter='.$current_chapter->id)}}">{{$current_chapter->name}}
                                    @if (($course->teachers->contains($user) || $user->role=='admin') and $current_chapter->isStarted($course))
                                        <span class="badge badge-primary"> {{ round($current_chapter->getStudentsPercent($course)) }}
                                            % </span>
                                    @endif
                                    @if ($user->role=='student' || ($user->role=='teacher' && !$course->teachers->contains($user)))
                                        <span class="badge badge-primary"> {{ round($current_chapter->getStudentPercent($course, $user)) }}
                                            % </span>
                                    @endif
                                </a>

                                @if ($course->teachers->contains($user) || $user->role=='admin')
                                    <div class="float-right">
                                        <div class="dropdown">
                                            <button class="btn-options" type="button" data-toggle="dropdown"
                                                    aria-haspopup="true" aria-expanded="false">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>

                                            <div class="dropdown-menu dropdown-menu-right">

                                                <a href="{{url('insider/courses/'.$course->id.'/chapters/'.$current_chapter->id.'/edit')}}"
                                                   class="dropdown-item"><i
                                                            class="icon ion-android-create"></i> Изменить</a>
                                                <a href="{{url('insider/courses/'.$course->id.'/chapters/'.$current_chapter->id.'/lower')}}"
                                                   class="dropdown-item"><i
                                                            class="icon ion-arrow-up-c"></i> Выше</a>
                                                <a href="{{url('insider/courses/'.$course->id.'/chapters/'.$current_chapter->id.'/upper')}}"
                                                   class="dropdown-item"><i
                                                            class="icon ion-arrow-down-c"></i> Ниже</a>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="small mb-0">{{$current_chapter->description}}</p>
                                @else
                                    @if ($current_chapter->isDone($course) and !($user->role=='admin' || $course->teachers->contains($user)))
                                        <span class="float-right">
                                        <i class="icon ion-checkmark-circled text-success"></i> <span
                                                    class="text-success">выполнено</span>
                                        </span>
                                    @endif
                                @endif
                            </li>
                        @endif
                    @endforeach

                </ul>
            @endif


            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Информация <img width="30"
                                                           src="{{ url('images/icons/icons8-info-48.png') }}" alt=""></h4>
                    <p>
                        @if ($course->teachers->contains($user) || $user->role=='admin')
                            <b>Статус:</b> {{$course->state}}<br/>
                            <b>Инвайт:</b> {{$course->invite}}<br/>
                        @endif
                        @if ($course->git!=null)
                            <b><img src="{{ url('images/icons/icons8-git-48.png') }}" title="Git" width="16"
                                    height="16"> Git
                                репозиторий:</b> <a href="{{$course->git}}">{{$course->git}}</a><br/>
                        @endif
                        @if ($course->telegram!=null)
                            <b><img src="{{ url('images/icons/icons8-telegram-app-48.png') }}" title="Telegram App"
                                    width="16"
                                    height="16"> Чат в телеграм:</b> <a
                                    href="{{$course->telegram}}">{{$course->telegram}}</a><br/>
                        @endif
                    </p>
                    <p>
                        <b>Преподаватели:</b>
                    </p>
                    <ul>
                        @foreach($course->teachers as $teacher)
                            <li><a class="text-dark"
                                   href="{{url('/insider/profile/'.$teacher->id)}}">{{$teacher->name}}</a></li>
                        @endforeach
                    </ul>
                    <p>
                        <b>Студенты:</b>
                    </p>
                    @if (count($students) < 70)
                        <ul>
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
                                <li><a class="text-dark"
                                       href="{{url('/insider/profile/'.$student->id)}}">{{$student->name}}</a> <span
                                            class="badge badge-primary float-right" title="Очки опыта: {{$studentPoints}}"> {{ round($studentPercent) }}
                                    % </span></li>
                            @endforeach
                        </ul>
                    @else
                        <ul>
                            @foreach($students as $student)
                                <li><a class="text-dark"
                                       href="{{url('/insider/profile/'.$student->id)}}">{{$student->name}}</a></li>
                            @endforeach
                        </ul>
                    @endif

                    @if (($course->teachers->contains($user) || $user->role=='admin') && count($students) < 40)

                        <div id="histogram" data-plotly-histogram='@json($students->pluck('percent')->values())'></div>

                        <p>
                            <a href="{{url('insider/courses/'.$course->id.'/assessments')}}"
                               class="btn btn-success btn-sm">Очки опыта</a>
                            <a href="{{url('insider/courses/'.$course->id.'/report')}}"
                               class="btn btn-success btn-sm">Отчет</a>
                            <a href="{{url('insider/courses/'.$course->id.'/blocked')}}"
                               class="btn btn-warning btn-sm">Заблокированные</a>
                        </p>
                    @endif

                </div>
            </div>
            @if ($user->role=='student' || ($user->role=='teacher' && !$course->teachers->contains($user)))
                <div class="card mt-3">
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
                            <small class="float-right"><span class="badge badge-primary">{{$points}}
                                    / {{$max_points}}</span></small>
                        </h4>
                        <div class="progress mb-3">
                            @if ($percent < 40)
                                <div class="progress-bar progress-bar-striped bg-danger" role="progressbar"
                                      data-progress-width="{{$percent}}%"
                                     aria-valuenow="{{$percent}}" aria-valuemin="0"
                                     aria-valuemax="100"></div>

                            @elseif($percent < 60)
                                <div class="progress-bar progress-bar-striped bg-warning" role="progressbar"
                                      data-progress-width="{{$percent}}%"
                                     aria-valuenow="{{$percent}}" aria-valuemin="0"
                                     aria-valuemax="100"></div>

                            @else
                                <div class="progress-bar progress-bar-striped bg-success" role="progressbar"
                                      data-progress-width="{{$percent}}%"
                                     aria-valuenow="{{$percent}}" aria-valuemin="0"
                                     aria-valuemax="100"></div>

                            @endif
                        </div>
                        <table class="table">
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
                                        <td>
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
                                                    <span class="badge badge-danger">
                                                            Просрочено {{$deadline->format('Y.m.d')}}
                                                        </span>
                                                @elseif (\Carbon\Carbon::now()->addDays(1)->gt($deadline))
                                                    <span class="badge badge-warning">
                                                            Срок {{$deadline->format('Y.m.d')}}
                                                        </span>
                                                @elseif (\Carbon\Carbon::now()->addDays(1)->lt($deadline))
                                                    <span class="badge badge-light">
                                                            Срок {{$deadline->format('Y.m.d')}}
                                                        </span>
                                                @endif
                                            @endif
                                        </td>
                                        @if ($should_check)
                                            <td><span class="badge badge-warning">{{$mark}} / {{$task->max_mark}}</span></td>
                                        @elseif ($mark == 0)
                                            <td><span class="badge badge-light">{{$mark}} / {{$task->max_mark}}</span></td>
                                        @elseif ($mark == $task->max_mark)
                                            <td><span class="badge badge-success">{{$mark}} / {{$task->max_mark}}</span></td>
                                        @else
                                            <td><span class="badge badge-primary">{{$mark}} / {{$task->max_mark}}</span></td>
                                        @endif

                                    </tr>
                                @endforeach
                            @endforeach
                        </table>
                    </div>
                </div>
            @endif

            <img src="{{url('images/ginger-cat.png')}}" class="img-fluid" alt=""/>
        </div>
    </div>



@endsection
