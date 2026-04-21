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
                    <div class="card">
                        <div class="card-header">
                            <span class="steps-task-title">{{$task->name}}</span>
                            @if (\Request::is('insider/*'))
                                @if ($task->getDeadline($course->id))

                                    @php
                                        $exp = $task->getDeadline($course->id)->expiration;
                                    @endphp
                                    @if (\Carbon\Carbon::now()->gt($exp))
                                        <img
                                                class="steps-deadline-icon steps-deadline-icon--danger"
                                                title="Дедлайн"
                                                src="{{ url('images/icons/deadline.png') }}"/>
                                    @elseif (\Carbon\Carbon::now()->addDays(3)->gt($exp))
                                        <img
                                                class="steps-deadline-icon steps-deadline-icon--warning"
                                                title="Дедлайн"
                                                src="{{ url('images/icons/deadline.png') }}"/>
                                    @else
                                        <img
                                                class="steps-deadline-icon"
                                                title="Дедлайн"
                                                src="{{ url('images/icons/deadline.png') }}"/>
                                    @endif
                                    {{ $task->getDeadline($course->id)->expiration->format('d.m.Y')}}

                                @endif
                            @endif


                            @if ($task->price > 0)
                                <img src="{{ url('images/icons/icons8-coins-48.png') }}"
                                     class="steps-coins-icon">
                                &nbsp;{{$task->price}}
                            @endif
                            @if (\Request::is('insider/*') && ($course->teachers->contains($user) || $user->role=='admin'))
                                <div class="steps-task-tools">
                                <a class="btn btn-danger btn-sm"
                                   href="#"
                                   data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/delete')}}"
                                   data-action-method="DELETE"
                                   data-action-confirm="Вы уверены?"><i
                                            class="icon fa-solid fa-xmark"></i></a>
                                <a class="btn btn-primary btn-sm steps-task-edit-btn"
                                       href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/edit')}}"><i
                                                class="icon fa-solid fa-pen-to-square"></i></a>
                                    @include('steps/partials/deadline_modal')
                                    <button type="button"
                                       title="Установить дедлайн" data-bs-toggle="modal"
                                       data-bs-target="#deadline-modal-{{$task->id}}"
                                       class="btn btn-outline-secondary btn-sm"><i
                                                class="icon fa-solid fa-calendar-days"></i></button>
                                <a title="Фантомное решение (добавить пустое решение для всех студентов)"
                                   class="btn btn-outline-secondary btn-sm"
                                   href="#"
                                   data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/phantom')}}"
                                   data-action-method="POST"><i
                                            class="icon fa-solid fa-wand-magic-sparkles"></i></a>
                                <a title="Сгенерировать форму perr-review" class="btn btn-outline-secondary btn-sm"
                                       href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/peer')}}"><i
                                                class="icon fa-solid fa-user-group"></i></a>
                                    @if ($task->is_code)
                                    <a title="Перепроверить все решения (обнулить баллы и отправить последнее решение каждого студента на перепроверку)"
                                    class="btn btn-outline-secondary btn-sm"
                                       href="#"
                                       data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/recheck-all')}}"
                                       data-action-method="POST"
                                       data-action-confirm="Вы уверены? Это обнулит все баллы и отправит последние решения на перепроверку."><i
                                                class="icon fa-solid fa-rotate"></i></a>
                                @endif
                                <a class="btn btn-outline-secondary btn-sm"
                                   href="#"
                                   data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/right')}}"
                                   data-action-method="POST"><i
                                            class="icon fa-solid fa-arrow-right"></i></a>
                                <a class="btn btn-outline-secondary btn-sm"
                                   href="#"
                                   data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/left')}}"
                                   data-action-method="POST"><i
                                            class="icon fa-solid fa-arrow-left"></i></a>
                                @if ($step->previousStep() != null)
                                    <a class="btn btn-outline-secondary btn-sm"
                                       href="#"
                                       data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/up')}}"
                                       data-action-method="POST"><i
                                                class="icon fa-solid fa-arrow-up"></i></a>
                                @endif
                                @if ($step->nextStep() != null)
                                <a class="btn btn-outline-secondary btn-sm"
                                       href="#"
                                       data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/down')}}"
                                       data-action-method="POST"><i
                                                class="icon fa-solid fa-arrow-down"></i></a>
                                @endif
                                </div>
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
                                        <a class="" data-bs-toggle="collapse" href="#solution{{$task->id}}" role="button"
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
                                              class="d-flex flex-column flex-sm-row gap-2 align-items-sm-center"
                                              onsubmit="checkTask(event, {{json_encode($task->id)}})">
                                            {{ csrf_field() }}
                                            <label for="text{{$task->id}}"><strong>Ответ:&nbsp;</strong></label>
                                            <input type="text" name="text" class="form-control form-control-sm"
                                                   id="text{{$task->id}}"/>&nbsp;
                                            <button type="submit" class="btn btn-primary btn-sm">Отправить
                                            </button>
                                        </form>
                                        @if ($errors->has('text'))
                                            <span
                                                    class="invalid-feedback d-block"><strong>{{ $errors->first('text') }}</strong></span>
                                        @endif
                                    @endif
                                @endif
                                <span class="badge text-bg-secondary">Очков опыта: {{$task->max_mark}}</span>
                                @if ($blocked)
                                    <span class="badge text-bg-danger" id="TSK_{{$task->id}}">Очков опыта: 0</span>
                                    <span class="small" id="TSK_COM_{{$task->id}}">Задача заблокирована</span>
                                @elseif ($task->is_quiz && $task->solutions()->where('user_id', Auth::User()->id)->count()!=0)
                                    @php
                                        $solution = $task->solutions()->where('user_id', Auth::User()->id)->get()->last();
                                    @endphp
                                    <span class="badge text-bg-primary"
                                          id="TSK_{{$task->id}}">Очков опыта: {{$solution->mark}}</span>
                                    <span class="small" id="TSK_COM_{{$task->id}}">{{$solution->comment}}</span>
                                @else
                                    <span class="badge text-bg-primary" id="TSK_{{$task->id}}"></span>
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
                        <div class="row steps-block-row">
                            <div class="col">
                                <div class="card">
                                    <div class="table-responsive">
                                    <table class="table table-striped table-sm align-middle mb-0">
                                        @foreach($course->students as $student)
                                            @php
                                                $filtered = $task->solutions->filter(function ($value) use ($student) {
                                                return $value->user_id == $student->id;
                                                });
                                                $blocked = $task->isBlocked($student->id, $course->id);
                                                if ($blocked) {
                                                    $mark = 0;
                                                    $need_check = false;
                                                    $class = 'danger';
                                                } else {
                                                    $mark = $filtered->max('mark');
                                                    $mark = $mark == null?0:$mark;
                                                    $need_check = false;
                                                    if ($filtered->count()!=0 && $filtered->last()->mark==null)
                                                    {
                                                    $need_check = true;
                                                    }
                                                    $class = 'light';
                                                    if ($mark >= $task->max_mark * 0.5)
                                                    {
                                                    $class = 'primary';
                                                    }
                                                    if ($mark >= $task->max_mark * 0.7)
                                                    {
                                                    $class = 'success';
                                                    }
                                                    if ($need_check)
                                                    {
                                                    $class = 'warning';
                                                    }
                                                }
                                            @endphp
                                            <tr>
                                                <td><a href="/insider/profile/{{ $student->id }}"
                                                       target="_blank">{{$student->name}}</a></td>
                                                <td><a target="_blank"
                                                       href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id)}}">
                                                        <span class="badge text-bg-{{ $class }}">{{$mark}}</span>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                    @foreach ($task->solutions->where('user_id', Auth::user()->id) as $solution)
                        @include('steps.solution_partial')
                    @endforeach
                @endif

                <div id="solutions_ajax{{$task->id}}">

                </div>
                @if (!$task->is_quiz and !$task->is_code)
                    @php $blocked = $task->isBlocked(Auth::User()->id, $course->id); @endphp
                    <div class="row steps-block-row">
                        <div class="col">
                            <div class="card">
                                <div class="card-header">
                                    Добавить решение
                                </div>
                                <div class="card-body steps-solution-card-body">
                                    @if (!$blocked)
                                        <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution')}}"
                                              method="POST"
                                              class="vstack gap-3"
                                              onsubmit="sendSolution(event, {{json_encode($task->id)}})">
                                            {{ csrf_field() }}
                                            <div class="mb-3 {{ $errors->has('text') ? ' is-invalid' : '' }}">
                                                <div>

                                                        <textarea id="text{{$task->id}}" class="form-control steps-solution-textarea" name="text"
                                                                  rows="4">{{old('text')}}</textarea>
                                                    <small class="text-muted">Пожалуйста, не используйте
                                                        это
                                                        поле
                                                        для
                                                        отправки
                                                        исходного кода. Выложите код на <a target="_blank"
                                                                                           href="https://paste.geekclass.ru">GeekPaste</a>,
                                                        <a target="_blank" href="https://pastebin.com">pastebin</a>, <a
                                                                target="_blank"
                                                                href="https://gist.github.com">gist</a>
                                                        или <a target="_blank"
                                                               href="https://paste.ofcode.org/">paste.ofcode</a>,
                                                        а
                                                        затем
                                                        скопируйте ссылку сюда.<br>Для загрузки картинок
                                                        и
                                                        небольших
                                                        файлов можно использовать <a
                                                                href="https://storage.geekclass.ru/"
                                                                target="_blank">storage.geekclass.ru</a>.
                                                    </small>

                                                    @if ($errors->has('text'))
                                                        <span
                                                                class="invalid-feedback d-block"><strong>{{ $errors->first('text') }}</strong></span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div>
                                                    <button type="submit" class="btn btn-primary" id="sbtn">Отправить
                                                    </button>
                                            </div>
                                        </form>
                                    @else
                                        <div class="alert alert-danger steps-alert-spaced" role="alert">
                                            Задача заблокирована для вас. Новые сдачи запрещены.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    @endforeach
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var firstTabPane = document.querySelector('.tab-pane');
            if (firstTabPane) {
                firstTabPane.classList.add('active', 'show');
            }
            @if($zero_theory)
            var firstTaskPill = document.querySelector('.task-pill');
            if (firstTaskPill) {
                firstTaskPill.classList.add('active');
            }
            @endif

            if (window.MathJax) {
                MathJax.typesetPromise();
            }

            document.querySelectorAll('a[data-bs-toggle="pill"]').forEach(function (tabLink) {
                tabLink.addEventListener('shown.bs.tab', function (e) {
                    if (!window.MathJax || !e || !e.target) {
                        return;
                    }

                    var targetSelector = e.target.getAttribute('href');
                    if (!targetSelector) {
                        return;
                    }

                    var targetPane = document.querySelector(targetSelector);
                    if (targetPane) {
                        MathJax.typesetPromise([targetPane]).catch(function (err) {
                            console.log('MathJax typeset error: ' + err.message);
                        });
                    }
                });
            });

            document.querySelectorAll('.collapse').forEach(function (collapseItem) {
                collapseItem.addEventListener('shown.bs.collapse', function () {
                    if (!window.MathJax) {
                        return;
                    }

                    MathJax.typesetPromise([collapseItem]).catch(function (err) {
                        console.log('MathJax typeset error: ' + err.message);
                    });
                });
            });
        });
    </script>
@endif
