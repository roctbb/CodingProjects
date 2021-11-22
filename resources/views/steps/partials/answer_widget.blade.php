<div class="card-body markdown">
    @parsedown($task->text)

    @if ($task->is_quiz)
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
    <span class="badge badge-secondary">Очков опыта: {{$task->max_mark}}</span>
    @if ($task->is_quiz && $task->solutions()->where('user_id', Auth::User()->id)->count()!=0)
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
