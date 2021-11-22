@extends('layouts.left-menu')

@section('title')
    GeekClass
@endsection

@section('content')
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <h2>Шкала {{ $scale->name }}</h2>
        </div>
        <div class="col">
            <a class="float-right btn btn-success btn-sm" href="{{url('/insider/scales/'.$scale->id.'/edit/')}}"><i
                        class="icon ion-edit"></i>&nbsp;Изменить</a>

            <a class="float-right btn btn-success btn-sm" style="margin-right: 5px;" href="{{url('/insider/scales/'.$scale->id.'/results/add')}}"><i
                        class="icon ion-plus"></i>&nbsp;Добавить результат</a>
        </div>
    </div>
    <div class="row" style="margin-top: 15px;">
        <div class="col-12">
            <p>{{$scale->description}}</p>
            @foreach([[8, 'success'],[6, 'primary'],[4, 'info']] as $level)
                @foreach($scale->results->where('level', $level[0]) as $result)
                    <div class="card">

                        <div class="card-body">
                            <div class="row">
                                <div class="col-1">
                                    <h5><span class="badge badge-{{$level[1]}}">{{$level[0] / 2}}.0</span></h5>
                                </div>
                                <div class="col-6" style="border-right: 1px solid lightblue">
                                    <h5 class="card-title">{{ $result->name }}</h5>
                                    <p>{{ $result->description }}</p>
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            Действия
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="{{url('/insider/scales/'.$scale->id.'/results/'.$result->id.'/tasks/add')}}" class="dropdown-item">
                                                <i class="icon ion-android-add"></i> Добавить задачу</a>
                                            <a href="{{url('/insider/scales/'.$scale->id.'/results/'.$result->id.'/edit')}}" class="dropdown-item">
                                                <i class="icon ion-edit"></i> Изменить</a>
                                            <a href="{{url('/insider/scales/'.$scale->id.'/results/'.$result->id.'/delete')}}" class="dropdown-item" onclick="return confirm('Вы уверены?')"><i
                                                        class="icon ion-android-close"></i> Удалить</a>
                                        </div>
                                    </div>


                                </div>
                                <div class="col-5">
                                    <ul class="list-group">
                                        @foreach($result->tasks as $task)
                                            <li class="list-group-item">
                                                <small><a href="#" data-toggle="modal" data-target="#task{{$task->id}}"> {{$task->name}}</a> <span class="float-right text-muted">@if ($task->is_demo)
                                                            демонстрационное @endif @if ($task->is_training) тренировочное @endif</span></small>
                                            </li>
                                        @endforeach
                                    </ul>

                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    @foreach($scale->results as $result)
        @foreach($result->tasks as $task)
            <div class="modal fade modal" id="task{{$task->id}}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{$task->name}}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <strong>Условие</strong>
                            @parsedown($task->text)

                            @if ($task->solution)
                                <strong>Решение</strong>
                                @parsedown($task->solution)
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Закрыть</button>
                            <a class="btn btn-sm btn-primary" href="{{url('/insider/scales/'.$scale->id.'/results/'.$result->id.'/tasks/'.$task->id.'/edit')}}">Изменить</a>
                            <a class="btn btn-sm btn-danger" href="{{url('/insider/scales/'.$scale->id.'/results/'.$result->id.'/tasks/'.$task->id.'/delete')}}" onclick="confirm('Вы уверены?')">Удалить</a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endforeach

@endsection
