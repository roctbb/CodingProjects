@extends('layouts.left-menu')

@section('title')
    GeekClass: Изменение курса "{{$course->name}}"
@endsection

@section('content')

    <h2>Изменение курса "{{$course->name}}"</h2>
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
                                <input id="name" type="text" class="form-control" name="name" value="{{$course->name}}"
                                       required>
                            @endif
                            @if ($errors->has('name'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>
                        @if (Auth::user()->role=='admin')
                            <div class="form-group">
                                <label for="mode" style="padding-bottom: 10px;">Тип курса:</label><br>
                                <select class="selectpicker4 form-control" data-live-search="true" id="mode"
                                        name="mode">
                                    <option data-tokens="private" value="private">Скрытый</option>
                                    <option data-tokens="offline" value="offline">Офлайн</option>
                                    <option data-tokens="zoom" value="zoom">Платный онлайн курс</option>
                                    <option data-tokens="paid" value="paid">Платный онлайн курс без преподавателя</option>
                                    <option data-tokens="open" value="open">Бесплатный онлайн курс</option>
                                </select>

                                <script>
                                    $('.selectpicker4').selectpicker('val', '{{$course->mode}}');
                                </script>
                            </div>

                            <div class="form-group">
                                <label for="categories" style="padding-bottom: 10px;">Образовательные
                                    направления:</label><br>
                                <select class="selectpicker3 form-control" data-live-search="true" id="categories"
                                        name="categories[]" multiple data-width="auto">
                                    @foreach (\App\CourseCategory::all() as $category)
                                        <option data-tokens="{{ $category->id }}"
                                                value="{{ $category->id }}">{{$category->title}}</option>
                                    @endforeach
                                </select>

                                <script>
                                    $('.selectpicker3').selectpicker('val', [{{implode(',', $course->categories->pluck('id')->toArray())}}]);
                                </script>
                            </div>

                        @endif

                        @if (Auth::user()->role=='admin')
                            <div class="form-group">
                                <label for="teachers" style="padding-bottom: 10px;">Учителя:</label><br>
                                <select class="selectpicker1  form-control" data-live-search="true" id="teachers"
                                        name="teachers[]" multiple data-width="auto">
                                    @foreach (\App\User::where('role', 'teacher')->orWhere('role', 'admin')->get() as $teacher)
                                        <option data-tokens="{{ $teacher->id }}"
                                                value="{{ $teacher->id }}">{{$teacher->name}}</option>
                                    @endforeach
                                </select>

                                <script>
                                    $('.selectpicker1').selectpicker('val', [{{implode(',', $course->teachers->pluck('id')->toArray())}}]);
                                </script>
                            </div>

                        @endif

                        @if ($course->state == 'draft')
                            <div class="form-group{{ $errors->has("start_date") ? ' has-error' : '' }}">
                                <label for="start_date">Дата начала:</label>
                                @if (old('start_date')!="" || $course->start_date==null)
                                    <input id="start_date" type="text" class="form-control date"
                                           value="{{old("start_date")}}"
                                           name="start_date">
                                @else
                                    <input id="start_date" type="text" class="form-control date"
                                           value="{{$course->start_date->format('Y-m-d')}}"
                                           name="start_date">
                                @endif


                                @if ($errors->has("start_date"))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first("start_date") }}</strong>
                                    </span>
                                @endif
                            </div>
                        @endif

                        <div class="form-group">
                            <label for="students" style="padding-bottom: 10px;">Студенты:</label><br>
                            <select class="selectpicker2  form-control" data-live-search="true" id="students"
                                    name="students[]" multiple data-width="auto">
                                @foreach (\App\User::all() as $student)
                                    <option data-tokens="{{ $student->id }}"
                                            value="{{ $student->id }}">{{$student->name}}</option>
                                @endforeach
                            </select>

                            <script>
                                $('.selectpicker2').selectpicker('val', [{{implode(',', $course->students->pluck('id')->toArray())}}]);
                            </script>
                        </div>

                        <div class="form-group">
                            <label for="git">Инвайт</label>

                            @if (old('invite')!="")
                                <input id="invite" type="text" class="form-control" name="invite"
                                       value="{{old('invite')}}">
                            @else
                                <input id="invite" type="text" class="form-control" name="invite"
                                       value="{{$course->invite}}"
                                >
                            @endif
                            @if ($errors->has('invite'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('invite') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="git">Git репозиторий</label>

                            @if (old('git')!="")
                                <input id="git" type="text" class="form-control" name="git" value="{{old('git')}}">
                            @else
                                <input id="git" type="text" class="form-control" name="git" value="{{$course->git}}"
                                >
                            @endif
                            @if ($errors->has('git'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('git') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="telegram">Telegram чат</label>

                            @if (old('telegram')!="")
                                <input id="telegram" type="text" class="form-control" name="telegram"
                                       value="{{old('telegram')}}">
                            @else
                                <input id="telegram" type="text" class="form-control" name="telegram"
                                       value="{{$course->telegram}}">
                            @endif
                            @if ($errors->has('telegram'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('telegram') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="site">Ссылка на описание курса</label>

                            @if (old('site')!="")
                                <input id="site" type="text" class="form-control" name="site" value="{{old('site')}}">
                            @else
                                <input id="site" type="text" class="form-control" name="site" value="{{$course->site}}"
                                >
                            @endif
                            @if ($errors->has('site'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('site') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="image">Ссылка на обложку</label>

                            @if (old('image')!="")
                                <input id="image" type="text" class="form-control" name="image"
                                       value="{{old('image')}}">
                            @else
                                <input id="image" type="text" class="form-control" name="image"
                                       value="{{$course->image}}"
                                >
                            @endif
                            @if ($errors->has('image'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('image') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="description">Описание</label>
                            <textarea id="description" class="form-control" name="description"
                                      required>@if (old('description')!=""){{old('description')}}@else{{$course->description}}@endif</textarea>
                            @if ($errors->has('description'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <hr>

                        <div class="form-group">
                            <label for="weekdays">Дни недели</label>

                            @if (old('weekdays')!="")
                                <input id="weekdays" type="text" class="form-control" name="weekdays"
                                       value="{{old('weekdays')}}" placeholder="1;4">
                            @else
                                <input id="weekdays" type="text" class="form-control" name="weekdays"
                                       value="{{$course->weekdays}}" placeholder="1;4">
                            @endif
                            @if ($errors->has('weekdays'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('weekdays') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group{{ $errors->has("import") ? ' has-error' : '' }}">
                            <label for="import">Импорт</label>
                            <input id="import" type="file" class="form-control"
                                   name="import">

                            @if ($errors->has("import"))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first("import") }}</strong>
                                    </span>
                            @endif
                        </div>


                        <button type="submit" class="btn btn-success">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

