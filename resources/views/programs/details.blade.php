@extends('layouts.left-menu')

@section('title')
    {{$program->name}}
@endsection

@section('tabs')

@endsection



@section('content')
    <div class="cp-program-details-page">
    <div class="row">
        <div class="col-12 col-xl-8">
            <h2 class="cp-heading-lite mb-2">{{$program->name}}</h2>
            <p class="mb-0">{{$program->description}}</p>
        </div>
        @if ($user->role=='teacher' || $user->role=='admin')
            <div class="col-12 col-xl-4 mt-3 mt-xl-0">
                <div class="d-flex justify-content-xl-end gap-2">
                    <a href="{{url('/insider/programs/'.$program->id.'/create')}}" class="btn btn-primary btn-sm"><i
                                class="icon fa-solid fa-pen"></i></a>
                    <a href="{{url('/insider/programs/'.$program->id.'/edit')}}"
                       class="btn btn-primary btn-sm"><i
                                class="icon fa-solid fa-pen-to-square"></i></a>
                </div>
            </div>
        @endif
    </div>
    <div class="row cp-row-gap-top">
        <div class="col-12 col-xl-8">
        @foreach($program->lessons as $key => $lesson)
            @if ($lesson->steps->count()!=0)
                <div class="cp-program-lesson-group">
                    <div class="card cp-program-lesson-card">
                        <div class="card-body">
                            <div class="row align-items-center g-2">
                                <div class="col">
                                    <h5>{{$key+1}}. <a class="cp-program-lesson-link"
                                                       href="{{url('/insider/steps/'.$lesson->steps->first()->id)}}">{{$lesson->name}}</a>
                                    </h5>
                                </div>
                                @if ($user->role=='teacher' || $user->role=='admin')
                                    <div class="col-sm-auto d-flex gap-2">
                                        <a href="{{url('insider/lessons/'.$lesson->id.'/edit')}}"
                                           class="btn btn-primary btn-sm"><i
                                                    class="icon fa-solid fa-pen-to-square"></i></a>
                                        <a href="{{url('insider/lessons/'.$lesson->id.'/lower')}}"
                                           class="btn btn-outline-primary btn-sm"><i
                                                    class="icon fa-solid fa-arrow-up"></i></a>
                                        <a href="{{url('insider/lessons/'.$lesson->id.'/upper')}}"
                                           class="btn btn-outline-primary btn-sm"><i
                                                    class="icon fa-solid fa-arrow-down"></i></a>
                                    </div>
                                @endif
                            </div>

                            <div class="row">
                                <div class="col">
                                    @parsedown($lesson->description)
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
        </div>
        <div class="col-12 col-xl-4 cp-row-gap-top cp-row-gap-top-lg">
        <div class="card cp-program-info-card">
            <div class="card-body">
                <h4 class="card-title">Информация <img class="cp-program-info-icon" src="{{ url('images/icons/icons8-info-48.png') }}" alt="Информация"></h4>
                <p>
                    <b>Авторы:</b>
                </p>
                <ul>
                    @foreach($program->authors as $author)
                        <li><a class="black-link"
                               href="{{url('/insider/profile/'.$author->id)}}">{{$author->name}}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>
        </div>
    </div>

    </div>

@endsection
