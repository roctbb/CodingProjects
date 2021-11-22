@extends('layouts.left-menu')

@section('title')
    GeekClass: "{{$program->name}}"
@endsection

@section('tabs')

@endsection



@section('content')
    <div class="row">
        <div class="col">
            <h2 style="font-weight: 300;">{{$program->name}}</h2>
            <p>{{$program->description}}</p>
        </div>
        @if ($user->role=='teacher' || $user->role=='admin')
            <div class="col">
                <div class="float-right">
                    <a href="{{url('/insider/programs/'.$program->id.'/create')}}" class="btn btn-primary btn-sm"><i
                                class="icon ion-compose"></i></a>
                    <a href="{{url('/insider/programs/'.$program->id.'/edit')}}"
                       class="btn btn-primary btn-sm"><i
                                class="icon ion-android-create"></i></a>
                </div>
            </div>
        @endif
    </div>
    <div class="row">
        @foreach($program->lessons as $key => $lesson)
            @if ($lesson->steps->count()!=0)
                <div class="card-group">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col">
                                    <h5>{{$key+1}}. <a class="collection-item"
                                                       href="{{url('/insider/steps/'.$lesson->steps->first()->id)}}">{{$lesson->name}}</a>
                                    </h5>
                                </div>
                                @if ($user->role=='teacher' || $user->role=='admin')
                                    <div class="col-sm-auto">
                                        <a href="{{url('insider/lessons/'.$lesson->id.'/edit')}}"
                                           class="btn btn-success btn-sm"><i
                                                    class="icon ion-android-create"></i></a>
                                        <a href="{{url('insider/lessons/'.$lesson->id.'/lower')}}"
                                           class="btn btn-success btn-sm"><i
                                                    class="icon ion-arrow-up-c"></i></a>
                                        <a href="{{url('insider/lessons/'.$lesson->id.'/upper')}}"
                                           class="btn btn-success btn-sm"><i
                                                    class="icon ion-arrow-down-c"></i></a>
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
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Информация <img style="width: 30px;" src="{{ url('images/icons/icons8-info-48.png') }}"></h4>
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


@endsection
