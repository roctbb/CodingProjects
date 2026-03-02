@extends('layouts.left-menu')
@section('title')
    Темы оформления
@endsection
@section('head')
    @if ($is_try)
        <link rel="stylesheet" href="/insider/themes/{{$try->id}}/css">
        <script src="/insider/themes/{{$try->id}}/js"></script>
    @endif
@endsection
@section('content')
    <div class="row">
        <h3 class="col">Темы</h3>
        <ul class="col nav-tabs row nav-fill" style="list-style: none;" id="pills-tab" role="tablist">
            <li class="nav-item">
                <a class="nav-link" id="pills-marketplace-tab" data-toggle="pill" href="#pills-marketplace" role="tab">Магазин тем</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" id="pills-own-tab" data-toggle="pill" href="#pills-own" role="tab">Profile</a>
            </li>
            @if(\Auth::user()->canCreateThemes())
                <li class="nav-item" style="height: 100%;">
                    <a class="btn btn-success nav-link" style="color: white;" href="/insider/themes/create">Создать</a>
                </li>
            @endif
            @if(\Auth::user()->isThemeModerator())
                <li class="nav-item" style="height: 100%;">
                    <a class="btn btn-warning nav-link" style="color: white;" href="/insider/themes/moderation">Модерация</a>
                </li>
            @endif
        </ul>
    </div>

    <div class="tab-content" id="pills-tabContent">
        <div class="tab-pane fade" id="pills-marketplace" role="tabpanel">
            @foreach($themes as $theme)
                <div class="card" style="min-width: 40%; border-left: 3px solid {{ $theme && $theme->isPending() ? '#ffc107' : ($theme && $theme->isBanned() ? '#dc3545' : '#28a745') }};">
                    <div class="card-body">
                        <div class="media">
                            <div class="media-body row">
                                <div style="width:100px; height:100px; background-image:url('{{$theme ? $theme->image : ""}}'); background-size: cover; float:left;"></div>
                                <div class="row col" style="margin-left: 0px">
                                    <h5 class="col com-md-12">
                                        <a href="{{url('/insider/themes/'.$theme->id)}}">{{$theme ? $theme->name : 'Тема удалена'}}</a>
                                        @if($theme && $theme->isPending())
                                            <span class="badge badge-warning">Ожидает проверки</span>
                                        @elseif($theme && $theme->isBanned())
                                            <span class="badge badge-danger">Забанена</span>
                                        @else
                                            <span class="badge badge-success">Одобрена</span>
                                        @endif
                                    </h5>
                                    <p class="col col-md-12" style="display: flex; justify-content: space-between; align-items: center;">
                                        <small class="disabled">Автор {{$theme && $theme->user ? $theme->user->name : 'Неизвестен'}}</small>
                                        <span>
                                            <a class="btn btn-primary" href="/insider/themes?try={{$theme->id}}">Примерить</a>
                                            @if(\Auth::user()->hasTheme($theme->id))
                                                @if(\Auth::user()->currentTheme() !== null && \Auth::user()->currentTheme()->id == $theme->id)
                                                    <a class="btn btn-danger" href="/insider/themes/{{$theme->id}}/takeoff/">Снять</a>
                                                @else
                                                    <a class="btn btn-success" href="/insider/themes/{{$theme->id}}/wear/">Одеть</a>
                                                @endif
                                            @endif
                                        </span>
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
                @php $theme = $theme_bought->theme; @endphp
                @if($theme)
                    <div class="card" style="min-width: 40%; border-left: 3px solid #28a745;">
                        <div class="card-body">
                            <div class="media">
                                <div class="media-body row">
                                    <div style="width:100px; height:100px; background-image:url('{{$theme->image}}'); background-size:cover; float:left;"></div>
                                    <div class="row col" style="margin-left: 0px">
                                        <h5 class="col com-md-12"><a href="{{url('/insider/themes/'.$theme->id)}}">{{$theme->name}}</a></h5>
                                        <p class="col col-md-12" style="display: flex; justify-content: space-between; align-items: center;">
                                            <small class="disabled">Автор {{$theme->user->name}}</small>
                                            @if(\Auth::user()->hasTheme($theme->id))
                                                <span>
                                                    <a class="btn btn-primary" href="/insider/themes?try={{$theme->id}}">Примерить</a>
                                                    @if(\Auth::user()->currentTheme() !== null && \Auth::user()->currentTheme()->id == $theme->id)
                                                        <a class="btn btn-danger" href="/insider/themes/{{$theme->id}}/takeoff/">Снять</a>
                                                    @else
                                                        <a class="btn btn-success" href="/insider/themes/{{$theme->id}}/wear/">Одеть</a>
                                                    @endif
                                                    @if(\Auth::user()->is_teacher || \Auth::user()->role == 'admin' || $theme->user_id == \Auth::id())
                                                        <a class="btn btn-danger" href="/insider/themes/{{$theme->id}}/delete" onclick="return confirm('Удалить тему?')">Удалить</a>
                                                    @endif
                                                </span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="card" style="min-width: 40%; border-left: 3px solid #dc3545;">
                        <div class="card-body">
                            <div class="media">
                                <div class="media-body row">
                                    <div style="width:100px; height:100px; background-image:url(''); background-color: #f8f9fa; background-size:cover; float:left;"></div>
                                    <div class="row col" style="margin-left: 0px">
                                        <h5 class="col com-md-12">Тема удалена</h5>
                                        <p class="col col-md-12" style="display: flex; justify-content: space-between; align-items: center;">
                                            <small class="disabled">Автор неизвестен</small>
                                            <span><!-- Для удаленных тем кнопки не нужны --></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endsection
