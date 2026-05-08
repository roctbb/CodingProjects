@extends('layouts.left-menu')

@section('title')
    Журнал
@endsection

@section('content')
    @php
        $tasks = collect([]);
        foreach($course->program->lessons as $lesson) {
            foreach($lesson->steps as $step) {
                $tasks = $tasks->merge($step->tasks);
            }
        }
        $maxPoints = $tasks->where('is_star', false)->sum('max_mark');
    @endphp

    <div class="assessment-page">
        <div class="assessment-header gc-card mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id) }}"><i class="icon ion-chevron-left"></i> К курсу</a>
                <h2 class="mb-1">Журнал опыта</h2>
                <p class="mb-0 text-muted text-truncate">{{ $course->name }}</p>
            </div>
            <div class="assessment-summary">
                <div><strong>{{ $course->students->count() }}</strong><span>учеников</span></div>
                <div><strong>{{ $tasks->count() }}</strong><span>задач</span></div>
                <div><strong>{{ $maxPoints }}</strong><span>XP</span></div>
            </div>
        </div>

        <div class="assessment-legend gc-card mb-3">
            <span><span class="assessment-dot assessment-dot--empty"></span> нет баллов</span>
            <span><span class="assessment-dot assessment-dot--primary"></span> частично</span>
            <span><span class="assessment-dot assessment-dot--success"></span> хорошо</span>
            <span><span class="assessment-dot assessment-dot--warning"></span> на проверке</span>
            <span><span class="assessment-dot assessment-dot--danger"></span> заблокировано</span>
        </div>

        <div class="assessment-scroll-hint gc-card mb-3">
            <i class="fas fa-arrows-alt-h"></i>
            <span>На телефоне журнал можно прокручивать вправо.</span>
        </div>

        <div class="assessment-block gc-card">
            <div class="table-responsive assessment-table-wrap">
                <table class="table table-hover table-sm assessment-table mb-0">
                <thead>
                <tr>
                    <th class="assessment-sticky-col border-bottom-0"></th>
                    @foreach($course->program->lessons as $lesson)
                            @php $lessonTasksCount = $lesson->steps->flatMap->tasks->count(); @endphp
                            @if ($lessonTasksCount != 0)
                                <th colspan="{{ $lessonTasksCount }}" class="assessment-lesson-head">{{$lesson->name}}
                                </th>
                            @endif
                    @endforeach
                    <td class="assessment-total-head"></td>
                </tr>

                <tr>
                    <th class="assessment-sticky-col">Ученик</th>
                    @php
                        $sum = 0;
                    @endphp
                    @foreach($course->program->lessons as $lesson)
                        @foreach($lesson->steps as $step)

                            @foreach($step->tasks as $task)

                                <th class="assessment-task-head">{{$task->name}} <span>({{$task->max_mark}})</span>
                                    @if($task->is_star) <sup>*</sup> @endif
                                    @if($task->only_class) <sup><i class="icon ion-android-contacts"></i></sup> @endif
                                    @if($task->only_remote) <sup><i class="icon ion-at"></i></sup> @endif</th>
                                @php
                                    $sum += $task->max_mark;
                                @endphp
                            @endforeach
                        @endforeach
                    @endforeach
                    <td class="assessment-total-head">Сумма ({{$sum}})</td>
                </tr>
                </thead>
                <tbody>
                @foreach($course->students as $student)
                    <tr>
                        <th scope="row" class="assessment-sticky-col assessment-student-cell">{{$student->name}}</th>
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
                                        $blocked = isset($blockedTaskMap[$student->id . ':' . $task->id]);
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
                                        $class = $blocked ? 'bg-danger' : 'bg-light text-dark';
                                        if (!$blocked) {
                                            if ($mark >= $task->max_mark * 0.5)
                                            {
                                                $class = 'bg-primary';
                                            }
                                            if ($mark >= $task->max_mark * 0.7)
                                            {
                                                $class = 'bg-success';
                                            }
                                            if ($need_check)
                                            {
                                                $class = 'bg-warning text-dark';
                                            }
                                        }


                                    @endphp
                                    <td>
                                        <a target="_blank"
                                           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id)}}">
                                            <span class="badge assessment-mark {{$class}}">{{$mark}}</span>
                                        </a>
                                    </td>
                                @endforeach
                            @endforeach

                        @endforeach
                        <td class="assessment-total-cell">{{$sum}}</td>
                    </tr>
                @endforeach
                </tbody>


            </table>
        </div>
    </div>
    </div>
@endsection
