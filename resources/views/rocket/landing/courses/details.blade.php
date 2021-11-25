@extends('rocket.layouts.top')

@section('title')
{{ $course->name }}
@endsection

@section('content')
    <!-- Hero -->

    <section class="section-header pb-10 pb-lg-11 mb-4 mb-lg-6 bg-primary text-white">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 text-center mb-4 mb-lg-5">
                    <h1 class="display-2 font-weight-extreme mb-4">{{ $course->name }}</h1>
                    <div class="d-flex flex-column flex-lg-row justify-content-center">
                        <span class="h5 mb-3 mb-lg-0"><i class="fas fa-map-marker-alt"></i><span class="ml-3">{{ $course->landing_timetable }}</span></span>
                        <span class="ml-lg-5 mb-3 mb-lg-0 h5"><i class="fas fa-map-marked"></i><span class="ml-3">{{ $course->landing_group_size }}</span></span>
                        <span class="ml-lg-5 mb-3 mb-lg-0 h5"><i class="fas fa-ruble-sign"></i><span class="ml-3">{{ $course->landing_price }}</span></span>
                    </div>
                </div>
                <div class="col col-12 text-center">
                    <a href="{{ url('/courses') }}" class="btn btn-secondary text-white animate-up-2 mr-3"><i
                                class="fas fa-arrow-left mr-2"></i>Все курсы</a>
                    <a href="{{ $course->landing_enrollment_link }}" target="_blank"
                       class="btn btn-white text-primary animate-up-2"><i
                                class="fas fa-clipboard-list mr-2"></i>Оставить заявку</a>
                </div>
            </div>
        </div>
        <div class="pattern bottom"></div>
    </section>
    <section class="section section-lg pt-0">
        {!! $course->landing_html_description  !!}
    </section>

@endsection
