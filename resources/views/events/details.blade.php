@extends('layouts.left-menu')

@section('content')
    <div class="row">
        <div class="col-12">
            <h2 style="margin: 20px;"><a href="{{url('/insider/events')}}"><i class="icon ion-chevron-left"></i>&nbsp;&nbsp;</a>{{ $event->name }}


                @if (\Auth::User()->role=='admin' or $event->userOrgs->contains(Auth::User()->id))
                    <div class="dropdown float-right" style="display: inline;">
                        <button class="btn-options" type="button" data-toggle="dropdown"
                                aria-haspopup="true"
                                aria-expanded="false">
                            <i class="material-icons">more_vert</i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item"
                               href="{{ url('/insider/events/'.$event->id.'/edit') }}">Изменить</a>
                            @if (!$event->coins_delivered and (\Auth::user()->role == 'admin' or \Auth::user()->role == 'teacher'))
                                <a class="dropdown-item"
                                   href="{{ url('/insider/events/'.$event->id.'/prize') }}">Начислить премию</a>
                            @endif
                            <div class="dropdown-divider"></div>


                            <a class="dropdown-item text-danger" onclick="return confirm('Вы уверены?')"
                               href="{{ url('/insider/events/'.$event->id.'/delete') }}">Удалить</a>
                        </div>
                    </div>
                @endif</h2>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col col-xl-8">

                            <h5>Начало
                                события: {{\Carbon\Carbon::parse($event->date)->format("Y.m.d в h:m")}}</h5>

                            <p style="margin-top: 15px;">@parsedown($event->text)</p>

                            <p>
                                <b>Организаторы: </b>
                                @foreach($event->userOrgs as $user)
                                    <div class="row" style="margin-bottom: 10px;">
                                        <div class="col" style="width: 85px; max-width: 85px;">
                                            @if ($user->image!=null)
                                                <div class="mr-3 rounded-circle img-circle"
                                                     style='background-image: url("{{url('/media/'.$user->image)}}");'>
                                                </div>
                                            @else
                                                <div class="mr-3 rounded-circle img-circle"
                                                     style='background-image: url("http://api.adorable.io/avatars/256/{{$user->id}}");'>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="col-auto"
                                             style="width: calc(100% - 100px); max-width: calc(100% - 100px)">
                                            @if (\Auth::check())
                                                <h5>
                                                    <a href="{{url('/insider/profile/'.$user->id)}}">{{ $user->name }}</a>
                                                </h5>
                                            @else
                                                <h5>{{ $user->name }}</h5>
                            @endif
                            <p><a tabindex="0" data-toggle="popover" data-trigger="focus" title="Ранги"
                                  data-html="true"
                                  data-content="{{\App\Rank::getRanksListHTML($user->rank())}}"><span
                                            style="font-size: 13px;" class="badge badge-pill badge-success"><i
                                                class="icon ion-ios-arrow-up"></i> {{$user->rank()->name}}</span></a>

                                @if ($user->is_trainee)
                                    <span style="font-size: 13px;" class="badge badge-pill badge-info">Стажер</span>
                                @endif
                                @if ($user->is_teacher)
                                    <span style="font-size: 13px;"
                                          class="badge badge-pill badge-info">Преподаватель</span>
                                @endif</p>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="col col-xl-4">
                    @if ($event->participants()->where('user_id', \Auth::id())->count() > 0)
                        <a role="button" class="btn btn-danger" style="width: 100%;"
                           href={{"/insider/events/$event->id/left"}}>
                            Я не иду
                        </a>
                    @else
                        <a role="button" class="btn btn-primary" style="width: 100%;"
                           href={{"/insider/events/$event->id/go"}}>
                            Я иду!
                        </a>
                    @endif
                    <ul class="avatars" style="margin-top:10px; margin-bottom:10px;">
                        @foreach($event->participants as $participant)
                            <li>
                                <a href="{{ url('insider/profile/'.$participant->id) }}" data-toggle="tooltip">
                                    @if ($participant->image!=null)
                                        <img alt="Image" src="{{url('/media/'.$participant->image)}}"
                                             class="avatar"/>
                                    @else
                                        <img alt="Image"
                                             src="http://api.adorable.io/avatars/256/{{$participant->id}}"
                                             class="avatar"/>
                                    @endif
                                </a>
                            </li>
                        @endforeach

                    </ul>
                    @if ($event->type == 'in')
                        <p>
                            <b>Тип: </b> внутреннее
                        </p>
                    @else
                        <p>
                            <b>Тип: </b> внешнее
                        </p>

                    @endif
                    <p>
                        <b>Где: </b> {{$event->location}}
                    </p>
                    @if ($event->site)
                        <p>
                            <b>Сайт: </b> {{$event->site}}
                        </p>
                    @endif

                    <p>
                        <b>Участники:</b> {{count($event->participants)}}
                        / {{$event->max_people ? $event->max_people:"&infin;"}}

                    </p>

                    @if ($event->skills)
                        <p>
                            <b>Ограничения: </b> {{$event->skills}}
                        </p>
                    @endif

                    <ul>
                        @foreach($users as $user)
                            @if($event->participants->contains($user->id))
                                <li><a href="{{url('/insider/profile/'.$user->id)}}">{{$user->name}}</a></li>
                            @endif
                        @endforeach
                    </ul>

                </div>
            </div>
        </div>
    </div>
    </div>

    <div class="col-md-12">
        <div style="margin-top: 15px">
            <h5>Комментарии:</h5>
        </div>

        @foreach($event->comments as $comment)


            <div class="card">
                <div class="card-body row">
                    <div class="col-md-12">
                        <div class="float-left" style="margin-left:5px">
                            <b>{{$comment->user->name}}</b>
                            {{\Carbon\Carbon::parse($comment->created_at)->diffForHumans(\Carbon\Carbon::now())}}
                        </div>
                        <div class="float-right">
                            @if($comment->user_id == Auth::User()->id or \Auth::user()->role=="teacher" or \Auth::user()->role=="admin")
                                <a href="{{"/insider/events/$event->id/delete_comm/$comment->id"}}"
                                   onclick="return confirm('Вы уверены?')">
                                    <i class="material-icons">clear</i>
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body col-md-12">
                        {{$comment->text}}
                    </div>
                </div>
            </div>

        @endforeach

        <form method="POST" action="{{url('insider/events/'.$event->id)}}">
            {{csrf_field()}}
            <textarea class="form-control" rows="5" style="margin-bottom:10px" name="text"></textarea>
            <input role="button" class="btn btn-success" value="Отправить комментарий" type="submit"/>
        </form>
    </div>
    </div>

    <script src="{{asset('js/forum.js')}}"></script>
@endsection
