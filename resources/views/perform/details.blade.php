@extends('layouts.fluid')

@section('title')
    {{$course->name}} - {{$step->name}}
@endsection

@section('content')
    <div class="neo-step-page neo-perform-page">
        <section class="steps-hero card border-0">
            <div class="card-body">
                <div class="steps-hero-top">
                    <div>
                        <a class="steps-hero-course-link" href="{{url('/insider/courses/'.$course->id)}}">{{$course->name}}</a>
                        <h1 class="steps-hero-title">{{$step->lesson->name}}</h1>
                        <p class="steps-hero-subtitle">{{$step->name}}</p>
                    </div>
                    <div class="steps-hero-meta">
                        <span class="badge text-bg-secondary">Этапов: {{$step->lesson->steps->count()}}</span>
                        <span class="badge text-bg-primary">Задач: {{count($tasks)}}</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="steps-layout neo-step-layout mt-3">
            @include('steps/partials/nav')

            <main role="main" class="steps-main">
                @include('steps/partials/breadcrumb_widget')
                @include('steps/partials/tabs')

                <div class="tab-content steps-tab-content" id="pills-tabContent">
                    @include('steps/partials/notes')
                    @include('steps/partials/content')
                </div>

                <div class="steps-bottom-nav">
                    @if ($step->previousStep() != null)
                        <a href="{{url('/insider/courses/'.$course->id.'/perform/'.$step->previousStep()->id)}}"
                           class="btn btn-outline-secondary steps-bottom-nav-btn">Назад</a>
                    @endif

                    @if ($step->nextStep() != null)
                        <a href="{{url('/insider/courses/'.$course->id.'/perform/'.$step->nextStep()->id)}}"
                           class="btn btn-primary steps-bottom-nav-btn steps-bottom-nav-btn--next">Вперед</a>
                    @endif
                </div>
            </main>
        </div>
    </div>

    @include('steps/partials/modal')
    <script>
        document.querySelectorAll('blockquote').forEach(function (node) {
            node.classList.add('bd-callout', 'bd-callout-info');
        });

        document.querySelectorAll('table').forEach(function (node) {
            node.classList.add('table', 'table-striped', 'table-sm', 'align-middle');
        });
    </script>
@endsection
