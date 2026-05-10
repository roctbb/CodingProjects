@extends('layouts.empty')

@section('title', $course->name . ' - ' . $step->name)

@section('head')
    @include('layouts.partials.mathjax')
@endsection

@section('content')
    <div class="container-lg py-4">
        <div class="gc-card gc-page-header mb-3">
            <div class="min-width-0">
                <a href="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id) }}" class="assessment-back-link">
                    <i class="fas fa-arrow-left me-1"></i>{{ $course->name }}
                </a>
                <h3 class="fw-bold lh-sm text-truncate mb-1">{{ $step->lesson->name }}</h3>
                <p class="text-muted mb-0 small text-truncate">{{ $step->name }}</p>
            </div>
        </div>

        @if (count($tasks) && (!$zero_theory || !$one_tasker))
            <ul class="nav nav-pills gc-segmented-tabs mb-4" role="tablist">
                @if (!$zero_theory)
                    <li class="nav-item">
                        <a class="nav-link active fw-semibold text-nowrap rounded-3 px-2 px-sm-3 py-2 small" data-bs-toggle="pill" href="#theory" role="tab">Теория</a>
                    </li>
                @endif
                @foreach ($tasks as $key => $task)
                    <li class="nav-item">
                        <a class="nav-link fw-semibold text-nowrap rounded-3 px-2 px-sm-3 py-2 small" data-bs-toggle="pill" href="#task{{ $task->id }}" role="tab">
                            {{ $key + 1 }}. {{ $task->name }}
                            @if($task->is_star)<sup>*</sup>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="tab-content" data-perform-tabs>
            @if ($empty || !$zero_theory)
                <div class="tab-pane fade show active" id="theory" role="tabpanel">
                    <div class="gc-card overflow-hidden">
                        <div class="gc-section-header">
                            <h4 class="fw-bold mb-0">{{ $step->name }}</h4>
                        </div>
                        <div class="markdown perform p-3 p-md-4">
                            @parsedown($step->theory)
                        </div>
                    </div>
                </div>
            @endif

            @foreach ($tasks as $key => $task)
                <div class="tab-pane fade" id="task{{ $task->id }}" role="tabpanel">
                    @if ($task->is_star)
                        <div class="step-task-note step-task-note--optional" role="note">
                            <span class="text-warning-emphasis flex-shrink-0"><i class="fas fa-star"></i></span>
                            <div class="min-width-0">
                                <strong class="d-block">Необязательная задача</strong>
                                <span class="text-muted small">За решение — дополнительные баллы.</span>
                            </div>
                        </div>
                    @endif
                    <div class="gc-card overflow-hidden">
                        <div class="gc-section-header gc-section-header--between">
                            <h4 class="fw-bold mb-0">{{ $task->name }}</h4>
                            <span class="badge rounded-pill bg-body-tertiary flex-shrink-0">{{ $task->max_mark }} XP</span>
                        </div>
                        <div class="markdown perform p-3 p-md-4">
                            {!! parsedown_math($task->text) !!}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <nav class="d-flex justify-content-between mt-4">
            <div>
                @if ($step->previousStep())
                    <a href="{{ url('/insider/courses/'.$course->id.'/perform/'.$step->previousStep()->id) }}" class="btn btn-outline-secondary rounded-3 step-nav-btn">
                        <i class="fas fa-arrow-left me-1"></i>Назад
                    </a>
                @endif
            </div>
            <div>
                @if ($step->nextStep())
                    <a href="{{ url('/insider/courses/'.$course->id.'/perform/'.$step->nextStep()->id) }}" class="btn btn-success rounded-3 step-nav-btn">
                        Вперёд<i class="fas fa-arrow-right ms-1"></i>
                    </a>
                @endif
            </div>
        </nav>
    </div>
@endsection
