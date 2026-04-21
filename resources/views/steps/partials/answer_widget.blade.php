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
              class="d-flex flex-column flex-sm-row gap-2 align-items-sm-center steps-answer-form" onsubmit="checkTask(event, {{json_encode($task->id)}})">
                {{ csrf_field() }}
                <label for="text{{$task->id}}" class="mb-0"><strong>Ответ:</strong></label>
                <input type="text" name="text" class="form-control form-control-sm"
                id="text{{$task->id}}"/>
                <button type="submit" class="btn btn-primary btn-sm">Отправить</button>

            </form>
            @if ($errors->has('text'))
                <span class="invalid-feedback d-block mt-2"><strong>{{ $errors->first('text') }}</strong></span>
            @endif
        @endif
    @endif
    <span class="badge text-bg-secondary">Очков опыта: {{$task->max_mark}}</span>
    @if ($blocked)
        <span class="badge text-bg-danger" id="TSK_{{$task->id}}">Очков опыта: 0</span>
        <span class="small" id="TSK_COM_{{$task->id}}">Задача заблокирована</span>
    @elseif ($task->is_quiz && $task->solutions()->where('user_id', Auth::User()->id)->count()!=0)
        @php
            $solution = $task->solutions()->where('user_id', Auth::User()->id)->orderBy('id', 'DESC')->get()->first();
        @endphp
        <span class="badge text-bg-primary" id="TSK_{{$task->id}}">Очков опыта: {{$solution->mark}}</span>
        <span class="small" id="TSK_COM_{{$task->id}}">{{$solution->comment}}</span>
    @else
        <span class="badge text-bg-primary" id="TSK_{{$task->id}}"></span>
        <span class="small" id="TSK_COM_{{$task->id}}"></span>
    @endif
</div>
