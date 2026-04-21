@extends('layouts.left-menu')

@section('title')
    Программы
@endsection

@section('content')
    <div class="cp-programs-page">
        <div class="row cp-row-gap-top align-items-center">
            <div class="col">
                <h2 class="cp-heading-lite mb-0">Программы</h2>
            </div>
            <div class="col-auto">
                <a class="btn btn-primary btn-sm" href="{{url('/insider/programs/create/')}}"><i
                            class="icon fa-solid fa-circle-plus text-white"></i>&nbsp;Создать</a>
            </div>
        </div>

        <div class="row cp-row-gap-top">
            <div class="col-12 col-xl-10">
                <div class="card cp-list-card">
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                @foreach($programs as $program)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{url('insider/programs/'.$program->id)}}">{{$program->name}}</a>
                                <a class="btn btn-outline-primary btn-sm" href="{{url('insider/programs/'.$program->id)}}">Открыть</a>
                            </li>
                @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
