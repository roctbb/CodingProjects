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


                            &nbsp;&nbsp;@if (\Request::is('insider/*'))
                                @foreach($task->consequences as $consequence)
                                    @if (!$user->checkPrerequisite($consequence))
                                        <span class="badge badge-secondary">{{$consequence->title}}</span>
                                    @else
                                        <span class="badge badge-success">{{$consequence->title}}</span>
                                    @endif
                                @endforeach
                            @endif
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
                                <i title="Установить дедлайн" data-toggle="modal" data-target="#deadline-modal-{{$task->id}}"
                                   class="float-right btn btn-default btn-sm"><i
                                            class="icon ion-ios-calendar"></i></i>
                                <a title="Фантомное решение (добавить пустое решение для всех студентов)" class="float-right btn btn-default btn-sm"
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
                            @parsedown($task->text)
                            @if (\Request::is('insider/*'))
                                @if ($user->role == 'student' and $task->solution!=null and $task->isDone(Auth::User()->id))
                                    <h3>Авторское решение</h3>
                                    @parsedown($task->solution)
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
                                        @parsedown($task->solution)
                                    </div>
                                @endif
                                @if ($task->is_quiz)
                                    <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution')}}"
                                          method="POST"
                                          class="form-inline"
                                          onsubmit="checkTask(event, {{json_encode($task->id)}})"
                                    >
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
                                <span class="badge badge-secondary">Очков опыта: {{$task->max_mark}}</span>
                                @if ($task->is_quiz && $task->solutions()->where('user_id', Auth::User()->id)->count()!=0)
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
                @if ($task->is_code && $task->solutions()->where('user_id', Auth::User()->id)->count()!=0)
                    @php
                        $solution = $task->solutions()->where('user_id', Auth::User()->id)->get()->last();
                    @endphp
                    @if ($solution->user_id == Auth::User()->id)
                        <div class="row" style="margin-top: 15px; margin-bottom: 15px;">
                            <div class="col">
                                <div class="card">
                                    <div class="card-header">
                                        Дата сдачи: {{ $solution->submitted->format('d.M.Y H:i')}}
                                        <div class="float-right">
                                            @if ($solution->mark!=null)
                                                <span class="badge badge-primary">Очков опыта: {{$solution->mark}}</span>
                                                <br>
                                            @else
                                                <span class="badge badge-secondary">Решение еще не проверено</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="card-body" style="padding-top: 0; padding-bottom: 0;">
                                        @if ($task->is_code)
                                            <pre><code class="hljs python">{{$solution->text}}</code></pre>
                                        @else
                                            {!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->text)),
                                            false)) !!}
                                        @endif
                                        @if ($solution->mark!=null)
                                            <p>
                  <span class="badge badge-light">Проверено: {{$solution->checked}}
                     , {{$solution->teacher->name}}</span>
                                            </p>
                                            <p class="small">
                                                {!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;',
                                                $solution->comment)), false)) !!}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
                @if (!$task->is_quiz && !$task->is_code)
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
                                            @endphp
                                            <tr>
                                                <td>{{$student->name}}</td>
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
                    @foreach ($tasksSolutions[$key]->shownSolutions as $sol_key => $solution)
                        @include('steps.solution_partial')
                    @endforeach
                    @if(sizeof($tasksSolutions[$key]->hiddenSolutions) > 0)
                        <a data-toggle="collapse" href="#solutionCollapse{{$task->id}}">Показать остальные</a>
                        <div class="collapse" id="solutionCollapse{{$task->id}}">
                            @foreach ($tasksSolutions[$key]->hiddenSolutions as $sol_key => $solution)
                                @include('steps.solution_partial')
                            @endforeach
                        </div>
                    @endif
                @endif

                <div id="solutions_ajax{{$task->id}}">

                </div>
                @if (!$task->is_quiz)
                    <div class="row" style="margin-top: 15px; margin-bottom: 15px;">
                        <div class="col">
                            <div class="card">
                                <div class="card-header">
                                    Добавить решение
                                </div>
                                <div class="card-body" style="padding: 0">
                                    <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution')}}"
                                          method="POST"
                                          class="form-horizontal"
                                          onsubmit="sendSolution(event, {{json_encode($task->id)}})">
                                        {{ csrf_field() }}
                                        <div class="form-group{{ $errors->has('text') ? ' has-error' : '' }}">
                                            <div class="col-md-12">
                                                @if (!$task->is_code)
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
                                                        или <a target="_blank" href="https://paste.ofcode.org/">paste.ofcode</a>,
                                                        а
                                                        затем
                                                        скопируйте ссылку сюда.<br>Для загрузки картинок
                                                        и
                                                        небольших
                                                        файлов можно использовать <a
                                                                href="https://storage.geekclass.ru/"
                                                                target="_blank">storage.geekclass.ru</a>.
                                                    </small>
                                                @else
                                                    @if (old('text')!='')
                                                        <textarea id="text{{$task->id}}" class="form-control"
                                                                  name="text">{{old('text')}}</textarea>
                                                        <div class="editor">
                                                            <div class="ace_editor" id="editor{{$task->id}}"></div>
                                                        </div>
                                                    @else
                                                        <textarea id="text{{$task->id}}" class="form-control"
                                                                  name="text">@if (!isset($solution)){{$task->template}}@else{{$solution->text}}@endif</textarea>
                                                        <div class="editor">
                                                            <div class="ace_editor" id="editor{{$task->id}}"></div>
                                                        </div>
                                                    @endif
                                                    <script>
                                                        var editor = ace.edit("editor{{$task->id}}");
                                                        editor.setTheme("ace/theme/tomorrow_night_eighties");
                                                        editor.session.setMode("ace/mode/python");
                                                        var textarea = $('#text{{$task->id}}').hide();
                                                        editor.getSession().setValue(textarea.val());
                                                        editor.getSession().on('change', function () {
                                                            textarea.val(editor.getSession().getValue());
                                                        });
                                                        editor.setOptions({
                                                            fontFamily: 'Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace',
                                                            fontSize: "14px"
                                                        });
                                                    </script>
                                                @endif
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
    </script>
@endif