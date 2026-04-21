@extends('layouts.empty-nc')

@section('title')
    Журнал
@endsection

@section('content')
    <div class="cp-course-assessments-page">
    <div class="cp-assessment-header">
        <h2 class="m-0 cp-heading-lite cp-assessment-title">
            <a class="back-link" href="{{url('/insider/courses/'.$course->id)}}"><i class="icon fa-solid fa-chevron-left"></i></a>
            Очки опыта по курсу "{{$course->name}}"
        </h2>
    </div>
    <div class="assessment-block">
        <div class="table-wrapper table-responsive">
            <table class="table table-striped table-sm align-middle cp-assessment-table">
                <thead>
                <tr class="bg-primary">
                    <th class="border-bottom-0 cp-assessment-student-head"></th>
                    @foreach($course->program->lessons as $lesson)
                            @if ($lesson->tasks()->count()!=0)
                                <th class="cp-assessment-lesson-head" colspan="{{$lesson->tasks()->count()}}">{{$lesson->name}}
                                </th>
                            @endif
                    @endforeach
                    <th class="bg-info cp-assessment-sum-col"></th>
                </tr>

                <tr>
                    <th class="bg-primary cp-assessment-student-head"></th>
                    @php
                        $sum = 0;
                    @endphp
                    @foreach($course->program->lessons as $lesson)
                        @foreach($lesson->steps as $step)

                            @foreach($step->tasks as $task)

                                <th class="bg-primary cp-assessment-task-col" title="{{$task->name}}">{{$task->name}} ({{$task->max_mark}})
                                    @if($task->is_star) <sup>*</sup> @endif
                                    @if($task->only_class) <sup><i class="icon fa-solid fa-users"></i></sup> @endif
                                    @if($task->only_remote) <sup><i class="icon fa-solid fa-at"></i></sup> @endif</th>
                                @php
                                    $sum += $task->max_mark;
                                @endphp
                            @endforeach
                        @endforeach
                    @endforeach
                    <th class="bg-info cp-assessment-sum-col">Сумма ({{$sum}})</th>
                </tr>
                </thead>
                <tbody>
                @foreach($course->students as $student)
                    <tr>
                        <th scope="row" class="cp-assessment-student-col">{{$student->name}}</th>
                        @php
                            $sum = 0;
                        @endphp

                        @foreach($course->program->lessons as $lesson)
                            @foreach($lesson->steps as $step)
                                @foreach($step->tasks as $task)
                                    @php

                                        $filtered = $student->submissions->filter(function ($value) use ($task, $course) {
                                            return $value->task_id == $task->id;
                                        });
                                        $blocked = $task->isBlocked($student->id, $course->id);
                                        if ($blocked) {
                                            $mark = 0;
                                            $need_check = false;
                                        } else {
                                            $mark = $filtered->max('mark');
                                            $mark = $mark == null?0:$mark;
                                            $need_check = false;
                                            if ($filtered->count()!=0 && $filtered->last()->mark==null)
                                            {
                                                $need_check = true;
                                            }
                                        }
                                        $sum += $mark;
                                        $class = $blocked ? 'text-bg-danger' : 'text-bg-light';
                                        if (!$blocked) {
                                            if ($mark >= $task->max_mark * 0.5)
                                            {
                                                $class = 'text-bg-primary';
                                            }
                                            if ($mark >= $task->max_mark * 0.7)
                                            {
                                                $class = 'text-bg-success';
                                            }
                                            if ($need_check)
                                            {
                                                $class = 'text-bg-warning';
                                            }
                                        }


                                    @endphp
                                    <td class="cp-assessment-mark-col">
                                        <a target="_blank"
                                           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id)}}">
                                            <span class="badge {{$class}}">{{$mark}}</span>
                                        </a>
                                    </td>
                                @endforeach
                            @endforeach

                        @endforeach
                        <td class="bg-info cp-assessment-sum-col">{{$sum}}</td>
                    </tr>
                @endforeach
                </tbody>


            </table>
        </div>
    </div>
    </div>
@endsection
