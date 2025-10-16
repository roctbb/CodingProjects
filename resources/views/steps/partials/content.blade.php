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
                            {{$task->name}} &nbsp; &nbsp;
                            @if (\Request::is('insider/*'))
                                @if ($task->getDeadline($course->id))

                                    @php
                                        $exp = $task->getDeadline($course->id)->expiration;
                                    @endphp
                                    @if (\Carbon\Carbon::now()->gt($exp))
                                        <img
                                                style="border: 1px dotted red; height: 23px; border-radius: 3px;"
                                                title="Дедлайн"
                                                src="{{ url('images/icons/deadline.png') }}"/>
                                    @elseif (\Carbon\Carbon::now()->addDays(3)->gt($exp))
                                        <img
                                                style="border: 1px dotted yellow; height: 23px; border-radius: 3px;"
                                                title="Дедлайн"
                                                src="{{ url('images/icons/deadline.png') }}"/>
                                    @else
                                        <img
                                                style="height: 23px;"
                                                title="Дедлайн"
                                                src="{{ url('images/icons/deadline.png') }}"/>
                                    @endif
                                    {{ $task->getDeadline($course->id)->expiration->format('d.m.Y')}}

                                @endif
                            @endif


                            &nbsp;&nbsp;
                            @if ($task->price > 0)
                                <img src="{{ url('images/icons/icons8-coins-48.png') }}"
                                     style="height: 23px;">
                                &nbsp;{{$task->price}}
                            @endif
                            @if (\Request::is('insider/*') && ($course->teachers->contains($user) || $user->role=='admin'))

                                <a class="float-right btn btn-danger btn-sm"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/delete')}}"
                                   onclick="return confirm('Вы уверены?')"><i
                                            class="icon ion-android-close"></i></a>
                                <a style="margin-right: 5px;"
                                   class="float-right btn btn-success btn-sm"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/edit')}}"><i
                                            class="icon ion-android-create"></i></a>
                                @include('steps/partials/deadline_modal')
                                <i title="Установить дедлайн" data-toggle="modal"
                                   data-target="#deadline-modal-{{$task->id}}"
                                   class="float-right btn btn-default btn-sm"><i
                                            class="icon ion-ios-calendar"></i></i>
                                <a title="Фантомное решение (добавить пустое решение для всех студентов)"
                                   class="float-right btn btn-default btn-sm"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/phantom')}}"><i
                                            class="icon ion-ios-color-wand"></i></a>
                                <a title="Сгенерировать форму perr-review" class="float-right btn btn-default btn-sm"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/peer')}}"><i
                                            class="icon ion-person-stalker"></i></a>
                                <a class="float-right btn btn-default btn-sm"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/right')}}"><i
                                            class="icon ion-arrow-right-c"></i></a>
                                <a class="float-right btn btn-default btn-sm"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/left')}}"><i
                                            class="icon ion-arrow-left-c"></i></a>
                                @if ($step->previousStep() != null)
                                    <a class="float-right btn btn-default btn-sm"
                                       href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/up')}}"><i
                                                class="icon ion-arrow-up-c"></i></a>
                                @endif
                                @if ($step->nextStep() != null)
                                    <a class="float-right btn btn-default btn-sm"
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
                                        <a class="" data-toggle="collapse" href="#solution{{$task->id}}" role="button"
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
                                              class="form-inline"
                                              onsubmit="checkTask(event, {{json_encode($task->id)}})">
                                            {{ csrf_field() }}
                                            <label for="text{{$task->id}}"><strong>Ответ:&nbsp;</strong></label>
                                            <input type="text" name="text" class="form-control form-control-sm"
                                                   id="text{{$task->id}}"/>&nbsp;
                                            <button type="submit" class="btn btn-success btn-sm">Отправить
                                            </button>
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
                                        $solution = $task->solutions()->where('user_id', Auth::User()->id)->get()->last();
                                    @endphp
                                    <span class="badge badge-primary"
                                          id="TSK_{{$task->id}}">Очков опыта: {{$solution->mark}}</span>
                                    <span class="small" id="TSK_COM_{{$task->id}}">{{$solution->comment}}</span>
                                @else
                                    <span class="badge badge-primary" id="TSK_{{$task->id}}"></span>
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
                        <div class="row" style="margin-top: 15px; margin-bottom: 15px;">
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
                                                    $class = 'badge-danger';
                                                } else {
                                                    $mark = $filtered->max('mark');
                                                    $mark = $mark == null?0:$mark;
                                                    $need_check = false;
                                                    if ($filtered->count()!=0 && $filtered->last()->mark==null)
                                                    {
                                                    $need_check = true;
                                                    }
                                                    $class = 'badge-light';
                                                    if ($mark >= $task->max_mark * 0.5)
                                                    {
                                                    $class = 'badge-primary';
                                                    }
                                                    if ($mark >= $task->max_mark * 0.7)
                                                    {
                                                    $class = 'badge-success';
                                                    }
                                                    if ($need_check)
                                                    {
                                                    $class = 'badge-warning';
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
                    @foreach ($task->solutions->where('user_id', Auth::user()->id) as $solution)
                        @include('steps.solution_partial')
                    @endforeach
                @endif

                <div id="solutions_ajax{{$task->id}}">

                </div>
                @if (!$task->is_quiz and !$task->is_code)
                    @php $blocked = $task->isBlocked(Auth::User()->id, $course->id); @endphp
                    <div class="row" style="margin-top: 15px; margin-bottom: 15px;">
                        <div class="col">
                            <div class="card">
                                <div class="card-header">
                                    Добавить решение
                                </div>
                                <div class="card-body" style="padding: 0">
                                    @if (!$blocked)
                                        <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution')}}"
                                              method="POST"
                                              class="form-horizontal"
                                              onsubmit="sendSolution(event, {{json_encode($task->id)}})">
                                            {{ csrf_field() }}
                                            <div class="form-group{{ $errors->has('text') ? ' has-error' : '' }}">
                                                <div class="col-md-12">

                                                        <textarea id="text{{$task->id}}" class="form-control" name="text"
                                                                  style="margin-top: 15px;"
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
                                                        <br><span
                                                                class="help-block error-block"><strong>{{ $errors->first('text') }}</strong></span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-md-12">
                                                    <button type="submit" class="btn btn-success" id="sbtn">Отправить
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    @else
                                        <div class="alert alert-danger" role="alert" style="margin: 15px;">
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
        $('.tab-pane').first().addClass('active show');
        @if($zero_theory)
        $('.task-pill').first().addClass('active');
        @endif

        // Re-render MathJax when tabs are shown
        $(document).ready(function() {
            // Initial MathJax rendering for visible content
            if (window.MathJax) {
                MathJax.typesetPromise();
            }

            // Listen for Bootstrap tab show events
            $('a[data-toggle="pill"]').on('shown.bs.tab', function (e) {
                // Re-render MathJax for the newly shown tab content
                if (window.MathJax) {
                    var targetPane = $(e.target.getAttribute('href'));
                    if (targetPane.length) {
                        MathJax.typesetPromise([targetPane[0]]).catch(function (err) {
                            console.log('MathJax typeset error: ' + err.message);
                        });
                    }
                }
            });

            // Listen for Bootstrap collapse show events (for solution sections)
            $('.collapse').on('shown.bs.collapse', function () {
                if (window.MathJax) {
                    MathJax.typesetPromise([this]).catch(function (err) {
                        console.log('MathJax typeset error: ' + err.message);
                    });
                }
            });
        });
    </script>
@endif
