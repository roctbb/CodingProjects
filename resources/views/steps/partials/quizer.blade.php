@if (\Request::is('insider/*') && $quizer)
    @foreach ($tasks as $key => $task)
        @php
            $latestUserSolution = Auth::check() ? $task->latestSolutionForUser(Auth::id()) : null;
            $hasUserSolution = $latestUserSolution !== null;
            $taskType = $task->is_code ? 'code' : ($task->is_quiz ? 'quiz' : 'text');
            $taskTypeLabel = ['code' => 'Код', 'quiz' => 'Квиз', 'text' => 'Ответ'][$taskType];
            $taskTypeIcon = ['code' => 'fas fa-code', 'quiz' => 'fas fa-question-circle', 'text' => 'fas fa-pen'][$taskType];
            $taskStatusClass = '';
            if ($hasUserSolution) {
                $taskStatusClass = is_null($latestUserSolution->mark)
                    ? 'is-review'
                    : ((int) $latestUserSolution->mark >= (int) $task->max_mark ? 'is-complete' : 'is-submitted');
            }
            $taskScoreBadgeClass = $hasUserSolution ? $latestUserSolution->scoreBadgeClass('bg-body') : 'bg-body';
        @endphp
        <div class="gc-card step-task-card step-task-card--{{$taskType}} @if ($task->is_star) is-optional @endif @if ($hasUserSolution) is-submitted @endif overflow-hidden mb-3">
            <div class="step-task-card__header gc-section-header">
                <div class="min-width-0">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="step-task-type step-task-type--{{$taskType}}">
                            <i class="{{$taskTypeIcon}}"></i>{{$taskTypeLabel}}
                        </span>
                        <h4 class="step-task-card__title fw-bold mb-0">{{$task->name}}</h4>
                        <span class="badge rounded-pill bg-body-tertiary">{{$task->max_mark}} XP</span>
                        @if ($task->price > 0)
                            <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                <i class="fas fa-coins me-1"></i>{{$task->price}}
                            </span>
                        @endif
                    </div>
                </div>

                @if ($course->teachers->contains($user) || $user->role=='admin')
                    <div class="step-task-actions d-flex flex-wrap justify-content-lg-end gap-1">
                        <a class="btn btn-outline-danger btn-sm rounded-3"
                           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/delete')}}"
                           title="Удалить задачу"
                           data-confirm="Вы уверены?"><i class="icon ion-android-close"></i></a>
                        <a class="btn btn-outline-secondary btn-sm rounded-3"
                           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/edit')}}"
                           title="Редактировать задачу"><i class="icon ion-android-create"></i></a>
                        <a class="btn btn-outline-secondary btn-sm rounded-3"
                           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/phantom')}}"
                           title="Фантомное решение"><i class="icon ion-ios-color-wand"></i></a>
                        <a class="btn btn-outline-secondary btn-sm rounded-3"
                           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/peer')}}"
                           title="Сгенерировать peer-review"><i class="icon ion-person-stalker"></i></a>
                        <a class="btn btn-outline-secondary btn-sm rounded-3" title="Сдвинуть вправо"
                           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/right')}}"><i class="icon ion-arrow-right-c"></i></a>
                        <a class="btn btn-outline-secondary btn-sm rounded-3" title="Сдвинуть влево"
                           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/left')}}"><i class="icon ion-arrow-left-c"></i></a>
                        @if ($step->previousStep() != null)
                            <a class="btn btn-outline-secondary btn-sm rounded-3" title="Перенести выше"
                               href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/up')}}"><i class="icon ion-arrow-up-c"></i></a>
                        @endif
                        @if ($step->nextStep() != null)
                            <a class="btn btn-outline-secondary btn-sm rounded-3" title="Перенести ниже"
                               href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/down')}}"><i class="icon ion-arrow-down-c"></i></a>
                        @endif
                    </div>
                @endif
            </div>

            <div class="markdown step-task-card__body">
                {!! parsedown_math($task->text) !!}

                @if ($task->is_quiz)
                    <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution')}}" method="POST"
                          class="step-quiz-answer-form"
                          data-check-task data-task-id="{{$task->id}}">
                        {{ csrf_field() }}
                        <label for="text{{$task->id}}" class="form-label fw-semibold mb-0">Ответ</label>
                        <input type="text" name="text" class="form-control form-control-sm rounded-3" id="text{{$task->id}}" />
                        <button type="submit" class="btn btn-success btn-sm rounded-3 fw-semibold">Отправить</button>
                    </form>
                    @if ($errors->has('text'))
                        <span class="text-danger small d-block mb-3"><strong>{{ $errors->first('text') }}</strong></span>
                    @endif
                @endif

                <div class="step-task-status-row {{$taskStatusClass}} @if (!($task->is_quiz && $hasUserSolution)) d-none @endif"
                     data-task-status-row>
                    @if ($task->is_quiz && $hasUserSolution)
                        <span class="badge rounded-pill {{ $taskScoreBadgeClass }} step-task-score" id="TSK_{{$task->id}}">{{$latestUserSolution->mark}} XP</span>
                        <span class="small text-muted" id="TSK_COM_{{$task->id}}">{{$latestUserSolution->comment}}</span>
                    @else
                        <span class="badge rounded-pill bg-body step-task-score" id="TSK_{{$task->id}}"></span>
                        <span class="small text-muted" id="TSK_COM_{{$task->id}}"></span>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
@endif
