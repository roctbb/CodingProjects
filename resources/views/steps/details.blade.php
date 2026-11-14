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
        $prevStep = $step->previousStep();
        $nextStep = $step->nextStep();
        $stepBaseUrl = $isInsider ? '/insider/courses/'.$course->id.'/steps/' : '/open/steps/';
        $prevUrl = $prevStep ? url($stepBaseUrl.$prevStep->id) : null;
        $nextUrl = $nextStep ? url($stepBaseUrl.$nextStep->id) : null;
    @endphp
    <div class="step-page-shell gc-workbook" data-step-details-page>
        <div class="step-reading-progress" data-step-reading-progress hidden>
            <span></span>
        </div>
        <div class="gc-workbook-layout {{ $hasStepNavigation ? 'gc-workbook-layout--with-navigation' : '' }}">
            <header class="gc-workbook-header">
                @include('steps/partials/breadcrumb_widget')
            </header>
            @if($hasStepNavigation)
                <aside class="gc-workbook-navigation">@include('steps.partials.nav')</aside>
            @endif
        <article class="step-main">
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
            @if ($prevUrl || $nextUrl)
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
