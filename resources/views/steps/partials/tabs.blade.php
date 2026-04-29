<div class="row">
    <div class="col">
        <ul class="nav nav-pills justify-content-end"
            id="pills-tab" role="tablist">
            @if (count($tasks)!=0 && !$zero_theory && !$quizer)
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="pill" id="theory-tab" href="#theory" role="tab"
                       aria-controls="theory" aria-expanded="true">0. Теория</a>
                </li>
            @endif
            @if (!$quizer && (!$one_tasker || !$zero_theory))
                @foreach ($tasks as $key => $task)
                    <li class="nav-item">
                        <a class="nav-link task-pill" data-toggle="pill" id="tasks-tab{{$task->id}}"
                           href="#task{{$task->id}}"
                           aria-controls="tasks{{$task->id}}" aria-expanded="true">{{$key+1}}
                            . {{$task->name}}
                            @if($task->is_star) <sup>*</sup> @endif
                            @if($task->is_hidden) <sup title="Скрытая задача">🔒</sup> @endif
                            @if (\Request::is('insider/*'))
                                @if($task->isSubmitted($user->id))
                                    @if($task->isFailed($user->id))
                                        <sup><img title="Не выполнено"
                                                  src="{{ url('images/icons/icons8-cancel-48.png') }}"
                                                  height="20"/></sup>
                                    @else
                                        @if ($task->isOnCheck($user->id))
                                            <sup><img title="Ожидает проверки"
                                                      src="{{ url('images/icons/icons8-historical-48.png') }}"
                                                        height="20"/></sup>
                                        @else
                                            @if($task->isFullDone($user->id))
                                                <sup><img title="Выполнено"
                                                          src="{{ url('images/icons/icons8-checkmark-48.png') }}"
                                                            height="20"/></sup>
                                            @else
                                                <sup><img title="Требует доработки"
                                                          src="{{ url('images/icons/icons8-error-48.png') }}"
                                                            height="20"/></sup>
                                            @endif
                                        @endif

                                    @endif
                                @else
                                    @if ($task->getDeadline($course->id))
                                        @php
                                            $exp = $task->getDeadline($course->id)->expiration;
                                        @endphp
                                        @if (\Carbon\Carbon::now()->gt($exp))
                                            &nbsp;<sup><img class="border border-danger rounded" title="Дедлайн"
                                                             src="{{ url('images/icons/deadline.png') }}" height="20" /></sup>
                                        @elseif (\Carbon\Carbon::now()->addDays(3)->gt($exp))
                                            &nbsp;<sup><img class="border border-warning rounded" title="Дедлайн"
                                                             src="{{ url('images/icons/deadline.png') }}" height="20" /></sup>
                                        @endif
                                    @endif
                                @endif
                            @endif
                        </a>
                    </li>
                @endforeach
            @endif
            @if (\Request::is('insider/*') && ($course->teachers->contains($user) || $user->role=='admin'))
                <li class="nav-item mx-1">
                    <a href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/edit')}}"
                       class="nav-link btn btn-success btn-sm p-2"><i
                                 class="icon ion-android-create"></i></a>
                </li>
                <li class="nav-item mx-1">
                    <button type="button" class="nav-link btn btn-success btn-sm p-2" data-toggle="modal"
                            data-target="#exampleModal">
                        <i class="icon ion-android-add-circle"></i>
                    </button>
                </li>
                <li class="nav-item mx-1">
                    <a href="{{url('/insider/courses/'.$course->id.'/perform/'.$step->id)}}"
                       class="nav-link btn btn-success btn-sm p-2"><i
                                 class="icon ion-android-desktop"></i></a>
                </li>
                <li class="nav-item mx-1">

                    <a class="nav-link btn btn-success btn-sm p-2"
                       href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/lower')}}"><i
                                 class="ion-arrow-up-c"></i></a>
                </li>
                <li class="nav-item mx-1">
                    <a class="nav-link btn btn-success btn-sm p-2"
                       href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/upper')}}"><i
                                 class="ion-arrow-down-c"></i></a>
                </li>
                <li class="nav-item mx-1">
                    <a class="nav-link btn btn-danger btn-sm p-2"
                       href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/delete')}}"
                       data-confirm="Вы уверены?"><i class="ion-close-round"></i></a>
                </li>
            @endif
        </ul>
    </div>

</div>
