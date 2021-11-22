@extends('layouts.left-menu')

@section('title')
    GeekClass: "{{$course->name}}"
@endsection

@section('tabs')

@endsection



@section('content')
    <div class="row">
        <div class="col">
            <h2 style="font-weight: 300;"><a class="back-link" href="{{url('/insider/courses/')}}"><i
                            class="icon ion-chevron-left"></i></a> Отчет по курсу: {{$course->name}}</h2>
        </div>
        <div class="col">
            <div class="float-right">

                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" id="dropdownMenuButton"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Действия
                    </button>
                    <div class="dropdown-menu">
                        <a href="{{url('/insider/courses/'.$course->id.'/edit')}}"
                           class="dropdown-item"><i
                                    class="icon ion-android-create"></i> Изменить курс</a>
                        @if ($course->state=="draft")
                            <a href="{{url('/insider/courses/'.$course->id.'/start')}}"
                               class="dropdown-item"><i
                                        class="icon ion-power"></i> Запустить курс</a>
                        @endif
                        @if ($course->state=="started")
                            <a href="{{url('/insider/courses/'.$course->id.'/stop')}}"
                               class="dropdown-item"><i
                                        class="icon ion-stop"></i> Завершить курс</a>
                        @endif
                    </div>
                </div>


            </div>
        </div>
    </div>

    <div class="row" style="margin-top: 15px;">
        <div class="col-3">
            <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                @foreach ($students as $key => $student)
                    <a class="nav-link @if ($key == 0) active @endif" id="students-tab" data-toggle="pill"
                       href="#student{{$student->id}}" role="tab"
                       aria-controls="student{{$student->id}}" aria-selected="true"
                       onclick="Plotly.relayout('pulse{{$student->id}}', {width: 1.5*getInnerWidth($('#v-pills-tabContent')[0]) + 'px', height: ''});">{{$student->name}}
                        &nbsp;&nbsp;
                        @if ($student_data[$student->id]->percent < 40)
                            <span class="badge badge-danger">&nbsp;</span>
                        @elseif($student_data[$student->id]->percent < 60)
                            <span class="badge badge-warning">&nbsp;</span>
                        @else
                            <span class="badge badge-success">&nbsp;</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
        <div class="col-9">
            <div class="tab-content" id="v-pills-tabContent">
                <script>
                </script>
                @foreach ($students as $student)
                    <div class="tab-pane fade show @if ($key == 0) active @endif" id="student{{$student->id}}"
                         role="tabpanel"
                         aria-labelledby="v-pills-tab">

                        <div class="card" style="width: 100%; min-width: 100%;">
                            <div class="card-body" id="cardbody{{$student->id}}">
                                <h4 class="card-title">{{ $student->name }}</h4>
                                <div class="progress" style="margin-bottom: 15px;">
                                    @if ($student_data[$student->id]->percent < 40)
                                        <div class="progress-bar progress-bar-striped bg-danger" role="progressbar"
                                             style="height: 2px;width: {{$student_data[$student->id]->percent}}%"
                                             aria-valuenow="{{$student_data[$student->id]->percent}}" aria-valuemin="0"
                                             aria-valuemax="100"></div>

                                    @elseif($student_data[$student->id]->percent < 60)
                                        <div class="progress-bar progress-bar-striped bg-warning" role="progressbar"
                                             style="height: 2px;width: {{$student_data[$student->id]->percent}}%"
                                             aria-valuenow="{{$student_data[$student->id]->percent}}" aria-valuemin="0"
                                             aria-valuemax="100"></div>

                                    @else
                                        <div class="progress-bar progress-bar-striped bg-success" role="progressbar"
                                             style="height: 2px;width: {{$student_data[$student->id]->percent}}%"
                                             aria-valuenow="{{$student_data[$student->id]->percent}}" aria-valuemin="0"
                                             aria-valuemax="100"></div>

                                    @endif
                                </div>
                                <table class="table table-striped">
                                    @foreach($lessons[$student->id] as $lesson)

                                        <tr>
                                            <td style="width: 50%;">

                                                <a data-toggle="collapse"
                                                   href="#student{{$student->id}}marks{{$lesson->id}}"
                                                   aria-expanded="false"
                                                   aria-controls="student{{$student->id}}marks{{$lesson->id}}"> {{$lesson->name}}
                                                </a>
                                            </td>
                                            <td>
                                                <div class="progress" style="margin: 5px;">
                                                    @if ($lesson->percent($student) < 40)
                                                        <div class="progress-bar progress-bar-striped bg-danger"
                                                             role="progressbar"
                                                             style="width: {{$lesson->percent($student)}}%"
                                                             aria-valuenow="{{$lesson->percent($student)}}"
                                                             aria-valuemin="0"
                                                             aria-valuemax="100">{{$lesson->points($student)}}
                                                            / {{$lesson->max_points($student)}}</div>

                                                    @elseif($lesson->percent($student) < 60)
                                                        <div class="progress-bar progress-bar-striped bg-warning"
                                                             role="progressbar"
                                                             style="width: {{$lesson->percent($student)}}%"
                                                             aria-valuenow="{{$lesson->percent($student)}}"
                                                             aria-valuemin="0"
                                                             aria-valuemax="100">
                                                            Очки опыта: {{$lesson->points($student)}}
                                                            / {{$lesson->max_points($student)}}</div>

                                                    @else
                                                        <div class="progress-bar progress-bar-striped bg-success"
                                                             role="progressbar"
                                                             style="width: {{$lesson->percent($student)}}%"
                                                             aria-valuenow="{{$lesson->percent($student)}}"
                                                             aria-valuemin="0"
                                                             aria-valuemax="100">
                                                            Очки опыта: {{$lesson->points($student)}}
                                                            / {{$lesson->max_points($student)}}</div>

                                                    @endif
                                                </div>

                                                <div class="collapse" id="student{{$student->id}}marks{{$lesson->id}}">

                                                    @foreach($lesson->steps as $step)
                                                        @php
                                                            if ($student->pivot->is_remote)
                                                            {
                                                            $tasks = $step->remote_tasks;
                                                            }
                                                            else {
                                                            $tasks = $step->class_tasks;
                                                            }
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
                                                            <li style="padding-right: 10px;">


                                                                <a target="_blank"
                                                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id)}}">{{$task->name}}</a>


                                                                @if ($should_check)
                                                                    <span class="badge badge-warning float-right">{{$mark}}</span>
                                                                @elseif ($mark == 0)
                                                                    <span class="badge badge-light float-right">{{$mark}}</span>
                                                                @else
                                                                    <span class="badge badge-primary float-right">{{$mark}}</span>
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
    </div>



    <script>
        $(function () {
            $('[data-toggle="popover"]').popover()
        });
        $('.popover-dismiss').popover({
            trigger: 'focus'
        });

        function getInnerWidth(element) {

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

    </script>

@endsection
