<div class="steps-tabs-wrap">
    <ul class="nav nav-tabs steps-tabs @if (count($tasks)==0 || $one_tasker || $quizer) steps-tabs--compact @endif"
        id="pills-tab" role="tablist">
        @if (count($tasks)!=0 && !$zero_theory && !$quizer)
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" id="theory-tab" href="#theory" role="tab"
                   aria-controls="theory" aria-expanded="true">0. Теория</a>
            </li>
        @endif

        @if (!$quizer && (!$one_tasker || !$zero_theory))
            @foreach ($tasks as $key => $task)
                <li class="nav-item">
                    <a class="nav-link task-pill" data-bs-toggle="pill" id="tasks-tab{{$task->id}}"
                       href="#task{{$task->id}}" title="{{ $task->name }}"
                       aria-controls="task{{$task->id}}" aria-expanded="true">
                        <span class="steps-task-pill-title">{{$key+1}}. {{ \Illuminate\Support\Str::limit($task->name, 28) }}</span>
                        @if($task->is_star) <sup>*</sup> @endif
                        @if($task->is_hidden)
                            <sup title="Скрытая задача"><i class="icon fa-solid fa-lock"></i></sup>
                        @endif

                        @if (\Request::is('insider/*'))
                            @if($task->isSubmitted($user->id))
                                @if($task->isFailed($user->id))
                                    <sup><img title="Не выполнено"
                                              src="{{ url('images/icons/icons8-cancel-48.png') }}"
                                              class="steps-task-state-icon"/></sup>
                                @else
                                    @if ($task->isOnCheck($user->id))
                                        <sup><img title="Ожидает проверки"
                                                  src="{{ url('images/icons/icons8-historical-48.png') }}"
                                                  class="steps-task-state-icon"/></sup>
                                    @else
                                        @if($task->isFullDone($user->id))
                                            <sup><img title="Выполнено"
                                                      src="{{ url('images/icons/icons8-checkmark-48.png') }}"
                                                      class="steps-task-state-icon"/></sup>
                                        @else
                                            <sup><img title="Требует доработки"
                                                      src="{{ url('images/icons/icons8-error-48.png') }}"
                                                      class="steps-task-state-icon"/></sup>
                                        @endif
                                    @endif
                                @endif
                            @else
                                @if ($task->getDeadline($course->id))
                                    @php
                                        $exp = $task->getDeadline($course->id)->expiration;
                                    @endphp
                                    @if (\Carbon\Carbon::now()->gt($exp))
                                        <sup><img class="steps-task-state-icon steps-task-state-icon--deadline steps-task-state-icon--danger" title="Дедлайн"
                                                  src="{{ url('images/icons/deadline.png') }}" /></sup>
                                    @elseif (\Carbon\Carbon::now()->addDays(3)->gt($exp))
                                        <sup><img class="steps-task-state-icon steps-task-state-icon--deadline steps-task-state-icon--warning" title="Дедлайн"
                                                  src="{{ url('images/icons/deadline.png') }}" /></sup>
                                    @endif
                                @endif
                            @endif
                        @endif
                    </a>
                </li>
            @endforeach
        @endif

        @if (\Request::is('insider/*') && ($course->teachers->contains($user) || $user->role=='admin'))
            <li class="nav-item steps-action-item ms-lg-auto">
                <a href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/edit')}}"
                   title="Редактировать этап"
                   class="nav-link btn btn-outline-primary steps-action-btn"><i class="icon fa-solid fa-pen-to-square"></i></a>
            </li>
                <li class="nav-item steps-action-item">
                <button type="button" class="nav-link btn btn-outline-primary steps-action-btn"
                        title="Добавить задачу"
                        data-bs-toggle="modal" data-bs-target="#exampleModal">
                    <i class="icon fa-solid fa-circle-plus"></i>
                </button>
            </li>
            <li class="nav-item steps-action-item">
                <a href="{{url('/insider/courses/'.$course->id.'/perform/'.$step->id)}}"
                   title="Режим показа"
                   class="nav-link btn btn-outline-primary steps-action-btn"><i class="icon fa-solid fa-desktop"></i></a>
            </li>
            <li class="nav-item steps-action-item">
                <a class="nav-link btn btn-outline-secondary steps-action-btn"
                   title="Поднять этап"
                   href="#"
                   data-action-url="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/lower')}}"
                   data-action-method="POST"><i class="fa-solid fa-arrow-up"></i></a>
            </li>
            <li class="nav-item steps-action-item">
                <a class="nav-link btn btn-outline-secondary steps-action-btn"
                   title="Опустить этап"
                   href="#"
                   data-action-url="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/upper')}}"
                   data-action-method="POST"><i class="fa-solid fa-arrow-down"></i></a>
            </li>
            <li class="nav-item steps-action-item">
                <a class="nav-link btn btn-outline-danger steps-action-btn"
                   title="Удалить этап"
                   href="#"
                   data-action-url="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/delete')}}"
                   data-action-method="DELETE"
                   data-action-confirm="Вы уверены?"><i class="fa-solid fa-xmark"></i></a>
            </li>
        @endif
    </ul>
</div>
