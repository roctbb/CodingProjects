@extends('layouts.left-menu')

@section('heading', 'Наградить')

@section('content')

<form action="/insider/projects/{{$project->id}}/reward" method="POST">
    @csrf
    
    <style scoped>
        h4
        {
            margin: 20px 0;
        }
    </style>
    @if ($errors->any())
        <div class="alert alert-danger">
            <div>
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <div style="margin-bottom: 5px;">
        <h4><label for="title">Сумма:</label></h4>
        <input class="form-control col" type="number" name="reward" id="reward" value="{{old('reward')}}">
    </div>
    <div style="margin-bottom: 5px;">
        <h4><label for="title">Комментарий:</label></h4>
        <input class="form-control col" type="text" name="comment" id="comment" value="{{old('comment')}}">
    </div>
    


    <div class="row" style="padding: 10px;">
        <input type="submit" name="submit" id="submit" value="Наградить" class="col btn btn-primary">
    </div>
@endsection