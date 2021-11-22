@extends('layouts.left-menu')

@section('content')
    <h2><a class="back-link" href="{{url('/insider/courses/'.$course->id.'/assessments')}}"><i
                    class="icon ion-chevron-left"></i></a>&nbsp;{{$student->name}}: "{{$task->name}}"</h2>

    <div class="row" style="margin-top: 15px;">
        <div class="col">

            <div class="card">
                <div class="card-header">
                    {{$task->name}}
                    <a class="float-right btn btn-danger btn-sm"
                       href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/delete')}}"  onclick="return confirm('Вы уверены?')">Удалить</a>
                    <a style="margin-right: 5px;" class="float-right btn btn-success btn-sm"
                       href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/edit')}}">Редактировать</a>
                </div>
                <div class="card-body markdown">
                    @parsedown($task->text)

                    <span class="badge badge-secondary">Очков опыта: {{$task->max_mark}}</span>
                </div>
            </div>

        </div>

    </div>
    @foreach ($solutions as $key => $solution)
        <div class="row" style="margin-top: 15px; margin-bottom: 15px;">

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
                                <form class="form-horizontal" method="post"
                                      action="{{url('insider/courses/'.$solution->course_id.'/solution/'.$solution->id)}}">
                                    {{csrf_field()}}
                                    <div class="form-group">
                                        <input type="text" class="form-control form-control-sm mb-2 mr-sm-2 mb-sm-0"
                                               id="mark"
                                               name="mark" placeholder="Очков опыта">
                                        @if ($errors->has('mark'))
                                            <span class="help-block error-block"><strong>{{ $errors->first('mark') }}</strong></span>
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
