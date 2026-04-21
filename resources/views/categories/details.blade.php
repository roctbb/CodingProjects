@extends('layouts.left-menu')

@section('title')
    {{$category->title}}
@endsection
@section('content')
    <div class="category-details-view">
    <div class="row">
        <div class="col-12">

            <div class="jumbotron p-5 p-md-7 text-white category-hero"
                 style='--category-hero-image: url("{{ $category->head_image_url ? url($category->head_image_url) : url("images/clip-education.png") }}");'>
                <div class="category-hero-spacer"></div>
                <div class="col-md-12 category-hero-overlay">
                    <div class="category-hero-head">
                        <h1 class="display-12 category-hero-title">{{$category->title}}</h1>
                        @if (Auth::check() and Auth::user()->role=='admin')
                            <div class="category-hero-actions d-flex justify-content-end">
                                <div class="dropdown">
                                    <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="dropdown">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>

                                    <div class="dropdown-menu dropdown-menu-end">
                                        @if (!$category->is_available)
                                            <a href="#"
                                               data-action-url="{{url('/categories/'.$category->id.'/start')}}"
                                               data-action-method="POST"
                                               class="dropdown-item"><i
                                                        class="icon fa-solid fa-power-off"></i> Показать в каталоге</a>
                                        @else
                                            <a href="#"
                                               data-action-url="{{url('/categories/'.$category->id.'/stop')}}"
                                               data-action-method="POST"
                                               class="dropdown-item"><i
                                                        class="icon fa-solid fa-stop"></i> Спрятать</a>
                                        @endif
                                        <a href="{{url('/categories/'.$category->id.'/edit')}}"
                                           class="dropdown-item"><i
                                                    class="icon fa-solid fa-pen-to-square"></i> Изменить</a>
                                        <a href="{{url('/categories/'.$category->id.'/delete')}}"
                                           class="dropdown-item"><i
                                                    class="icon fa-solid fa-trash"></i> Удалить</a>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <p class="category-hero-description">{{$category->short_description}}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row category-description-row" id="root">
        <div class="col-12">
            <div class="card category-info-card">
                <div class="card-body">
                    @if ($category->video_url)
                        <div class="videoWrapper category-video-wrapper">
                            <iframe width="560" height="315" src="{{$category->video_url}}" frameborder="0"
                                    allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen></iframe>
                        </div>
                    @endif
                    <h5 class="card-title">Подробнее о направлении</h5>
                    <div class="markdown markdown-big category-markdown">
                        @parsedown($category->description)
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <h5 class="category-section-title category-section-title--main">Онлайн в своем темпе</h5>
            <p class="text-muted category-lead-text">Осваивать теорию и сдавать задачи можно в своем темпе, а вопросы задавать в чате
                преподавателю и другим участникам. При необходимости можно запросить индивидуальную консультацию
                преподавателя.</p>
            @if ($open_courses->count() != 0)
                <div class="row g-3 category-course-grid">
                    @foreach($open_courses->sortBy('created_at') as $course)
                        <div class="col-12 col-xl-6">
                        <div class="card category-course-card @if ($course->mode == 'open') category-course-card--open @else category-course-card--paid @endif"
                             style='--category-course-image: url("{{ $course->image ?: url("images/clip-education.png") }}");'>
                            <div class="card-body category-course-card-body d-flex flex-column">
                                <h5 class="card-title category-course-card-title">{{$course->name}}</h5>
                                @if ($course->mode == 'open')
                                    <span class="badge text-bg-success">Бесплатно</span>
                                @endif
                                <span class="badge text-bg-primary">Онлайн</span>
                                <p class="card-text category-course-description">{{$course->description}}</p>
                                <div class="mt-auto d-flex flex-wrap align-items-center gap-2">
                                    @if ($course->mode == 'open')
                                        @if (\Auth::check())
                                            <a href="#"
                                               data-action-url="{{ url('/insider/courses/'.$course->id.'/enroll') }}"
                                               data-action-method="POST"
                                               class="btn btn-primary btn-sm">Начать учиться</a>
                                        @else
                                            <a href="{{ url('/register?course_id='.$course->id) }}"
                                               class="btn btn-primary btn-sm">Начать учиться</a>
                                        @endif
                                    @else

                                        <a href="https://forms.gle/EpesRiW2PTCSdGif8" target="_blank"
                                           class="btn btn-primary btn-sm">Записаться</a>
                                    @endif
                                    @if ($course->site != null)
                                        <a target="_blank" href="{{$course->site}}" class="category-course-link ms-auto">О курсе</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p>Сейчас нет онлайн курсов по этому направлению.</p>
            @endif

            <h5 class="category-section-title">По расписанию с преподавателем</h5>
            <p class="text-muted category-lead-text">Еженедельные занятия по расписанию с преподавателем и группой единомышленников очно
                или онлайн.</p>
            @if ($private_courses->count() != 0)
                <div class="row g-3 category-course-grid">
                    @foreach($private_courses->sortBy('start_date') as $course)
                        <div class="col-12 col-xl-6">
                        <div class="card category-course-card category-course-card--private"
                             style='--category-course-image: url("{{ $course->image ?: url("images/clip-education.png") }}");'>
                            <div class="card-body category-course-card-body d-flex flex-column">
                                <h5 class="card-title category-course-card-title">{{$course->name}}</h5>

                                @if ($course->mode == 'zoom')
                                    <span class="badge text-bg-primary">Онлайн</span>
                                @else
                                    <span class="badge text-bg-light">Очный</span>
                                @endif
                                <span class="badge text-bg-info">С преподавателем</span>


                                <p class="card-text category-course-description">{{$course->description}}</p>
                                @if ($course->start_date)
                                    <p class="card-text text-muted">
                                        @if ($course->state != 'draft')
                                            <small>Курс начался {{ $course->start_date->format('d.m.Y') }}.</small>
                                        @else
                                            <small>Курс начнется {{ $course->start_date->format('d.m.Y') }}.</small>
                                        @endif
                                    </p>
                                @endif
                                <div class="mt-auto d-flex flex-wrap align-items-center gap-2">
                                    <a href="https://goo.gl/forms/jMsLU855JBFaZRQE2" target="_blank"
                                       class="btn btn-info btn-sm">Оставить заявку</a>
                                    @if ($course->site != null)
                                        <a target="_blank" href="{{$course->site}}" class="category-course-link ms-auto">О курсе</a>
                                    @endif
                                </div>

                            </div>

                        </div>
                        </div>
                    @endforeach

                </div>
            @else
                <p>Сейчас нет доступных очных курсов по этому направлению.</p>
            @endif
        </div>

    </div>



    </div>
@endsection
