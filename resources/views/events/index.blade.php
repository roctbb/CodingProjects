@extends('layouts.left-menu')

@section('content')
    <div class="row">
        <div class="col">
            <h2>События</h2>
        </div>
        <div class="col">
            <ul class="nav nav-tabs nav-fill">
                <li class="nav-item">
                    <a class="nav-link active" id="active-tab" data-toggle="tab" href="#future" role="tab"
                       aria-controls="active" aria-selected="true">Будущие</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#past" role="tab" aria-controls="active"
                       aria-selected="true">Прошедшие</a>
                </li>
                <li class="nav-item">
                    <a class="btn btn-success btn-sm nav-link" style="color: white;" href="/insider/events/create"><i
                                class="icon ion-plus-round" style="color: white;"></i>&nbsp;Создать</a>
                </li>
            </ul>
        </div>
    </div>
    <div class="tab-content" style="margin-top: 15px;">
        <div class="row">
            <div class="tab-pane fade show active card-deck" id="future" style="width:100%;">
                @foreach ($events as $event)
                    <div class="card card-team @if ($event->participants->contains(\Auth::user())) bg-success @endif" style="min-width: 280px;">
                        <div class="card-body">
                            @if (\Auth::User()->role=='admin' or $event->userOrgs->contains(Auth::User()->id))
                                <div class="dropdown card-options">
                                    <button class="btn-options" type="button" data-toggle="dropdown"
                                            aria-haspopup="true"
                                            aria-expanded="false">
                                        <i class="material-icons">more_vert</i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a class="dropdown-item" href="{{ url('/insider/events/'.$event->id.'/edit') }}">Изменить</a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger" onclick="return confirm('Вы уверены?')" href="{{ url('/insider/events/'.$event->id.'/delete') }}">Удалить</a>
                                    </div>
                                </div>
                            @endif
                            <div class="card-title">
                                <a href="{{ url('/insider/events/'.$event->id) }}">
                                    <h5 data-filter-by="text">{{$event->name}}</h5>
                                </a>
                                <p class="card-text">{{$event->short_text}}</p>
                            </div>
                            <ul class="avatars">
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

                            <div class="card-meta d-flex justify-content-between" style="margin-top: 36px;">
                                <div class="d-flex align-items-center">
                                    <i class="material-icons mr-1 text-small">people</i>
                                    <span class="text-small">{{count($event->participants)}}/{{$event->max_people ? $event->max_people:"&infin;"}} @if ($event->participants->contains(\Auth::user())) <strong>(вы записаны)</strong> @endif</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="material-icons mr-1 text-small">near_me</i>
                                    <span class="text-small">{{$event->location}}</span>
                                </div>
                                <span class="text-small SPAN-filter-by-text"><strong><i
                                                class="material-icons mr-1 text-small">calendar_today</i> {{ $event->date->day }} {{$event->date->localeMonth}}, {{ $event->date->format('H:i') }}</strong></span>

                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="tab-pane fade show card-deck" id="past" style="width:100%;">
                @foreach ($old_events as $event)
                    <div class="card card-team @if ($event->participants->contains(\Auth::user())) bg-success @endif" style="min-width: 280px;">
                        <div class="card-body">
                            @if (\Auth::User()->role=='admin' or $event->userOrgs->contains(Auth::User()->id))
                                <div class="dropdown card-options">
                                    <button class="btn-options" type="button" data-toggle="dropdown"
                                            aria-haspopup="true"
                                            aria-expanded="false">
                                        <i class="material-icons">more_vert</i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a class="dropdown-item" href="{{ url('/insider/events/'.$event->id.'/edit') }}">Изменить</a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger" onclick="return confirm('Вы уверены?')" href="{{ url('/insider/events/'.$event->id.'/delete') }}">Удалить</a>
                                    </div>
                                </div>
                            @endif
                            <div class="card-title">
                                <a href="{{ url('/insider/events/'.$event->id) }}">
                                    <h5 data-filter-by="text">{{$event->name}}</h5>
                                </a>
                                <p class="card-text">{{$event->short_text}}</p>
                            </div>
                            <ul class="avatars">
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

                            <div class="card-meta d-flex justify-content-between" style="margin-top: 36px;">
                                <div class="d-flex align-items-center">
                                    <i class="material-icons mr-1 text-small">people</i>
                                    <span class="text-small">{{count($event->participants)}}/{{$event->max_people ? $event->max_people:"&infin;"}} @if ($event->participants->contains(\Auth::user())) <strong>(вы записаны)</strong> @endif</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="material-icons mr-1 text-small">near_me</i>
                                    <span class="text-small">{{$event->location}}</span>
                                </div>
                                <span class="text-small SPAN-filter-by-text"><strong><i
                                                class="material-icons mr-1 text-small">calendar_today</i> {{ $event->date->day }} {{$event->date->localeMonth}}, {{ $event->date->format('H:i') }}</strong></span>

                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @include('events.event_likes_script')
@endsection
