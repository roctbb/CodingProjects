@extends('rocket.layouts.top')

@section('title')
    Проектные онлайн курсы по программированию
@endsection

@section('content')
    <!-- Hero -->
    <section class="section-header pb-9 pb-lg-12 mb-4 mb-lg-6 bg-primary text-white">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-md-8 text-center">
                    <h1 class="display-2 mb-3">Онлайн-курсы по компьютерным наукам</h1>
                    <p class="lead">Прикладное программирование, анализ данных и информационная безопасность для старшеклассников онлайн в мини-группах.</p>
                </div>
            </div>
        </div>
        <div class="pattern bottom"></div>
    </section>
    <section class="section section-lg pt-0">
        <div class="container mt-n8 mt-lg-n12 z-2">
            <div class="row">
                <div class="col-12 col-lg-4 mb-4 mb-lg-0">
                    <div class="card bg-white border-light shadow-soft p-4">
                        <div class="card-body p-3">
                            <div class="icon icon-lg icon-primary justify-content-start mb-3">
                                <span class="fas fa-user-astronaut"></span>
                            </div>
                            <h4 class="mb-4">Просто</h4>
                            <p>Двигаясь от простого к сложному, объясняем высокие технологии понятным языком.<br><br></p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-4 mb-4 mb-lg-0">
                    <div class="card bg-white border-light shadow-soft p-4">
                        <div class="card-body p-3">
                            <div class="icon icon-lg icon-primary justify-content-start mb-3">
                                <span class="fas fa-smile-wink"></span>
                            </div>
                            <h4 class="mb-4">Интересно и полезно</h4>
                            <p>Учимся через творческие мини-проекты. Полученный опыт сразу же можно применить в работе и учебе.</p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="card bg-white border-light shadow-soft p-4">
                        <div class="card-body p-3">
                            <div class="icon icon-lg icon-primary justify-content-start mb-3">
                                <span class="fas fa-atom"></span>
                            </div>
                            <h4 class="mb-4">Фундаментально</h4>
                            <p>Погружаемся в суть вещей и их теоретические основания: математику, физику, компьютерные науки.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="section section-lg pt-0">
        <div class="container">
            <div class="row justify-content-center mb-1">
                <div class="col-12 text-center">
                    <h2 class="h1 mb-3">Курсы</h2>
                    <p class="lead px-5 px-lg-7">Стартуем как только наберется группа из 3 - 5 человек.</p>
                </div>
            </div>
            <div class="row">
                @foreach($courses as $course)
                    <div class="col-12 mb-4">
                        <div class="card bg-white border-light shadow-soft">
                            <div class="card-body p-4 p-lg-5">
                                <div class="row">
                                    <div class="col-12 col-md-6 mb-4 mb-lg-0">
                                        <h3 class="mb-3">{{ $course->name }}</h3>
                                        <p class="text-gray mb-4">
                                            {{ $course->landing_short_description }}
                                        </p>
                                        <div class="d-flex">
                                            <i class="fas fa-calendar"></i><span class="h6 text-sm ml-2">{{ $course->landing_length }}</span>
                                            <span class="ml-4"><i class="fas fa-brain text-secondary"></i><span class="h6 text-sm ml-2">{{ $course->landing_level }}</span></span>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 d-flex align-items-center justify-content-md-end">
                                        <a href="{{ $course->landing_enrollment_link }}" target="_blank" class="btn btn-secondary mr-3 animate-up-2 m-0">Записаться</a>
                                        <a href="{{ url('/courses/'.$course->id) }}" class="btn btn-primary animate-up-2 m-0"><i class="fas fa-clipboard-list mr-2"></i>Подробнее</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>


@endsection
