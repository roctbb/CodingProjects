@extends('layouts.left-menu')

@section('content')
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <h2>Вопросы и ответы</h2>
        </div>
    </div>
    <div class="row" style="margin-top: 15px;">

        <div class="col-12">
            <form>
                <div class="form-row">
                    <div class="col-12 col-md-9">
                        <input style="border-radius: 0;" type="text" class="form-control" name="q"
                               placeholder="Как работает...">
                    </div>
                    <div class="col-auto">
                        <input type="submit" class="btn btn-success" value="Найти" style="margin-right: 5px;"/>
                        <a class="float-right btn btn-success" href="{{url('/insider/forum/create/')}}"><i
                                    class="icon ion-plus-round" style="color: white;"></i>&nbsp;Задать вопрос</a>
                    </div>

                </div>
            </form>

            @if ($q != "")
                <p style="margin-bottom: 5px; margin-top: 5px;">Показаны результаты по запросу:
                    <strong>{{ $q }}</strong>. <a href="{{'/insider/forum'}}">Все вопросы</a>.</p>
            @endif

            @if ($tag != "")
                <p style="margin-bottom: 5px; margin-top: 5px;">Показаны результаты по тэгу
                    <strong>{{ $tag->name }}</strong>. <a href="{{'/insider/forum'}}">Все вопросы</a>.</p>
            @endif
        </div>
    </div>
    <div class="row" style="margin-top: 15px;">

        <div class="col-12">
            <div class="card-deck">
                <div class="card" style="min-width:35% !important; border-left: 3px solid #28a745;">
                    <div class="card-body">
                        <p style="margin-top: 10px;">В качестве эксперимента, мы добавляем в GeekClass новый раздел -
                            ответы. Здесь вы можете задавать вопросы на любые темы, связанные с программированием (или
                            любыми учебными и неучебными темами) и получать на них ответы от других участников и
                            преподавателей так же, как это делается на stackoverflow.com.</p>
                        <p>За полезные ответы (и вопросы!) вы можете голосовать, а их авторы получат GC за 3 набранных
                            голоса. А если ответ написан неточно или грубо, вы, наоборот, можете понизить его рейтинг.
                            Вопросы и ответы также будут учтены на общей шкале опыта.</p>
                        <p><strong>Очень надеемся что вместе нам удастся создать сообщество, в котором каждый сможет
                                поделиться опытом и узнать что-то новое!</strong></p>

                    </div>
                </div>

                @foreach ($threads as $thread)
                    <div class="card"
                         style="min-width:35% !important; border-left: 3px solid #28a745;">

                        <div class="card-body" style="background-color: rgba(255,255,255,0.9);">
                            <div class="row">
                                <div class="col">
                                    <h5 style="margin-top: 15px; font-weight: 300;"
                                        class="card-title">
                                        <a href="{{url('/insider/forum/'.$thread->id)}}">{{$thread->name}}</a>
                                    </h5>
                                    <p>
                                        @foreach($thread->tags as $tag)
                                            <span class="badge badge-secondary badge-light"
                                                  style="font-size: 1.2rem;"><a
                                                        href="{{url('/insider/forum?tag='.$tag->name)}}">{{$tag->name}}</a></span>
                                        @endforeach
                                    </p>
                                    <p style="margin-bottom: 0;">
                                        <small class="text-muted">Задан: {{$thread->created_at->format('H:i d.m.Y')}}
                                            , {{$thread->user->name}}</small>
                                    </p>
                                </div>
                                <div class="col">
                                    <table class="table" border="0">
                                        <tr>
                                            <td style="border: 0;">
                                                <h4 class="lead"
                                                    style="text-align: center;">{{$thread->posts()->count()-1}}<br>
                                                    <small>ответов</small>
                                                </h4>
                                            </td>
                                            <td style="border: 0;">
                                                <h4 class="lead"
                                                    style="text-align: center;">{{$thread->posts->first()->getVotes()}}
                                                    <br>
                                                    <small>голосов</small>
                                                </h4>
                                            </td>
                                            <td style="border: 0;">
                                                <h4 class="lead" style="text-align: center;">{{$thread->visits}}<br>
                                                    <small>просмотров</small>
                                                </h4>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>


                        </div>
                    </div>
                @endforeach
            </div>
        </div>


    </div>

@endsection
