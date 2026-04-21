@extends('layouts.left-menu')

@section('title')
    {{$student->name}}: {{$task->name}}
@endsection

@section('content')
    <div class="cp-step-review-page">
    <h2><a class="back-link" href="{{url('/insider/courses/'.$course->id.'/assessments')}}"><i
                    class="icon fa-solid fa-chevron-left"></i></a>&nbsp;{{$student->name}}: "{{$task->name}}"</h2>

    <div class="row cp-row-gap-top">
        <div class="col">

            <div class="card">
                <div class="card-header">
                    {{$task->name}}
                    <div class="steps-review-actions d-flex flex-wrap gap-1">
                    @if ($course->teachers->contains(Auth::user()) || Auth::user()->role=='admin')
                        @php $isBlocked = $task->isBlocked($student->id, $course->id); @endphp
                        @if ($isBlocked)
                            <a class="btn btn-warning btn-sm"
                               href="#"
                               data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/unblock/'.$student->id)}}"
                               data-action-method="POST"
                               data-action-confirm="Разблокировать задачу для этого ученика?">Разблокировать</a>
                        @else
                            <a class="btn btn-danger btn-sm"
                               href="#"
                               data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/block/'.$student->id)}}"
                               data-action-method="POST"
                               data-action-confirm="Заблокировать задачу для этого ученика? Все предыдущие баллы будут обнулены.">Заблокировать</a>
                        @endif
                    @endif
                    <a class="btn btn-danger btn-sm"
                       href="#"
                       data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/delete')}}"
                       data-action-method="DELETE"
                       data-action-confirm="Вы уверены?">Удалить</a>
                    <a class="btn btn-primary btn-sm"
                       href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/edit')}}">Редактировать</a>
                    </div>
                </div>
                <div class="card-body markdown">
                    {!! parsedown_math($task->text) !!}

                    <span class="badge text-bg-secondary">Очков опыта: {{$task->max_mark}}</span>
                </div>
            </div>

        </div>

    </div>
    @foreach ($solutions as $key => $solution)
        <div class="row cp-row-gap-y">

            <div class="col">

                <div class="card">
                    <div class="card-header">
                        Дата сдачи: {{ $solution->submitted->format('d.M.Y H:i')}}
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                {!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->text)), false))!!}
                                <br>
                                <br>
                                @if ($solution->mark!=null)
                                    <p>
                                        <span class="badge text-bg-primary">Очков опыта: {{$solution->mark}}</span><br>
                                        <span class="badge text-bg-light">Проверено: {{$solution->checked}}
                                            , {{$solution->teacher->name}}</span>
                                    </p>

                                    <p>
                                        <span class="small">{!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->comment)), false))!!}</span>
                                    </p>
                                @else
                                    <span class="badge text-bg-secondary">Решение еще не проверено</span>
                                @endif
                            </div>
                            <div class="col-md-4">
                                <form method="post"
                                      action="{{url('insider/courses/'.$solution->course_id.'/solution/'.$solution->id)}}">
                                    {{csrf_field()}}
                                    <div class="mb-3">
                                        <input type="number" min="0" step="1" class="form-control form-control-sm mb-2"
                                               id="mark_{{$solution->id}}"
                                               name="mark" placeholder="Очков опыта">
                                        @if ($errors->has('mark'))
                                            <span class="invalid-feedback d-block"><strong>{{ $errors->first('mark') }}</strong></span>
                                        @endif
                                    </div>
                                    <div class="mb-3">
                                        <textarea class="form-control" name="comment"
                                                  placeholder="Комментарий"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">Оценить</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    @endforeach
    </div>
@endsection
