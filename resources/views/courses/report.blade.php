@extends('layouts.left-menu')

@section('title')
    {{$course->name}}
@endsection

@section('tabs')

@endsection



@section('content')
    <div class="cp-course-report-page">
    <div class="row cp-report-header align-items-center g-2">
        <div class="col-12 col-xl">
            <h2 class="cp-heading-lite cp-report-title">
                <a class="back-link" href="{{url('/insider/courses/'.$course->id)}}"><i class="icon fa-solid fa-chevron-left"></i></a>
                Отчет по курсу: {{$course->name}}
            </h2>
        </div>
        <div class="col-12 col-xl-auto">
            <div class="cp-report-meta">
                <span class="badge rounded-pill text-bg-light">Студентов: {{ $students->count() }}</span>
                <span class="badge rounded-pill text-bg-light">Уроков: {{ $lessons->count() }}</span>
            </div>
        </div>
    </div>

    <div class="row g-3">

        <div class="col-12 col-xl-9">
            <div class="tab-content" id="v-pills-tabContent">
                @foreach ($students as $key => $student)
                    <div class="tab-pane fade show @if ($key == 0) active @endif" id="student{{$student->id}}"
                         role="tabpanel"
                         aria-labelledby="students-tab-{{$student->id}}">

                        <div class="card w-100 cp-report-student-card">
                            <div class="card-body" id="cardbody{{$student->id}}">
                                <h4 class="card-title">{{ $student->name }}</h4>
                                <div class="progress cp-mb-15">
                                    @if ($student->percent < 40)
                                        <div class="progress-bar progress-bar-striped bg-danger cp-progress-thin" role="progressbar"
                                             style="width: {{$student->percent}}%"
                                             aria-valuenow="{{$student->percent}}" aria-valuemin="0"
                                             aria-valuemax="100"></div>

                                    @elseif($student->percent < 60)
                                        <div class="progress-bar progress-bar-striped bg-warning cp-progress-thin" role="progressbar"
                                             style="width: {{$student->percent}}%"
                                             aria-valuenow="{{$student->percent}}" aria-valuemin="0"
                                             aria-valuemax="100"></div>

                                    @else
                                        <div class="progress-bar progress-bar-striped bg-success cp-progress-thin" role="progressbar"
                                             style="width: {{$student->percent}}%"
                                             aria-valuenow="{{$student->percent}}" aria-valuemin="0"
                                             aria-valuemax="100"></div>

                                    @endif
                                </div>
                                @if ($pulse_keys->has($student->id))
                                    <div id="pulse{{$student->id}}" class="w-100 mb-2 cp-min-h-200"></div>

                                    <script>
                                        var data = [
                                            {
                                                x: {!! $pulse_keys[$student->id] !!},
                                                y: {!! $pulse_values[$student->id] !!},
                                                type: 'scatter',
                                                line: {shape: 'spline'},
                                            }@if ($task_keys->has($student->id))
                                            ,
                                            {
                                                x: {!! $task_keys[$student->id] !!},
                                                y: {!! $task_values[$student->id] !!},
                                                type: 'scatter',
                                                yaxis: 'y2',
                                                line: {shape: 'spline'},
                                                fill: 'tonexty',
                                            }@endif

                                        ];

                                        plot{{$student->id}} = Plotly.newPlot('pulse{{$student->id}}', data, {
                                            xaxis: {

                                                zeroline: false,
                                                showline: false,

                                            }, yaxis: {
                                                zeroline: false,
                                                showline: false
                                            }, yaxis2: {
                                                side: 'right',
                                                zeroline: false,
                                                showline: false,
                                                overlaying: 'y'
                                            }, margin: {
                                                l: 15,
                                                r: 20,
                                                b: 30,
                                                t: 3,
                                                pad: 0
                                            },
                                            showlegend: false
                                        }, {staticPlot: false, displayModeBar: false, responsive: false});
                                    </script>

                                @endif
                                <div class="table-responsive cp-report-table-wrap">
                                <table class="table table-striped table-sm align-middle cp-report-lessons-table mb-0">
                                    @foreach($lessons as $lesson)

                                        <tr>
                                            <td class="w-50">

                                                <a class="cp-report-lesson-link" data-bs-toggle="collapse"
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
                                                             style="width: {{$lesson->percent($student, $course)}}%"
                                                             aria-valuenow="{{$lesson->percent($student, $course)}}"
                                                             aria-valuemin="0"
                                                             aria-valuemax="100">
                                                            Очки опыта: {{$lesson->points($student, $course)}}
                                                            / {{$lesson->max_points($student, $course)}}</div>

                                                    @elseif($lesson->percent($student, $course) < 60)
                                                        <div class="progress-bar progress-bar-striped bg-warning"
                                                             role="progressbar"
                                                             style="width: {{$lesson->percent($student, $course)}}%"
                                                             aria-valuenow="{{$lesson->percent($student, $course)}}"
                                                             aria-valuemin="0"
                                                             aria-valuemax="100">
                                                            Очки опыта: {{$lesson->points($student, $course)}}
                                                            / {{$lesson->max_points($student, $course)}}</div>

                                                    @else
                                                        <div class="progress-bar progress-bar-striped bg-success"
                                                             role="progressbar"
                                                             style="width: {{$lesson->percent($student, $course)}}%"
                                                             aria-valuenow="{{$lesson->percent($student, $course)}}"
                                                             aria-valuemin="0"
                                                             aria-valuemax="100">
                                                            Очки опыта: {{$lesson->points($student, $course)}}
                                                            / {{$lesson->max_points($student, $course)}}</div>

                                                    @endif
                                                </div>

                                                <div class="collapse cp-report-lesson-details" id="student{{$student->id}}marks{{$lesson->id}}">

                                                    <ul class="list-unstyled mb-0">
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
                                                            <li class="pe-2 d-flex align-items-center justify-content-between gap-2">
                                                                <a target="_blank"
                                                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id)}}">{{$task->name}}</a>


                                                                @php $blocked = $task->isBlocked($student->id, $course->id); @endphp
                                                                @if ($blocked)
                                                <span class="badge text-bg-danger">0</span>
                                                                @elseif ($should_check)
                                                                <span class="badge text-bg-warning">{{$mark}}</span>
                                                                @elseif ($mark == 0)
                                                                    <span class="badge text-bg-light">{{$mark}}</span>
                                                                @else
                                                                    <span class="badge text-bg-primary">{{$mark}}</span>
                                                                @endif

                                                            </li>
                                                        @endforeach
                                                    @endforeach
                                                    </ul>

                                                </div>
                                            </td>


                                        </tr>





                                    @endforeach
                                </table>
                                </div>

                            </div>

                        </div>
                    </div>
                @endforeach

            </div>
        </div>
        <div class="col-12 col-xl-3">
            <div class="nav flex-column nav-pills cp-report-students-nav" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                @foreach ($students as $key => $student)
                    <button class="nav-link @if ($key == 0) active @endif"
                            id="students-tab-{{$student->id}}"
                            data-bs-toggle="pill"
                            data-bs-target="#student{{$student->id}}"
                            data-student-id="{{$student->id}}"
                            type="button"
                            role="tab"
                            aria-controls="student{{$student->id}}"
                            aria-selected="{{ $key == 0 ? 'true' : 'false' }}">
                        <span class="cp-report-student-name">{{$student->name}}</span>
                        @if ($student->percent < 40)
                            <span class="badge text-bg-danger cp-report-student-state">&nbsp;</span>
                        @elseif($student->percent < 60)
                            <span class="badge text-bg-warning cp-report-student-state">&nbsp;</span>
                        @else
                            <span class="badge text-bg-success cp-report-student-state">&nbsp;</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    </div>



    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.CPUI) {
                    window.CPUI.initPopovers('[data-bs-toggle="popover"]');
                window.CPUI.initPopovers('.popover-dismiss', {trigger: 'focus'});
            }
        });

        function getInnerWidth(element) {
            if (!element) {
                return 0;
            }

            var wrapper = document.createElement('span'),
                result;

            while (element.firstChild) {
                wrapper.appendChild(element.firstChild);
            }

            element.appendChild(wrapper);

            result = wrapper.offsetWidth;

            element.removeChild(wrapper);

            while (wrapper.firstChild) {
                element.appendChild(wrapper.firstChild);
            }

            return result;

        }

        function relayoutPulse(studentId) {
            var chartId = 'pulse' + studentId;
            var chartEl = document.getElementById(chartId);
            if (!chartEl || typeof Plotly === 'undefined' || typeof Plotly.relayout !== 'function') {
                return;
            }

            var targetWidth = chartEl.parentElement ? chartEl.parentElement.clientWidth : 0;
            if (!targetWidth) {
                var contentEl = document.getElementById('v-pills-tabContent');
                targetWidth = contentEl ? getInnerWidth(contentEl) : 0;
            }

            if (!targetWidth) {
                return;
            }

            Plotly.relayout(chartId, {
                width: targetWidth,
                height: ''
            });
        }

        document.querySelectorAll('#v-pills-tab [data-bs-toggle="pill"]').forEach(function (tabButton) {
            tabButton.addEventListener('shown.bs.tab', function (event) {
                var studentId = event.target.getAttribute('data-student-id');
                if (studentId) {
                    relayoutPulse(studentId);
                }
            });
        });

    </script>
    </div>

@endsection
