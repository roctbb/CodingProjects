<div class="card-body markdown">
    {!! parsedown_math($task->text) !!}

    @php $blocked = isset($course) ? $task->isBlocked(Auth::User()->id, $course->id) : false; @endphp

    @if ($blocked)
        <div class="alert alert-danger" role="alert">
            Задача заблокирована для вас. Новые сдачи запрещены.
        </div>
    @endif

    @if ($task->is_quiz)
        @if (!$blocked)
            <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution')}}"
              method="POST"
              class="form-inline" onsubmit="checkTask(event, {{json_encode($task->id)}})">
                {{ csrf_field() }}
                <label for="text{{$task->id}}"><strong>Ответ:&nbsp;</strong></label>
                <input type="text" name="text" class="form-control form-control-sm"
                id="text{{$task->id}}"/>&nbsp;
                <button type="submit" class="btn btn-success btn-sm">Отправить</button>

            </form>
            @if ($errors->has('text'))
                <br><span
                class="help-block error-block"><strong>{{ $errors->first('text') }}</strong></span>
            @endif
        @endif
    @endif
    <span class="badge badge-secondary">Очков опыта: {{$task->max_mark}}</span>
    @if ($blocked)
        <span class="badge badge-danger" id="TSK_{{$task->id}}">Очков опыта: 0</span>
        <span class="small" id="TSK_COM_{{$task->id}}">Задача заблокирована</span>
    @elseif ($task->is_quiz && $task->solutions()->where('user_id', Auth::User()->id)->count()!=0)
        @php
            $solution = $task->solutions()->where('user_id', Auth::User()->id)->orderBy('id', 'DESC')->get()->first();
        @endphp
        <span class="badge badge-primary" id="TSK_{{$task->id}}">Очков опыта: {{$solution->mark}}</span>
        <span class="small" id="TSK_COM_{{$task->id}}">{{$solution->comment}}</span>
    @else
        <span class="badge badge-primary" id="TSK_{{$task->id}}"></span>
        <span class="small" id="TSK_COM_{{$task->id}}"></span>
    @endif
</div>
