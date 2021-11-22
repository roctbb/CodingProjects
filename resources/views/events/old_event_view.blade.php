@extends('layouts.left-menu')

@section('content')
    @extends('events.event_layout')
    <br>
    <div class="row" style = "margin-top: -30px">
        <div class="col" style="margin: -10px">
            <div class="float-left">
                <a class="nav-link" href="{{url('/insider/events')}}" style="color:black" ><h2>События</h2></a>
            </div>
            <div class="text-center" style="margin-right: 10px">
                <h2 class="nav-link" style="color: blue">Архив событий</h2>
            </div>
        </div>
    </div>
    <div class="row" style = "margin-top: 10px">
        <div class="col-md-8">
            @foreach($events as $event)
                    <div class="card-group ev">
                        <div class="card" style="border: none;">
                            <div class="row" style="display: flex; justify-content: space-between;">
                                <div class="text-center">
                                    <h4><b>{{$event->name}}, ({{$event->type}})</b></h4>
                                </div>
                                @include('event_likes', ['event' => $event])
                                <div style="margin-right:15px">
                                    <b>{{$event->date}}</b>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <div class="card-body">
                                            {{$event->short_text}}
                                        <div class="float-right">
                                            <a href="{{url('/insider/events/'.$event->id)}}" style="margin-top: -5px"
                                               class = "btn btn-primary">Перейти к событию</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
            @endforeach
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Теги:</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        {{ csrf_field() }}
                        <div class="form-group form-check">
                            @foreach($tags as $tag)
                                @if($tag->id !=1)
                                    <div class="form-check">
                                        <input type="checkbox" style="margin-left:5px" name="sel_tags[]" class="form-check-input" value="{{$tag->id}}" id="{{$tag->id}}"
                                               @if((in_array($tag->id, $s_tags)) && ($s_tags[0] != 1)) checked @endif>
                                        <label for="{{$tag->id}}" class="form-check-label" style="margin-left:2px" >{{$tag->name}}</label>
                                    </div>
                                @endif
                            @endforeach
                            <br>
                            <div class="float-left">
                                <input type="submit" value="Применить" class = "btn btn-success">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
        </div>
    @include('events.event_likes_script')
@endsection