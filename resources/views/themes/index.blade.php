@extends('layouts.left-menu')
@section('head')
    @if ($is_try)
        <link rel="stylesheet" href="/insider/themes/{{$try->id}}/css"></style>
        <script src="/insider/themes/{{$try->id}}/js"></script>
    @endif
@endsection
@section('content')
    <div class="row">
        <h3 class="col">Темы</h3>
    <ul class="col nav-tabs row nav-fill" style="list-style: none;" id="pills-tab" role="tablist">
        <li class="nav-item">
            <a class="nav-link" id="pills-marketplace-tab" data-toggle="pill" href="#pills-marketplace" role="tab" >Магазин тем</a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" id="pills-own-tab" data-toggle="pill" href="#pills-own" role="tab">Profile</a>
        </li>
        @if(\Auth::user()->is_teacher || \Auth::user()->role == 'admin')
            <li class="nav-item" style="height: 100%;">
                <a class="btn btn-success nav-link" style="color: white;" href="/insider/themes/create">Создать</a>
            </li>
        @endif
    </ul></div>
    <div class="tab-content" id="pills-tabContent">
        <div class="tab-pane fade" id="pills-marketplace" role="tabpanel">
            @foreach($themes as $theme)


                <div class="card" style="min-width: 40%; border-left: 3px solid #28a745;">
                    <div class="card-body">
                        <div class="media">
                            <div class="media-body row">
                                <div style=' width:100px; height:100px; background-image:url("{{$theme->image}}"); background-size: cover; float:left;'></div>
                                <div class="row col" style="margin-left: 0px">
                                    <h5 class="col com-md-12"><a href="{{url('/insider/themes/'.$theme->id)}}"> {{$theme->name}}</a></h5>

                                    <p class="col col-md-12" style="display: flex; justify-content: space-between; align-items: center;">
                                    <small class="disabled">Автор {{$theme->user->name}}</small>
                                    <span>
                                        <a class="btn btn-primary" href="/insider/themes?try={{$theme->id}}">Примерить</a>
@if(\Auth::user()->hasTheme($theme->id))
                                            @if(\Auth::user()->currentTheme() !== null && \Auth::user()->currentTheme()->id == $theme->id)
                                            <a class="btn btn-danger" href="/insider/themes/{{$theme->id}}/takeoff/">
    
                                            Снять
                                        </a>

                                            @else
                                            <a class="btn btn-success" href="/insider/themes/{{$theme->id}}/wear/">
    
                                                Одеть
                                            </a>
                                            @endif
                                        
                                    </span>
                                    @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
        </div>
        <div class="tab-pane fade show active" id="pills-own" role="tabpanel">
            @foreach (\Auth::user()->themes as $theme_bought)
                @php
                    $theme = $theme_bought->theme;
                @endphp
                <div class="card" style="min-width: 40%; border-left: 3px solid #28a745;">
                    <div class="card-body">
                        <div class="media">
                            <div class="media-body row">
                                <div style=" width:100px; height:100px; background-image:url('{{$theme->image}}'); background-size:cover; float:left;"></div>
                                <div class="row col" style="margin-left: 0px">
                                    <h5 class="col com-md-12"><a href="{{url('/insider/themes/'.$theme->id)}}"> {{$theme->name}}</a></h5>

                                    <p class="col col-md-12" style="display: flex; justify-content: space-between; align-items: center;">
                                    <small class="disabled">Автор {{$theme->user->name}}</small>
                                        @if(\Auth::user()->hasTheme($theme->id))
                                    <span>
                                        <a class="btn btn-primary" href="/insider/themes?try={{$theme->id}}">Примерить</a>
                                            @if(\Auth::user()->currentTheme() !== null && \Auth::user()->currentTheme()->id == $theme->id)
                                            <a class="btn btn-danger" href="/insider/themes/{{$theme->id}}/takeoff/">
    
                                            Снять
                                        </a>

                                            @else
                                            <a class="btn btn-success" href="/insider/themes/{{$theme->id}}/wear/">
    
                                                Одеть
                                            </a>
                                            @endif
                                        
                                    </span>
                                    @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>               
            @endforeach
        </div>
    </div>
@endsection




