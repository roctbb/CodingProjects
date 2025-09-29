@extends('layouts.left-menu')

@section('title')
    GeekClass: "{{$course->name}}"
@endsection

@section('content')
    <div class="row">
        <div class="col">
            <h2 style="font-weight: 300;"><a class="back-link" href="{{url('/insider/courses/'.$course->id)}}"><i
                            class="icon ion-chevron-left"></i></a> Заблокированные задачи: {{$course->name}}</h2>
        </div>
    </div>

    <div class="row" style="margin-top: 15px;">
        <div class="col">
            @if ($blocked->isEmpty())
                <div class="alert alert-info" role="alert">
                    Заблокированных задач нет.
                </div>
            @else
                @foreach ($blocked as $user_id => $records)
                    @php
                        $student = $records->first()->user;
                        $tasks = $records->unique('task_id');
                    @endphp
                    <div class="card" style="margin-bottom: 15px;">
                        <div class="card-header">
                            <a href="{{ url('/insider/profile/'.$student->id) }}" target="_blank">{{ $student->name }}</a>
                            <span class="badge badge-danger float-right" title="Количество заблокированных задач">{{ $tasks->count() }}</span>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                @foreach ($tasks as $bt)
                                    <li style="padding: 4px 0;">
                                        <a target="_blank"
                                           href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$bt->task_id.'/student/'.$student->id) }}">
                                            {{ optional($bt->task)->name ?? ('Задача #'.$bt->task_id) }}
                                        </a>
                                        <span class="small text-muted">
                                            — {{ $bt->reason ?? 'заблокировано' }}
                                            @if($bt->blocked_at)
                                                ({{ $bt->blocked_at->format('d.m.Y H:i') }})
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
@endsection
