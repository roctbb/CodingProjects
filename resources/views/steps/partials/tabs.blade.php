<div class="row">
    <div class="col">
        <ul class="nav nav-pills nav-fill @if (count($tasks)==0 || $one_tasker || $quizer) float-right @endif"
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
                        <!-- TODO -->
                            @if (\Request::is('insider/*'))
                                @if($task->isSubmitted($user->id))
                                    @if($task->isFailed($user->id))
                                        <sup><img title="Не выполнено"
                                                  src="{{ url('images/icons/icons8-cancel-48.png') }}"
                                                  style="height: 20px;"/></sup>
                                    @else
                                        @if ($task->isOnCheck($user->id))
                                            <sup><img title="Ожидает проверки"
                                                      src="{{ url('images/icons/icons8-historical-48.png') }}"
                                                      style="height: 20px;"/></sup>
                                        @else
                                            @if($task->isFullDone($user->id))
                                                <sup><img title="Выполнено"
                                                          src="{{ url('images/icons/icons8-checkmark-48.png') }}"
                                                          style="height: 20px;"/></sup>
                                            @else
                                                <sup><img title="Требует доработки"
                                                          src="{{ url('images/icons/icons8-error-48.png') }}"
                                                          style="height: 20px;"/></sup>
                                            @endif
                                        @endif

                                    @endif
                                @else
                                    @if ($task->getDeadline($course->id))
                                        @php
                                            $exp = $task->getDeadline($course->id)->expiration;
                                        @endphp
                                        @if (\Carbon\Carbon::now()->gt($exp))
                                            &nbsp;<sup><img style="border: 1px dotted red; height: 20px; border-radius: 3px;" title="Дедлайн"
                                                            src="{{ url('images/icons/deadline.png') }}" /></sup>
                                        @elseif (\Carbon\Carbon::now()->addDays(3)->gt($exp))
                                            &nbsp;<sup><img style="border: 1px dotted yellow; height: 20px; border-radius: 3px;" title="Дедлайн"
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
                <li class="nav-item" style="max-width: 45px;">
                    <a href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/edit')}}"
                       class="nav-link btn btn-success"
                       style="padding: 8px 9px; height: 40px; margin: 0 0; margin-left: 5px; width: 40px;;"><i
                                class="icon ion-android-create"></i></a>
                </li>
                <li class="nav-item" style="max-width: 45px;">
                    <button type="button" class="nav-link btn btn-success" data-toggle="modal"
                            data-target="#exampleModal"
                            style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;;">
                        <i class="icon ion-android-add-circle"></i>
                    </button>
                </li>
                <li class="nav-item" style="max-width: 45px;">
                    <a href="{{url('/insider/courses/'.$course->id.'/perform/'.$step->id)}}"
                       class="nav-link btn btn-success"
                       style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;"><i
                                class="icon ion-android-desktop"></i></a>
                </li>
                <li class="nav-item" style="max-width: 45px;">

                    <a class="nav-link btn btn-success"
                       style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;"
                       href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/lower')}}"><i
                                class="ion-arrow-up-c"></i></a>
                </li>
                <li class="nav-item" style="max-width: 45px;">
                    <a class="nav-link btn btn-success"
                       style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;"
                       href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/upper')}}"><i
                                class="ion-arrow-down-c"></i></a>
                </li>
                <li class="nav-item" style="max-width: 45px;">
                    <a class="nav-link btn btn-danger"
                       style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;"
                       href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/delete')}}"
                       onclick="return confirm('Вы уверены?')"><i class="ion-close-round"></i></a>
                </li>
            @endif
        </ul>
    </div>

</div>