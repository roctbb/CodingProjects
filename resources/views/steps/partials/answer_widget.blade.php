                <div class="markdown step-task-card__body">
                    {!! parsedown_math($task->text) !!}

    @php
        $blocked = isset($course) ? $task->isBlocked(Auth::id(), $course->id) : false;
        $latestUserSolution = $task->latestSolutionForUser(Auth::id());
        $hasUserSolution = $latestUserSolution !== null;
        $taskScoreBadgeClass = $hasUserSolution ? $latestUserSolution->scoreBadgeClass('bg-body') : 'bg-body';
    @endphp

    @if ($blocked)
        <div class="step-task-note step-task-note--danger bg-danger-subtle text-danger-emphasis mb-3" role="note">
            <span class="flex-shrink-0"><i class="fas fa-lock"></i></span>
            <div class="min-width-0">
                <strong class="d-block">Задача заблокирована</strong>
                <span class="small">Новые сдачи запрещены.</span>
            </div>
        </div>
    @endif

    @if ($task->is_quiz)
        @if (!$blocked)
            <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution')}}"
              method="POST"
              class="step-quiz-answer-form" data-check-task data-task-id="{{$task->id}}">
                {{ csrf_field() }}
                <label for="text{{$task->id}}" class="form-label fw-semibold mb-0">Ответ</label>
                <input type="text" name="text" class="form-control form-control-sm rounded-3"
                id="text{{$task->id}}"/>
                <button type="submit" class="btn btn-success btn-sm rounded-3 fw-semibold">Отправить</button>

            </form>
            @if ($errors->has('text'))
                <span class="text-danger small d-block mb-3"><strong>{{ $errors->first('text') }}</strong></span>
            @endif
        @endif
    @endif
    <div class="step-task-status-row @if (!$blocked && !($task->is_quiz && $hasUserSolution)) d-none @endif"
         data-task-status-row>
        @if ($blocked)
            <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle fw-semibold" id="TSK_{{$task->id}}">0 XP</span>
            <span class="small text-muted" id="TSK_COM_{{$task->id}}">Задача заблокирована</span>
        @elseif ($task->is_quiz && $hasUserSolution)
            <span class="badge rounded-pill {{ $taskScoreBadgeClass }} step-task-score" id="TSK_{{$task->id}}">{{$latestUserSolution->mark}} XP</span>
            <span class="small text-muted" id="TSK_COM_{{$task->id}}">{{$latestUserSolution->comment}}</span>
        @else
            <span class="badge rounded-pill bg-body step-task-score" id="TSK_{{$task->id}}"></span>
            <span class="small text-muted" id="TSK_COM_{{$task->id}}"></span>
        @endif
    </div>
</div>
