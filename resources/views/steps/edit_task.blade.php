@extends('layouts.left-menu')

@section('title')
    GeekClass: Изменение задачи "{{$task->name}}"
@endsection

@section('content')

    <h2>Изменение задачи "{{$task->name}}"</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="form-horizontal" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label for="name">Название</label>

                            @if (old('name')!="")
                                <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}"
                                       required>
                            @else
                                <input id="name" type="text" class="form-control" name="name" value="{{$task->name}}"
                                       required>
                            @endif
                            @if ($errors->has('name'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="consequences" style="padding-bottom: 10px;">Подтверждаемые результаты из <sup>
                                    <small>Core</small>
                                </sup>:</label><br>
                            <select class="selectpicker  form-control" data-live-search="true" id="consequences"
                                    name="consequences[]" multiple data-width="auto">
                                @foreach (\App\CoreNode::where('is_root', false)->where('version', 1)->get() as $node)
                                    <option data-tokens="{{ $node->id }}" value="{{ $node->id }}"
                                            data-subtext="{{$node->getParentLine()}}">{{$node->title}}</option>
                                @endforeach
                            </select>

                            <script>
                                $('.selectpicker').selectpicker('val', [{{implode(',', $task->consequences->pluck('id')->toArray())}}]);
                            </script>
                        </div>

                        <div class="form-group{{ $errors->has('max_mark') ? ' has-error' : '' }}">
                            <label for="max_mark">Очков опыта</label>

                            @if (old('max_mark')!="")
                                <input type="text" name="max_mark" class="form-control" id="max_mark"
                                       value="{{old('name')}}"
                                       required/>
                            @else
                                <input type="text" name="max_mark" class="form-control" id="max_mark"
                                       value="{{$task->max_mark}}" required/>
                            @endif

                            @if ($errors->has('max_mark'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('max_mark') }}</strong>
                                    </span>
                            @endif

                        </div>

                        <div class="form-group">
                            <label for="text">Текст</label>
                            <textarea id="text" class="form-control"
                                      name="text">@if (old('text')!=""){{old('text')}}@else{{$task->text}}@endif</textarea>
                            @if ($errors->has('text'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('text') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="solution">Решение</label>
                            <textarea id="solution" class="form-control"
                                      name="solution">@if (old('solution')!=""){{old('solution')}}@else{{$task->solution}}@endif</textarea>
                            @if ($errors->has('solution'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('solution') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="is_star">Дополнительное</label>
                            <input type="checkbox" id="is_star" name="is_star" value="on"
                                   @if ($task->is_star) checked @endif/>
                        </div>


                        <div class="form-group{{ $errors->has('price') ? ' has-error' : '' }}">
                            <label for="price" class="col-md-4">Премия</label>

                            <div class="col-md-12">

                                @if (old('price')!="")
                                    <input type="text" name="price" class="form-control" id="price"
                                           value="{{old('price')}}"/>
                                @else
                                    <input type="text" name="price" class="form-control" id="price"
                                           value="{{$task->price}}"/>
                                @endif

                                @if ($errors->has('price'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('price') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group{{ $errors->has('answer') ? ' has-error' : '' }}">
                            <label for="answer" class="col-md-4">Ответ</label>

                            <div class="col-md-12">

                                @if (old('answer')!="")
                                    <input type="text" name="answer" class="form-control" id="answer"
                                           value="{{old('answer')}}"/>
                                @else
                                    <input type="text" name="answer" class="form-control" id="answer"
                                           value="{{$task->answer}}"/>
                                @endif

                                @if ($errors->has('answer'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('answer') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="form-group{{ $errors->has('template') ? ' has-error' : '' }}">
                            <label for="template" class="col-md-4">Шаблон кода</label>

                            <div class="col-md-12">

                                @if (old('template')!="")
                                    <textarea id="template" class="form-control"
                                              name="template">{{old('template')}}</textarea>
                                @else
                                    <textarea id="template" class="form-control"
                                              name="template">{{$task->template}}</textarea>
                                @endif




                                @if ($errors->has('template'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('template') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="form-group{{ $errors->has('checker') ? ' has-error' : '' }}">
                            <label for="checker" class="col-md-4">Чекер</label>

                            <div class="col-md-12">

                                @if (old('checker')!="")
                                    <textarea id="checker" class="form-control"
                                              name="checker">{{old('checker')}}</textarea>
                                @else
                                    <textarea id="checker" class="form-control"
                                              name="checker">{{$task->checker}}</textarea>
                                @endif




                                @if ($errors->has('checker'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('checker') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="form-group{{ $errors->has('code_input') ? ' has-error' : '' }}">
                            <label for="code_input" class="col-md-4">Входные данные</label>

                            <div class="col-md-12">

                                @if (old('code_input')!="")
                                    <textarea id="code_input" class="form-control"
                                              name="code_input">{{old('code_input')}}</textarea>
                                @else
                                    <textarea id="code_input" class="form-control"
                                              name="code_input">{{$task->code_input}}</textarea>
                                @endif




                                @if ($errors->has('code_input'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('code_input') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="form-group{{ $errors->has('code_answer') ? ' has-error' : '' }}">
                            <label for="code_answer" class="col-md-4">Результат</label>

                            <div class="col-md-12">

                                @if (old('code_answer')!="")
                                    <textarea id="code_answer" class="form-control"
                                              name="code_answer">{{old('code_answer')}}</textarea>
                                @else
                                    <textarea id="code_answer" class="form-control"
                                              name="code_answer">{{$task->code_answer}}</textarea>
                                @endif




                                @if ($errors->has('code_answer'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('code_answer') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        var simplemde_task = new EasyMDE({
            spellChecker: false,
            element: document.getElementById("text")
        });
        var simplemde_solution = new EasyMDE({
            spellChecker: false,
            element: document.getElementById("solution")
        });
    </script>
@endsection

