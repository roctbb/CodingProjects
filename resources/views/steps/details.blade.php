@extends('layouts.fluid')

@section('title')
    @if (\Request::is('insider/*'))
        {{$course->name}} - {{$step->name}}
    @else
        {{$step->name}}
    @endif
@endsection


@section('tabs')

@endsection

@section('head')
    @include('layouts.partials.mathjax')
@endsection

@section('content')
    @php
        $hasStepNavigation = $step->lesson->steps->count() > 1;
        $isInsider = \Request::is('insider/*');
        $isManager = $isInsider && ($course->teachers->contains($user) || $user->role === 'admin');
        $attemptTask = !empty($taskReviewContext['attempt']) ? $tasks->firstWhere('id', $taskReviewContext['task_id']) : null;
        $studentAttempt = $attemptTask && !$isManager;
        $initialTaskId = $taskReviewContext['task_id'] ?? ($zero_theory ? $tasks->first()?->id : null);
        $prevStep = $step->previousStep();
        $nextStep = $step->nextStep();
        $stepBaseUrl = $isInsider ? '/insider/courses/'.$course->id.'/steps/' : '/open/steps/';
        $prevUrl = $prevStep ? url($stepBaseUrl.$prevStep->id) : null;
        $nextUrl = $nextStep ? url($stepBaseUrl.$nextStep->id) : null;
    @endphp
    <div class="step-page-shell gc-workbook {{ $studentAttempt ? 'gc-student-attempt' : '' }}"
         @if($studentAttempt) id="task{{ $attemptTask->id }}" @endif data-step-details-page
         @if($attemptTask) data-attempt-task="{{ $attemptTask->id }}" data-attempt-author="{{ $taskReviewContext['student_id'] }}" @endif>
        <div class="step-reading-progress" data-step-reading-progress hidden>
            <span></span>
        </div>
        <div class="gc-workbook-layout {{ $hasStepNavigation && !$studentAttempt ? 'gc-workbook-layout--with-navigation' : '' }}">
            <header class="gc-workbook-header">
                <div class="gc-workbook-heading-text">
                @if($studentAttempt)
                    <a class="gc-attempt-back" href="{{ url($stepBaseUrl.$step->id.'#task'.$attemptTask->id) }}">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i> {{ $step->name }}
                    </a>
                    @if($attemptTask->is_quiz)<div class="gc-attempt-heading">
                        <div class="gc-task-title-group">
                            <h1>{{ $attemptTask->name }}</h1>
                            @include('steps.partials.task-title-rewards', ['task' => $attemptTask])
                        </div>
                        <div class="gc-chat-outcome"><div data-attempt-verdict aria-live="polite"></div></div>
                    </div>@endif
                @else
                    @include('steps/partials/breadcrumb_widget')
                @endif
                </div>
            </header>
            @if($hasStepNavigation && !$studentAttempt)
                <aside class="gc-workbook-navigation">@include('steps.partials.nav')</aside>
            @endif
        <article class="step-main">
            @if($attemptTask?->is_quiz)
                <div class="gc-task-sheet gc-quiz-card">
                @if(!$studentAttempt)<h2>{{ $attemptTask->name }}</h2>@include('steps.partials.task-title-rewards', ['task' => $attemptTask])@endif
                <div class="markdown">{!! parsedown_math($attemptTask->text) !!}</div>
                @if(!$studentAttempt)<a class="gc-quiet-link" href="{{ url($stepBaseUrl.$step->id.'#task'.$attemptTask->id) }}">К заданию</a>@endif
                @include('steps.partials.native-workspace', [
                    'task' => $attemptTask, 'nativeTeacher' => $isManager, 'nativeUser' => $taskReviewContext['student_id'],
                    'nativeSubmission' => $taskReviewContext['attempt'], 'nativeHistoryOnly' => true,
                ])
                </div>
            @elseif($studentAttempt)
                @include('steps/partials/content')
            @else
            @include('steps/partials/tabs')

            <details class="gc-reading-outline gc-disclosure gc-disclosure--inline" data-reading-outline hidden>
                <summary>В этой теме</summary>
                <nav aria-label="Оглавление конспекта"></nav>
            </details>
            <div class="step-content-layout" data-step-content-layout>
            <div class="tab-content step-content" id="pills-tabContent">

                @include('steps/partials/notes')
                @include('steps/partials/quizer')
                @include('steps/partials/content')

            </div>
            <aside class="step-reading-toc" data-step-reading-toc hidden></aside>
            </div>
            @endif
            @if (!$studentAttempt && ($prevUrl || $nextUrl))
                <nav class="step-page-nav mt-4" aria-label="Переход между ступенями">
                    <div class="step-page-nav__slot">
                        @if ($prevUrl)
                            <a href="{{ $prevUrl }}" class="step-nav-btn step-nav-btn--prev">
                                <i class="fas fa-arrow-left"></i>
                                <span class="step-nav-btn__text">
                                    <small>Назад</small>
                                    <strong>{{ $prevStep->name }}</strong>
                                </span>
                            </a>
                        @endif
                    </div>
                    <div class="step-page-nav__slot step-page-nav__slot--next">
                        @if ($nextUrl)
                            <a href="{{ $nextUrl }}" class="step-nav-btn step-nav-btn--next">
                                <span class="step-nav-btn__text">
                                    <small>Далее</small>
                                    <strong>{{ $nextStep->name }}</strong>
                                </span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        @endif
                    </div>
                </nav>
            @endif

        </article>
        </div>


    </div>



@endsection
