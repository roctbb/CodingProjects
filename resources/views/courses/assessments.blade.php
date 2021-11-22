@extends('layouts.empty-nc')

@section('title')
    GeekClass: Журнал
@endsection

@section('content')
    <h2 style="margin: 20px;"><a class="back-link" href="{{url('/insider/courses/'.$course->id)}}"><i
                    class="icon ion-chevron-left"></i></a>&nbsp;Очки опыта по курсу "{{$course->name}}"</h2>
    <div class="assessment-block">
        <div class="table-wrapper">
            <table class="table table-striped  table-sm">
                <thead class="thead-inverse">
                <tr class="bg-primary">
                    <th style="border-bottom: none;"></th>
                    @foreach($course->lessons as $lesson)
                            @if ($lesson->tasks()->count()!=0)
                                <th colspan="{{$lesson->tasks()->count()}}">{{$lesson->name}}
                                </th>
                            @endif
                    @endforeach
                    <td class="bg-info"></td>
                </tr>

                <tr>
                    <th class="bg-primary"></th>
                    @php
                        $sum = 0;
                    @endphp
                    @foreach($course->lessons as $lesson)
                        @foreach($lesson->steps as $step)

                            @foreach($step->tasks as $task)

                                <th class="bg-primary">{{$task->name}} ({{$task->max_mark}})
                                    @if($task->is_star) <sup>*</sup> @endif
                                    @if($task->only_class) <sup><i class="icon ion-android-contacts"></i></sup> @endif
                                    @if($task->only_remote) <sup><i class="icon ion-at"></i></sup> @endif</th>
                                @php
                                    $sum += $task->max_mark;
                                @endphp
                            @endforeach
                        @endforeach
                    @endforeach
                    <td class="bg-info">Сумма ({{$sum}})</td>
                </tr>
                </thead>
                <tbody>
                @foreach($course->students as $student)
                    <tr>
                        <th scope="row">{{$student->name}}</th>
                        @php
                            $sum = 0;
                        @endphp

                        @foreach($course->lessons as $lesson)
                            @foreach($lesson->steps as $step)
                                @foreach($step->tasks as $task)
                                    @php

                                        $filtered = $student->submissions->filter(function ($value) use ($task, $course) {
                                            return $value->task_id == $task->id;
                                        });
                                        $mark = $filtered->max('mark');
                                        $mark = $mark == null?0:$mark;
                                        $need_check = false;
                                        if ($filtered->count()!=0 && $filtered->last()->mark==null)
                                        {
                                            $need_check = true;
                                        }
                                        $sum += $mark;
                                        $class = 'badge-light';
                                        if ($mark >= $task->max_mark * 0.5)
                                        {
                                            $class = 'badge-primary';
                                        }
                                        if ($mark >= $task->max_mark * 0.7)
                                        {
                                            $class = 'badge-success';
                                        }
                                        if ($need_check)
                                        {
                                            $class = 'badge-warning';
                                        }


                                    @endphp
                                    <td>
                                        <a target="_blank"
                                           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id)}}">
                                            <span class="badge {{$class}}">{{$mark}}</span>
                                        </a>
                                    </td>
                                @endforeach
                            @endforeach

                        @endforeach
                        <td class="bg-info">{{$sum}}<br>&nbsp;</td>
                    </tr>
                @endforeach
                </tbody>


            </table>
        </div>
    </div>
@endsection