@extends('rocket.layouts.top')

@section('title')
    {{ $textbook->name }} - {{ $lesson->name }}
@endsection

@section('content')


    <section class="section-header bg-primary text-white pb-9 pb-lg-12 mb-4 mb-lg-6">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-md-8 text-center">
                    <h1 class="display-2 mb-3">{{ $lesson->name }}</h1>
                </div>
            </div>
        </div>
        <div class="pattern bottom"></div>
    </section>
    <section class="section section-lg pb-4 pt-0">
        <div class="container mt-n8 mt-lg-n12 z-2">
            <div class="row justify-content-center">
                <div class="col">
                    <div class="card shadow-soft border-light p-4 p-lg-5 lesson-content">
                        @foreach($lesson->steps as $step)
                            @if ($step->theory || $step->video_url)
                                <h2 class="h3 mb-4">{{ $step->name }} </h2>

                                @if (\Auth::check() and \Auth::User()->role == 'admin')
                                    <p class="small"><a target="_blank" href="{{ url('/textbook/' . $textbook->id . '/edit/' . $step->id ) }}"><span class="mr-2"><i class="fas fa-pen"></i></span>Изменить</a></p>@endif

                                @if ($step->video_url)
                                    <div class="embed-responsive embed-responsive-16by9 mb-4">
                                        <iframe class="embed-responsive-item" src="{{$step->video_url}}"
                                                allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
                                                allowfullscreen></iframe>
                                    </div>
                                @endif

                                @if ($step->is_notebook)
                                    <div class="notebook-container" id="notebook" data-notebook-content="{{ e($step->theory) }}">

                                    </div>
                                @else
                                    @parsedown($step->theory)
                                @endif
                            @endif
                        @endforeach

                        <div class="text-center pt-4">
                            @if ($previous_id)
                                <a href="{{ url('/textbook/'.$textbook->id.'/lesson/'.$previous_id) }}" type="button" class="btn btn-info mr-sm-3 animate-left-2"><span class="mr-2"><i
                                                class="far fa-arrow-alt-circle-left"></i></span>Назад
                                </a>
                            @endif

                            @if ($next_id)
                                <a href="{{ url('/textbook/'.$textbook->id.'/lesson/'.$next_id) }}" type="button" class="btn btn-info animate-right-2">Дальше<span class="ml-2"><i
                                                class="far fa-arrow-alt-circle-right"></i></span>
                                </a>
                            @endif
                        </div>

                        <!-- Resolved -->
                        <!--
                    <div class="text-center border-top border-bottom border-light my-6 py-6">
                        <h4 class="h4 mb-5">
                            <span class="mr-1"><i class="far fa-newspaper"></i></span>
                            Понятно?
                        </h4>

                        <button type="button" class="btn btn-success mr-sm-3 animate-up-2"><span class="mr-2"><i
                                        class="far fa-thumbs-up"></i></span>Да, спасибо!
                        </button>
                        <button type="button" class="btn btn-danger animate-down-2"><span class="mr-2"><i
                                        class="far fa-thumbs-down"></i></span>Не очень...
                        </button>

                    </div>-->
                        <!-- End Resolved -->

                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
