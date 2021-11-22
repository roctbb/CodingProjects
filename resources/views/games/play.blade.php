@extends('layouts.games', ['page' => 1, 'game' => $game])


@section('content')
    <style>
        .row {
            margin: 0;
        }

        .iframe-cont,
        .iframe-cont > * {
            overflow: hidden;
            height: 100%;
            margin: 0;
            padding: 0;

        }

        .iframe-cont {
            height: 450px;
        }

        .iframe {
            height: 100%;
        }

        .heading {
            display: flex;
        }

        #root {

        }
    </style>
    <div id="root" style="word-wrap: break-word;">
        <div style="float:left; margin-right:20px;">
            @include('games/upvoteswidget', ['game' => $game])
        </div>
        <div class="heading" style="float:left">
            <h1>{{$game->title}}</h1>

            @if ($game->getReward() > 0)
                <div class="d-flex" style="margin: 10px;">
                    <img src="{{ url('images/icons/icons8-coins-48.png') }}" width="20" height="20"
                         alt="Rewarded: ">
                    <p>{{$game->getReward()}}</p></div>
            @endif

        </div>
        <div style="clear:both"></div>

        <p style="float:left">
            {!!clean(parsedown($game->description))!!}
        </p>
        <div style="clear:both"></div>

    </div>
    <div class="row">
        <style scoped>
            .card {
                margin: 5px;
            }

            .meta {
                padding-left: 20px;
            }
        </style>
        <div class="card col-md iframe-cont">
            <div class="card-body">
                <iframe class="iframe" onload="this.contentWindow.focus()" src="/games/{{$game->id}}/frame"
                        width="100%" height="450px" frameborder="0"
                        sandbox="allow-same-origin allow-scripts allow-pointer-lock"></iframe>
            </div>
        </div>
    </div>

    @if (\Auth::check())
        <form action="/insider/games/{{$game->id}}/comment" method="POST">
            @csrf

            <div id="comment">
                <h4 style="margin: 20px;"><label for="title">Комментарий:</label></h4>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <div>
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif
                <textarea class="form-control" id="commentArea" rows="10" type="text"
                          name="comment">{{old('comment')}}</textarea>
                <input class="btn btn-primary" type="submit" style="margin:10px 0;" value="Оставить комментарий">
            </div>
        </form>
    @endif

    @foreach($game->comments as $comment)
        <div class="row">
            <div class="card col">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <p class="text-secondary">{{$comment->user->name}}</p>
                        @can('delete', $comment)
                            <a class="nav-link btn btn-danger"
                               style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;"
                               href="/insider/games/comments/{{$comment->id}}/delete"
                               onclick="return confirm('Вы уверены?')"><i
                                        class="ion-close-round"></i></a>
                        @endcan
                    </div>
                    <blockquote>{!!parsedown(clean($comment->comment))!!}</blockquote>
                </div>
            </div>
        </div>
    @endforeach
    <script src="{{asset('js/games.js')}}"></script>
    <script>
        var simplemde_text = new EasyMDE({
            spellChecker: false,
            autosave: true,
            element: document.getElementById("commentArea")
        });
    </script>
@endsection
