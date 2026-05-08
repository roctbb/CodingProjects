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
    <div class="report-page">
        <div class="management-header gc-card mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id) }}"><i class="icon ion-chevron-left"></i> К курсу</a>
                <h2 class="mb-1">Отчет по курсу</h2>
                <p class="mb-0 text-muted text-truncate">{{ $course->name }}</p>
            </div>
            <div class="assessment-summary">
                <div><strong>{{ $students->count() }}</strong><span>учеников</span></div>
                <div><strong>{{ $lessons->count() }}</strong><span>уроков</span></div>
                <div><strong>{{ round($students->avg('percent')) }}%</strong><span>средний прогресс</span></div>
            </div>
        </div>

    <div class="row g-3 report-layout">

        <div class="col-12 col-xl-9">
            <div class="tab-content" id="v-pills-tabContent">
                @foreach ($students as $key => $student)
                    <div class="tab-pane fade show @if ($key == 0) active @endif" id="student{{$student->id}}"
                         role="tabpanel"
                         aria-labelledby="v-pills-tab">

                        <div class="gc-card report-student-card w-100">
                            <div class="card-body" id="cardbody{{$student->id}}">
                                <div class="report-student-heading">
                                    <div>
                                        <h4 class="card-title mb-1">{{ $student->name }}</h4>
                                        <small class="text-muted">{{ $student->points }} / {{ $student->max_points }} XP</small>
                                    </div>
                                    <strong>{{ round($student->percent) }}%</strong>
                                </div>
                                <div class="progress mb-3">
                                    @if ($student->percent < 40)
                                        <div class="progress-bar progress-bar-striped bg-danger" role="progressbar"
                                              data-progress-width="{{$student->percent}}%"
                                              data-progress-height="2px"
                                              aria-valuenow="{{$student->percent}}" aria-valuemin="0"
                                              aria-valuemax="100"></div>

                                    @elseif($student->percent < 60)
                                        <div class="progress-bar progress-bar-striped bg-warning" role="progressbar"
                                              data-progress-width="{{$student->percent}}%"
                                              data-progress-height="2px"
                                              aria-valuenow="{{$student->percent}}" aria-valuemin="0"
                                              aria-valuemax="100"></div>

                                    @else
                                        <div class="progress-bar progress-bar-striped bg-success" role="progressbar"
                                              data-progress-width="{{$student->percent}}%"
                                              data-progress-height="2px"
                                              aria-valuenow="{{$student->percent}}" aria-valuemin="0"
                                              aria-valuemax="100"></div>

                                    @endif
                                </div>
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
                                <table class="table table-hover report-lessons-table">
                                    @foreach($lessons as $lesson)

                                        <tr>
                                            <td class="w-50">

                                                <a data-bs-toggle="collapse"
                                                   href="#student{{$student->id}}marks{{$lesson->id}}"
                                                   aria-expanded="false"
                                                   aria-controls="student{{$student->id}}marks{{$lesson->id}}"> {{$lesson->name}}
                                                </a>


                                                @if (!$lesson->isAvailableForUser($course, $student))
                                                    <strong><span class="text-danger">!!!</span></strong> @endif</td>
                                            <td>
                                                <div class="progress m-1">
                                                    @if ($lesson->percent($student, $course) < 40)
                                                        <div class="progress-bar progress-bar-striped bg-danger"
                                                             role="progressbar"
                                                              data-progress-width="{{$lesson->percent($student, $course)}}%"
                                                             aria-valuenow="{{$lesson->percent($student, $course)}}"
                                                             aria-valuemin="0"
                                                             aria-valuemax="100">{{$lesson->points($student, $course)}}
                                                            / {{$lesson->max_points($student, $course)}}</div>

                                                    @elseif($lesson->percent($student, $course) < 60)
                                                        <div class="progress-bar progress-bar-striped bg-warning"
                                                             role="progressbar"
                                                              data-progress-width="{{$lesson->percent($student, $course)}}%"
                                                             aria-valuenow="{{$lesson->percent($student, $course)}}"
                                                             aria-valuemin="0"
                                                             aria-valuemax="100">
                                                            Очки опыта: {{$lesson->points($student, $course)}}
                                                            / {{$lesson->max_points($student, $course)}}</div>

                                                    @else
                                                        <div class="progress-bar progress-bar-striped bg-success"
                                                             role="progressbar"
                                                              data-progress-width="{{$lesson->percent($student, $course)}}%"
                                                             aria-valuenow="{{$lesson->percent($student, $course)}}"
                                                             aria-valuemin="0"
                                                             aria-valuemax="100">
                                                            Очки опыта: {{$lesson->points($student, $course)}}
                                                            / {{$lesson->max_points($student, $course)}}</div>

                                                    @endif
                                                </div>

                                                <div class="collapse" id="student{{$student->id}}marks{{$lesson->id}}">

                                                    @foreach($lesson->steps as $step)
                                                        @php
                                                            $tasks = $step->tasks;
                                                        @endphp
                                                        @foreach($tasks as $task)
                                                            @php
                                                                $filtered = $task->solutions->filter(function ($value) use ($student) {
                                                                    return $value->user_id == $student->id;
                                                                });
                                                                $mark = $filtered->max('mark');
                                                                $mark = $mark == null?0:$mark;
                                                                $should_check = false;
                                                                if (count($filtered)!=0 && $filtered->last()->mark==null) $should_check=true;

                                                            @endphp
                                                            <li class="report-task-row">


                                                                <a target="_blank"
                                                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id)}}">{{$task->name}}</a>


                                                                @php $blocked = $task->isBlocked($student->id, $course->id); @endphp
                                                                @if ($blocked)
                                                                    <span class="badge bg-danger float-end">0</span>
                                                                @elseif ($should_check)
                                                                    <span class="badge bg-warning text-dark float-end">{{$mark}}</span>
                                                                @elseif ($mark == 0)
                                                                    <span class="badge bg-light text-dark float-end">{{$mark}}</span>
                                                                @else
                                                                    <span class="badge bg-primary float-end">{{$mark}}</span>
                                                                @endif

                                                            </li>
                                                        @endforeach
                                                    @endforeach

                                                </div>
                                            </td>


                                        </tr>





                                    @endforeach
                                </table>

                            </div>

                        </div>
                    </div>
                @endforeach

            </div>
        </div>
        <div class="col-12 col-xl-3">
            <div class="nav flex-column nav-pills report-student-nav gc-card" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                @foreach ($students as $key => $student)
                    <a class="nav-link @if ($key == 0) active @endif" id="students-tab" data-bs-toggle="pill"
                       href="#student{{$student->id}}" role="tab"
                       aria-controls="student{{$student->id}}" aria-selected="true"
                       data-plotly-resize-target="pulse{{$student->id}}"><span class="text-truncate">{{$student->name}}</span>
                        @if ($student->percent < 40)
                            <span class="badge bg-danger">&nbsp;</span>
                        @elseif($student->percent < 60)
                            <span class="badge bg-warning text-dark">&nbsp;</span>
                        @else
                            <span class="badge bg-success">&nbsp;</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>
    </div>


@endsection
