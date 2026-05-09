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

@section('content')
    <div class="step-page-shell" data-step-details-page>
        <div class="step-reading-progress" data-step-reading-progress hidden>
            <span></span>
        </div>
        @include('steps/partials/nav')

        <main role="main" class="step-main">
            @include('steps/partials/breadcrumb_widget')
            @include('steps/partials/tabs')

            <div class="step-content-layout" data-step-content-layout>
            <div class="tab-content step-content" id="pills-tabContent">

                @include('steps/partials/notes')
                @include('steps/partials/quizer')
                @include('steps/partials/content')

            </div>
            <aside class="step-reading-toc" data-step-reading-toc hidden></aside>
            </div>
            @php
                $isInsider = \Request::is('insider/*');
                $prevStep  = $step->previousStep();
                $nextStep  = $step->nextStep();
                $prevUrl   = $prevStep ? url(($isInsider ? '/insider/courses/'.$course->id.'/steps/' : '/open/steps/').$prevStep->id) : null;
                $nextUrl   = $nextStep ? url(($isInsider ? '/insider/courses/'.$course->id.'/steps/' : '/open/steps/').$nextStep->id) : null;
            @endphp
            @if ($prevUrl || $nextUrl)
                <nav class="step-page-nav mt-4">
                    <div class="step-page-nav__slot">
                        @if ($prevUrl)
                            <a href="{{ $prevUrl }}" class="step-nav-btn step-nav-btn--prev">
                                <i class="icon ion-arrow-left-c"></i>
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
                                <i class="icon ion-arrow-right-c"></i>
                            </a>
                        @endif
                    </div>
                </nav>
            @endif

        </main>


    </div>


    @include('steps/partials/modal')
@endsection
@push('editor')
<script type="text/javascript" src="{{ asset('build/js/vendor/easymde.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('build/js/easymde-bridge.js') }}"></script>
@endpush
