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

    <div class="container-fluid px-0">
        <div class="gc-card gc-page-header mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id) }}"><i class="icon ion-chevron-left"></i> К курсу</a>
                <h2 class="mb-1">Журнал опыта</h2>
                <p class="mb-0 text-muted text-truncate">{{ $course->name }}</p>
            </div>
            <div class="row row-cols-3 g-2 flex-shrink-0">
                <div class="col"><div class="gc-summary-tile"><strong>{{ $course->students->count() }}</strong><span>учеников</span></div></div>
                <div class="col"><div class="gc-summary-tile"><strong>{{ $tasks->count() }}</strong><span>задач</span></div></div>
                <div class="col"><div class="gc-summary-tile"><strong>{{ $maxPoints }}</strong><span>XP</span></div></div>
            </div>
        </div>

        <div class="gc-card gc-toolbar-card assessment-toolbar">
            <div>
                <h5 class="mb-1">Баллы по задачам</h5>
                <p class="mb-0 text-muted small">Сводка по ученикам, урокам и задачам курса.</p>
            </div>
            <div class="d-flex flex-column flex-xl-row align-items-xl-center gap-3">
                <div class="input-group input-group-sm gc-search-box assessment-student-search">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="search" class="form-control" placeholder="Найти ученика" aria-label="Найти ученика" data-assessment-student-search data-assessment-table="#assessment-table">
                    <button class="btn d-none" type="button" data-assessment-student-clear aria-label="Очистить поиск"><i class="fas fa-times"></i></button>
                    <span class="input-group-text gc-search-box__count" data-assessment-student-count>{{ $course->students->count() }} из {{ $course->students->count() }}</span>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3 text-muted small assessment-legend">
                    <span><span class="assessment-dot assessment-dot--empty"></span> нет баллов</span>
                    <span><span class="assessment-dot assessment-dot--primary"></span> частично</span>
                    <span><span class="assessment-dot assessment-dot--success"></span> хорошо</span>
                    <span><span class="assessment-dot assessment-dot--warning"></span> на проверке</span>
                    <span><span class="assessment-dot assessment-dot--danger"></span> заблокировано</span>
                </div>
            </div>
        </div>

        <div class="gc-card p-3 mb-3 d-flex d-md-none align-items-center gap-2 text-muted small">
            <i class="fas fa-arrows-alt-h"></i>
            <span>На телефоне журнал можно прокручивать вправо.</span>
        </div>

        <div class="gc-card overflow-hidden">
            <div class="table-responsive assessment-table-wrap">
                <table class="table table-hover table-sm align-middle assessment-table gc-data-table mb-0" id="assessment-table">
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
                    <tr data-assessment-row data-assessment-student-name="{{ $student->name }} {{ $student->activeCustomTitle() }}">
                        <th scope="row" class="assessment-sticky-col assessment-student-cell">
                            <span class="d-inline-flex align-items-center gap-1 min-width-0">
                                <span class="text-truncate">{{$student->name}}</span>
                                @include('profile.partials.custom_title_badge', ['profileUser' => $student, 'compact' => true])
                            </span>
                        </th>
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
                                        $bestSolution = \App\Solution::bestScoredIn($filtered);
                                        $blocked = isset($blockedTaskMap[$student->id . ':' . $task->id]);
                                        if ($blocked) {
                                            $mark = 0;
                                            $need_check = false;
                                        } else {
                                            $mark = $bestSolution ? $bestSolution->mark : 0;
                                            $need_check = false;
                                            if ($filtered->count()!=0 && $filtered->last()->mark==null)
                                            {
                                                $need_check = true;
                                            }
                                        }
                                        $sum += $mark;
                                        $class = $blocked ? 'bg-danger-subtle text-danger border border-danger-subtle fw-semibold' : ($bestSolution ? $bestSolution->scoreBadgeClass('bg-body-tertiary fw-semibold') : 'bg-body-tertiary fw-semibold');
                                        if (!$blocked) {
                                            if ($mark >= $task->max_mark * 0.5)
                                            {
                                                $class = $bestSolution ? $bestSolution->scoreBadgeClass('bg-body-tertiary fw-semibold') : 'bg-body-tertiary fw-semibold';
                                            }
                                            if ($mark >= $task->max_mark * 0.7)
                                            {
                                                $class = $bestSolution ? $bestSolution->scoreBadgeClass('bg-body-tertiary fw-semibold') : 'bg-body-tertiary fw-semibold';
                                            }
                                            if ($need_check)
                                            {
                                                $class = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold';
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
