@extends('layouts.left-menu')

@section('title')
    GeekClass
@endsection

@section('content')
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <h2> Программы</h2>
        </div>
        <div class="col">
            <a class="float-right btn btn-success btn-sm" href="{{url('/insider/programs/create/')}}"><i
                        class="icon ion-plus-round" style="color: white;"></i>&nbsp;Создать</a>

        </div>
    </div>

    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <ul>
                @foreach($programs as $program)
                    <li><a href="{{url('insider/programs/'.$program->id)}}">{{$program->name}}</a> </li>
                @endforeach
            </ul>
        </div>

    </div>




@endsection
