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
    <div class="row bg-light position-absolute w-100 min-vh-100" data-step-details-page>
        @include('steps/partials/nav')

        <main role="main" class="col-sm-8 ml-sm-auto col-md-9 col-xl-10 pt-3 pb-5 bg-white">
            @include('steps/partials/breadcrumb_widget')
            @include('steps/partials/tabs')


            <div class="tab-content p-3" id="pills-tabContent">

                @include('steps/partials/notes')
                @include('steps/partials/quizer')
                @include('steps/partials/content')

            </div>
            <p class="markdown">
                @if (\Request::is('insider/*'))
                    @if ($step->previousStep() != null)
                        <a href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->previousStep()->id)}}"
                           class="btn btn-success btn-sm">Назад</a>
                    @endif
                    @if ($step->nextStep() != null)
                        <a href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->nextStep()->id)}}"
                           class="btn btn-success btn-sm float-right">Вперед</a>
                    @endif
                @endif
                @if (\Request::is('open/*'))
                    @if ($step->previousStep() != null)
                        <a href="{{url('/open/steps/'.$step->previousStep()->id)}}"
                           class="btn btn-success btn-sm">Назад</a>
                    @endif
                    @if ($step->nextStep() != null)
                        <a href="{{url('/open/steps/'.$step->nextStep()->id)}}"
                           class="btn btn-success btn-sm float-right">Вперед</a>
                    @endif
                @endif
            </p>

        </main>


    </div>


    @include('steps/partials/modal')
@endsection
