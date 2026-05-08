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
        @include('steps/partials/nav')

        <main role="main" class="step-main">
            @include('steps/partials/breadcrumb_widget')
            @include('steps/partials/tabs')


            <div class="tab-content step-content" id="pills-tabContent">

                @include('steps/partials/notes')
                @include('steps/partials/quizer')
                @include('steps/partials/content')

            </div>
            @php
                $isInsider = \Request::is('insider/*');
                $prevStep  = $step->previousStep();
                $nextStep  = $step->nextStep();
                $prevUrl   = $prevStep ? url(($isInsider ? '/insider/courses/'.$course->id.'/steps/' : '/open/steps/').$prevStep->id) : null;
                $nextUrl   = $nextStep ? url(($isInsider ? '/insider/courses/'.$course->id.'/steps/' : '/open/steps/').$nextStep->id) : null;
            @endphp
            @if ($prevUrl || $nextUrl)
                <nav class="step-page-nav mt-4 d-flex justify-content-between">
                    <div>
                        @if ($prevUrl)
                            <a href="{{ $prevUrl }}" class="btn step-nav-btn step-nav-btn--prev">
                                <i class="icon ion-arrow-left-c"></i> Назад
                            </a>
                        @endif
                    </div>
                    <div>
                        @if ($nextUrl)
                            <a href="{{ $nextUrl }}" class="btn step-nav-btn step-nav-btn--next">
                                Вперед <i class="icon ion-arrow-right-c"></i>
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
