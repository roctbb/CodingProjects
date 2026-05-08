@extends('layouts.empty')

@section('title', $course->name . ' - ' . $step->name)

@section('content')
    <div class="container py-4" style="max-width: 900px;">
        <nav class="mb-3">
            <a href="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id) }}" class="text-decoration-none">
                <i class="fas fa-arrow-left me-1"></i>{{ $course->name }}
            </a>
        </nav>

        <h3 class="mb-3">{{ $step->lesson->name }}</h3>

        @if (count($tasks) && (!$zero_theory || !$one_tasker))
            <ul class="nav nav-pills mb-4" role="tablist">
                @if (!$zero_theory)
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="pill" href="#theory" role="tab">Теория</a>
                    </li>
                @endif
                @foreach ($tasks as $key => $task)
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="pill" href="#task{{ $task->id }}" role="tab">
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
                    <div class="card gc-card">
                        <div class="card-body markdown perform">
                            <h4>{{ $step->name }}</h4>
                            @parsedown($step->theory)
                        </div>
                    </div>
                </div>
            @endif

            @foreach ($tasks as $key => $task)
                <div class="tab-pane fade" id="task{{ $task->id }}" role="tabpanel">
                    @if ($task->is_star)
                        <div class="alert alert-success">
                            <strong>Необязательная задача.</strong> За решение — дополнительные баллы.
                        </div>
                    @endif
                    <div class="card gc-card">
                        <div class="card-header bg-transparent fw-medium">{{ $task->name }}</div>
                        <div class="card-body markdown perform">
                            {!! parsedown_math($task->text) !!}
                            <span class="badge bg-secondary mt-2">Очков опыта: {{ $task->max_mark }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <nav class="d-flex justify-content-between mt-4">
            <div>
                @if ($step->previousStep())
                    <a href="{{ url('/insider/courses/'.$course->id.'/perform/'.$step->previousStep()->id) }}" class="btn step-nav-btn">
                        <i class="fas fa-arrow-left me-1"></i>Назад
                    </a>
                @endif
            </div>
            <div>
                @if ($step->nextStep())
                    <a href="{{ url('/insider/courses/'.$course->id.'/perform/'.$step->nextStep()->id) }}" class="btn step-nav-btn">
                        Вперёд<i class="fas fa-arrow-right ms-1"></i>
                    </a>
                @endif
            </div>
        </nav>
    </div>
@endsection
