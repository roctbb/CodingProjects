@extends('layouts.left-menu')
@section('head')
    @if ($is_try)
        <link rel="stylesheet" href="/insider/themes/{{$theme->id}}/css"></style>
        <script src="/insider/themes/{{$theme->id}}/js"></script>
    @endif
@endsection
@section('content')
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col">
                    <div class="d-flex align-items-center justify-content-between">
                        <h3 style="margin-top: 10px;">{{$theme->name}}</h3>    
                        <div>
                            @if(!\Auth::user()->hasTheme($theme->id))
                                <a href="/insider/themes/{{$theme->id}}/buy" class="btn btn-warning">
                                    @if($theme->price > 0)
                                        Купить за {{$theme->price}}GK
                                    @else
                                        Получить бесплатно
                                    @endif
                                </a>
                            @endif
 
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
                        @endif                        
                            <a href="/insider/themes/{{$theme->id}}?try=true" class="btn btn-primary">Попробовать</a>
                        @if(\Auth::user()->is_teacher || \Auth::user()->role == 'admin')
                            <a href="/insider/themes/{{$theme->id}}/edit" class="btn btn-success"><i class="ion ion-edit"></i></a>   
                            @endif
                        </div> 

                        </div>
                        <hr>
                    <p>@parsedown($theme->description)</p>
                </div>

            </div>
        </div>
    </div>
@endsection
