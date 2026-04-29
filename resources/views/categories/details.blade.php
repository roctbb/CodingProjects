@extends('layouts.left-menu')

@section('title')
    {{$category->title}}
@endsection
@section('content')
    <div class="row">
        <div class="col-12">

            <div class="jumbotron p-5 p-md-7 text-white bg-dark category-hero"
                 data-background-image="{{url($category->head_image_url)}}">
                <div class="category-hero-spacer"></div>
                <div class="col-md-12 category-hero-panel">

                    <h1 class="display-12 text-white">{{$category->title}}@if (Auth::check() and Auth::user()->role=='admin')
                            <div class="float-right mt-2">

                                <div class="dropdown">
                                    <button class="btn btn-round" data-toggle="dropdown"
                                            data-target="#project-add-modal">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>

                                    <div class="dropdown-menu dropdown-menu-right">
                                        @if (!$category->is_available)
                                            <a href="{{url('/categories/'.$category->id.'/start')}}"
                                               class="dropdown-item"><i
                                                        class="icon ion-power"></i> Показать в каталоге</a>
                                        @else
                                            <a href="{{url('/categories/'.$category->id.'/stop')}}"
                                               class="dropdown-item"><i
                                                        class="icon ion-stop"></i> Спрятать</a>
                                        @endif
                                        <a href="{{url('/categories/'.$category->id.'/edit')}}"
                                           class="dropdown-item"><i
                                                    class="icon ion-android-create"></i> Изменить</a>
                                        <a href="{{url('/categories/'.$category->id.'/delete')}}"
                                           class="dropdown-item"><i
                                                    class="icon ion-android-delete"></i> Удалить</a>
                                    </div>
                                </div>


                            </div>@endif</h1>

                    <p class="text-white">{{$category->short_description}}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3" id="root">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @if ($category->video_url)
                        <div class="embed-responsive embed-responsive-16by9 video-card-bleed">
                            <iframe class="embed-responsive-item" src="{{$category->video_url}}"
                                    allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen></iframe>
                        </div>
                    @endif
                    <h5 class="card-title">Подробнее о направлении</h5>
                    <div class="markdown markdown-big mt-3">
                        @parsedown($category->description)
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <h5 class="mt-4">Онлайн в своем темпе</h5>
            <p class="text-muted">Осваивать теорию и сдавать задачи можно в своем темпе, а вопросы задавать в чате
                преподавателю и другим участникам. При необходимости можно запросить индивидуальную консультацию
                преподавателя.</p>
            @if ($open_courses->count() != 0)
                <div class="card-deck">
                    @foreach($open_courses->sortBy('created_at') as $course)

                        <div class="card course-catalog-card border-left border-left-accent @if ($course->mode == 'open') border-left-accent-success @else border-left-accent-primary @endif"
                              data-background-image="{{$course->imageUrl()}}">
                            <div class="card-body translucent-card-body">
                                <h5 class="card-title font-weight-light mb-1">{{$course->name}}</h5>
                                @if ($course->mode == 'open')
                                    <span class="badge badge-success">Бесплатно</span>
                                @endif
                                <span class="badge badge-primary">Онлайн</span>
                                <p class="card-text small mt-2">{{$course->description}}</p>

                                @if ($course->site != null)
                                    <a target="_blank" href="{{$course->site}}"
                                       class="float-right small mt-1">О курсе</a>
                                @endif
                                @if ($course->mode == 'open')
                                    @if (\Auth::check())
                                        <a href="{{ url('/insider/courses/'.$course->id.'/enroll') }}"
                                           class="btn btn-success btn-sm">Начать учиться</a>
                                    @else
                                        <a href="{{ url('/register?course_id='.$course->id) }}"
                                           class="btn btn-success btn-sm">Начать учиться</a>
                                    @endif
                                @else

                                    <a href="https://forms.gle/EpesRiW2PTCSdGif8" target="_blank"
                                       class="btn btn-primary btn-sm">Записаться</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p>Сейчас нет онлайн курсов по этому направлению.</p>
            @endif

            <h5 class="mt-3">По расписанию с преподавателем</h5>
            <p class="text-muted">Еженедельные занятия по расписанию с преподавателем и группой единомышленников очно
                или онлайн.</p>
            @if ($private_courses->count() != 0)
                <div class="card-deck">
                    @foreach($private_courses->sortBy('start_date') as $course)

                        <div class="card course-catalog-card border-left border-left-accent border-left-accent-info"
                              data-background-image="{{$course->imageUrl()}}">
                            <div class="card-body">
                                <h5 class="card-title font-weight-light mb-1">{{$course->name}}</h5>

                                @if ($course->mode == 'zoom')
                                    <span class="badge badge-primary">Онлайн</span>
                                @else
                                    <span class="badge badge-light">Очный</span>
                                @endif
                                <span class="badge badge-info">С преподавателем</span>


                                <p class="card-text small mt-2">{{$course->description}}</p>
                                @if ($course->start_date)
                                    <p class="card-text text-muted">
                                        @if ($course->state != 'draft')
                                            <small>Курс начался {{ $course->start_date->format('d.m.Y') }}.</small>
                                        @else
                                            <small>Курс начнется {{ $course->start_date->format('d.m.Y') }}.</small>
                                        @endif
                                    </p>
                                @endif

                                @if ($course->site != null)
                                    <a target="_blank" href="{{$course->site}}"
                                       class="float-right small mt-1">О курсе</a>
                                @endif
                                <a href="https://goo.gl/forms/jMsLU855JBFaZRQE2" target="_blank"
                                   class="btn btn-info btn-sm">Оставить заявку</a>

                            </div>

                        </div>
                    @endforeach

                </div>
            @else
                <p>Сейчас нет доступных очных курсов по этому направлению.</p>
            @endif
        </div>

    </div>



@endsection
