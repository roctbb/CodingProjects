@extends('layouts.left-menu')

@section('title')
    {{$student->name}}: {{$task->name}}
@endsection

@section('content')
    <h2><a class="d-inline mr-2" href="{{url('/insider/courses/'.$course->id.'/assessments')}}"><i
                    class="icon ion-chevron-left"></i></a>&nbsp;{{$student->name}}: "{{$task->name}}"</h2>

    <div class="row mt-3">
        <div class="col">

            <div class="card">
                <div class="card-header">
                    {{$task->name}}
                    @if ($course->teachers->contains(Auth::user()) || Auth::user()->role=='admin')
                        @php $isBlocked = $task->isBlocked($student->id, $course->id); @endphp
                        @if ($isBlocked)
                            <a class="float-right btn btn-warning btn-sm mr-1"
                               href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/unblock/'.$student->id)}}"
                               data-confirm="Разблокировать задачу для этого ученика?">Разблокировать</a>
                        @else
                            <a class="float-right btn btn-danger btn-sm mr-1"
                               href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/block/'.$student->id)}}"
                               data-confirm="Заблокировать задачу для этого ученика? Все предыдущие баллы будут обнулены.">Заблокировать</a>
                        @endif
                    @endif
                    <a class="float-right btn btn-danger btn-sm"
                       href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/delete')}}" data-confirm="Вы уверены?">Удалить</a>
                    <a class="float-right btn btn-success btn-sm mr-1"
                       href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/edit')}}">Редактировать</a>
                </div>
                <div class="card-body markdown">
                    {!! parsedown_math($task->text) !!}

                    <span class="badge badge-secondary">Очков опыта: {{$task->max_mark}}</span>
                </div>
            </div>

        </div>

    </div>
    @foreach ($solutions as $key => $solution)
        <div class="row my-3">

            <div class="col">

                <div class="card">
                    <div class="card-header">
                        Дата сдачи: {{ $solution->submitted->format('d.M.Y H:i')}}
                        <div class="float-right">

                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                {!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->text)), false))!!}
                                <br>
                                <br>
                                @if ($solution->mark!=null)
                                    <p>
                                        <span class="badge badge-primary">Очков опыта: {{$solution->mark}}</span><br>
                                        <span class="badge badge-light">Проверено: {{$solution->checked}}
                                            , {{$solution->teacher->name}}</span>
                                    </p>

                                    <p>
                                        <span class="small">{!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->comment)), false))!!}</span>
                                    </p>
                                @else
                                    <span class="badge badge-secondary">Решение еще не проверено</span>
                                @endif
                            </div>
                            <div class="col-md-4">
                                <form method="post"
                                      action="{{url('insider/courses/'.$solution->course_id.'/solution/'.$solution->id)}}">
                                    {{csrf_field()}}
                                    <div class="form-group">
                                        <input type="text" class="form-control form-control-sm mb-2 mr-sm-2 mb-sm-0"
                                               id="mark"
                                               name="mark" placeholder="Очков опыта">
                                        @if ($errors->has('mark'))
                                            <span class="text-danger d-block"><strong>{{ $errors->first('mark') }}</strong></span>
                                        @endif
                                    </div>
                                    <div class="form-group">
                                        <textarea class="form-control" name="comment"
                                                  placeholder="Комментарий"></textarea>

                                    </div>
                                    <button type="submit" class="btn btn-success btn-sm">Оценить</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    @endforeach
@endsection
