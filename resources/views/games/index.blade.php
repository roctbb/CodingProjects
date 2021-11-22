@extends('layouts.games', ['page' => 0])

@section('heading')
    Игры
@endsection

@section('content')
    <style>
        .card-deck {
            width: 100%;
        }

        .meta {
            padding-right: 10px !important;
        }

        .card {
            margin: 10px !important;

        }

        .col-lg-6 {
            padding: 0;
            margin: 0;
        }


        .row {
            margin: 0;
            padding: 0;
        }
    </style>
    <div class="row">
        <h1>Игры</h1>

    </div>
    <div class="row card-deck" id="root">
        @foreach($games as $game)

            <div class="col-lg-6 flex-grow-1">
                <div class="card">

                    <div class="card-body">
                        <div class="d-flex">
                            <div>
                                <div style="float:left; margin-right:10px;">
                                    @include('games/upvoteswidget', ['game' => $game])
                                </div>
                            </div>
                            <div style="flex:1;">

                                <h4>{{ $game->title }}</h4>
                                <div class="d-flex justify-content-between">
                                    {{-- <p class="text-secondary">{{ str_limit($game->description, 100) }}</p> --}}
                                    <div class="row-sm">

                                    </div>
                                </div>

                                <div style="margin-top:5px" class="row">

                                    <p class="meta">Автор: {{$game->user->name}}</p>

                                    @if ($game->getReward() > 0)
                                        <img src="{{ url('images/icons/icons8-coins-48.png') }}" width="20"
                                             height="20"
                                             alt="Rewarded: ">
                                        <p class="meta">{{$game->getReward()}}</p>
                                    @endif
                                </div>
                                <div class="d-flex">
                                    @if (\Auth::check())
                                        <a class="meta" href="/insider/games/{{$game->id}}">Играть</a>
                                        @if (\Auth::id()==$game->user->id || \Auth::user()->role == 'teacher')
                                            <a href="/insider/games/{{$game->id}}/ide" class="meta">Изменить</a>
                                        @endif
                                        @if (\Auth::id()!=$game->user->id || \Auth::user()->role == 'teacher')
                                            <a href="/insider/games/{{$game->id}}/reward" class="meta">Наградить</a>
                                        @endif
                                    @else
                                        <a class="meta" href="/games/{{$game->id}}">Играть</a>
                                    @endif
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
        @endforeach
    </div>
    <script src="{{asset('js/games.js')}}"></script>

@endsection
