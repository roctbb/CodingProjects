@if (!$quizer)
    @foreach ($tasks as $key => $task)
        <div class="tab-pane fade @if (!$empty && $zero_theory && $one_tasker) show active @endif"
             id="task{{$task->id}}"
             role="tabpanel" aria-labelledby="tasks-tab{{$task->id}}">
            <div class="row">
                <div class="col">
                    @if ($task->is_star)
                        <div class="alert alert-success" role="alert">
                            <strong>Это необязательная задача.</strong> За ее решение вы получите
                            дополнительный опыт.
                        </div>
                    @endif
                    <div class="card step-task-card">
                        <div class="card-header step-task-card__header">
                            <span class="step-task-card__title">{{$task->name}}</span>
                            @if (\Request::is('insider/*'))
                                @if ($task->getDeadline($course->id))

                                    @php
                                        $exp = $task->getDeadline($course->id)->expiration;
                                    @endphp
                                    @if (\Carbon\Carbon::now()->gt($exp))
                                        <img
                                                class="border border-danger rounded"
                                                title="Дедлайн"
                                                src="{{ url('images/icons/deadline.png') }}" height="23"/>
                                    @elseif (\Carbon\Carbon::now()->addDays(3)->gt($exp))
                                        <img
                                                class="border border-warning rounded"
                                                title="Дедлайн"
                                                src="{{ url('images/icons/deadline.png') }}" height="23"/>
                                    @else
                                        <img
                                                title="Дедлайн"
                                                src="{{ url('images/icons/deadline.png') }}" height="23"/>
                                    @endif
                                    {{ $task->getDeadline($course->id)->expiration->format('d.m.Y')}}

                                @endif
                            @endif


                            <span class="step-task-card__meta">
                            @if ($task->price > 0)
                                      <img src="{{ url('images/icons/icons8-coins-48.png') }}"
                                      height="23" alt="">
                                &nbsp;{{$task->price}}
                            @endif
                            </span>
                            @if (\Request::is('insider/*') && ($course->teachers->contains($user) || $user->role=='admin'))

                                <a class="float-end btn btn-link btn-sm p-0 ms-2 text-danger"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/delete')}}"
                                   data-confirm="Вы уверены?"><i
                                             class="icon ion-android-close"></i></a>
                                <a class="float-end btn btn-link btn-sm p-0 ms-2 text-success"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/edit')}}"><i
                                             class="icon ion-android-create"></i></a>
                                @include('steps/partials/deadline_modal')
                                <button type="button" title="Установить дедлайн" data-bs-toggle="modal"
                                   data-bs-target="#deadline-modal-{{$task->id}}"
                                   class="float-end btn btn-link btn-sm p-0 ms-2 text-muted"><i
                                             class="icon ion-ios-calendar"></i></button>
                                <a title="Фантомное решение (добавить пустое решение для всех студентов)"
                                   class="float-end btn btn-link btn-sm p-0 ms-2 text-muted"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/phantom')}}"><i
                                             class="icon ion-ios-color-wand"></i></a>
                                <a title="Сгенерировать форму perr-review" class="float-end btn btn-link btn-sm p-0 ms-2 text-muted"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/peer')}}"><i
                                             class="icon ion-person-stalker"></i></a>
                                @if ($task->is_code)
                                    <a title="Перепроверить все решения (обнулить баллы и отправить последнее решение каждого студента на перепроверку)"
                                        class="float-end btn btn-link btn-sm p-0 ms-2 text-muted"
                                        href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/recheck-all')}}"
                                        data-confirm="Вы уверены? Это обнулит все баллы и отправит последние решения на перепроверку."><i
                                                 class="icon ion-refresh"></i></a>
                                @endif
                                <a class="float-end btn btn-link btn-sm p-0 ms-2 text-muted"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/right')}}"><i
                                             class="icon ion-arrow-right-c"></i></a>
                                <a class="float-end btn btn-link btn-sm p-0 ms-2 text-muted"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/left')}}"><i
                                             class="icon ion-arrow-left-c"></i></a>
                                @if ($step->previousStep() != null)
                                    <a class="float-end btn btn-link btn-sm p-0 ms-2 text-muted"
                                       href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/up')}}"><i
                                                 class="icon ion-arrow-up-c"></i></a>
                                @endif
                                @if ($step->nextStep() != null)
                                    <a class="float-end btn btn-link btn-sm p-0 ms-2 text-muted"
                                       href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/down')}}"><i
                                                 class="icon ion-arrow-down-c"></i></a>
                                @endif
                            @endif
                        </div>
                        <div class="card-body markdown">
                            {!! parsedown_math($task->text) !!}
                            @if (\Request::is('insider/*'))
                                @php $blocked = $task->isBlocked(Auth::User()->id, $course->id); @endphp
                                @if ($blocked)
                                    <div class="alert alert-danger" role="alert">
                                        Задача заблокирована для вас. Новые сдачи запрещены.
                                    </div>
                                @endif
                                @if ($task->is_code)
                                    @if (!$blocked)
                                        <p>
                                            <a href="{{ config('services.geekpaste_url').'/?task_id=' . $task->id . '&course_id=' . $course->id }}"
                                               class="btn btn-primary" target="_blank">Сдать решение</a></p>
                                    @endif
                                @endif

                                @if ($user->role == 'student' and $task->solution!=null and $task->isFullDone(Auth::User()->id))
                                    <h3>Авторское решение</h3>
                                    {!! parsedown_math($task->solution) !!}
                                @endif
                                @if (($course->teachers->contains($user) || $user->role=='admin') and $task->solution != null)
                                    <p>
                                        <a data-bs-toggle="collapse" href="#solution{{$task->id}}" role="button"
                                           aria-expanded="false"
                                           aria-controls="collapseExample">
                                            Авторское решение &raquo;
                                        </a>
                                    </p>
                                    <div class="collapse" id="solution{{$task->id}}">
                                        {!! parsedown_math($task->solution) !!}
                                    </div>
                                @endif
                                @if ($task->is_quiz)
                                    @if (!$blocked)
                                        <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution')}}"
                                              method="POST"
                                              class="d-flex gap-2"
                                              data-check-task data-task-id="{{$task->id}}">
                                            {{ csrf_field() }}
                                            <label for="text{{$task->id}}"><strong>Ответ:&nbsp;</strong></label>
                                            <input type="text" name="text" class="form-control form-control-sm"
                                                   id="text{{$task->id}}"/>&nbsp;
                                            <button type="submit" class="btn btn-success btn-sm">Отправить
                                            </button>
                                        </form>
                                        @if ($errors->has('text'))
                                            <br><span
                                                    class="text-danger d-block"><strong>{{ $errors->first('text') }}</strong></span>
                                        @endif
                                    @endif
                                @endif
                                <span class="badge bg-secondary">Очков опыта: {{$task->max_mark}}</span>
                                @if ($blocked)
                                    <span class="badge bg-danger" id="TSK_{{$task->id}}">Очков опыта: 0</span>
                                    <span class="small" id="TSK_COM_{{$task->id}}">Задача заблокирована</span>
                                @elseif ($task->is_quiz && $task->solutions()->where('user_id', Auth::User()->id)->exists())
                                    @php
                                        $solution = $task->solutions()
                                            ->where('user_id', Auth::User()->id)
                                            ->orderByDesc('submitted')
                                            ->first();
                                    @endphp
                                    <span class="badge bg-primary"
                                          id="TSK_{{$task->id}}">Очков опыта: {{$solution->mark}}</span>
                                    <span class="small" id="TSK_COM_{{$task->id}}">{{$solution->comment}}</span>
                                @else
                                    <span class="badge bg-primary" id="TSK_{{$task->id}}"></span>
                                    <span class="small" id="TSK_COM_{{$task->id}}"></span>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @if (\Request::is('insider/*'))

                @if (!$task->is_quiz)
                    @if ($course->teachers->contains($user) || $user->role == 'admin')
                        <div class="row my-3">
                            <div class="col">
                                <div class="card">
                                    <table class="table table-stripped">
                                        @foreach($course->students as $student)
                                            @php
                                                $filtered = $task->solutions->filter(function ($value) use ($student) {
                                                return $value->user_id == $student->id;
                                                });
                                                $blocked = $task->isBlocked($student->id, $course->id);
                                                if ($blocked) {
                                                    $mark = 0;
                                                    $need_check = false;
                                                    $class = 'bg-danger';
                                                } else {
                                                    $mark = $filtered->max('mark');
                                                    $mark = $mark == null?0:$mark;
                                                    $need_check = false;
                                                    if ($filtered->count()!=0 && $filtered->last()->mark==null)
                                                    {
                                                    $need_check = true;
                                                    }
                                                    $class = 'bg-light text-dark';
                                                    if ($mark >= $task->max_mark * 0.5)
                                                    {
                                                    $class = 'bg-primary';
                                                    }
                                                    if ($mark >= $task->max_mark * 0.7)
                                                    {
                                                    $class = 'bg-success';
                                                    }
                                                    if ($need_check)
                                                    {
                                                    $class = 'bg-warning text-dark';
                                                    }
                                                }
                                            @endphp
                                            <tr>
                                                <td><a href="/insider/profile/{{ $student->id }}"
                                                       target="_blank">{{$student->name}}</a></td>
                                                <td><a target="_blank"
                                                       href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id)}}">
                                                        <span class="badge {{$class}}">{{$mark}}</span>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                    @include('steps.partials.task_solutions_list')
                @endif

                @if (!$task->is_quiz and !$task->is_code)
                    @include('steps.partials.task_solution_form')
                @endif
            @endif
        </div>
    @endforeach
    <div data-step-content-tabs data-zero-theory="{{ $zero_theory ? 'true' : 'false' }}" hidden></div>
@endif
