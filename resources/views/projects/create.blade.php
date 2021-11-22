@extends('layouts.left-menu')

@section('title')
    GeekClass: Добавление проекта
@endsection

@section('content')
    <h2>Создание проекта</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="form-horizontal" enctype="multipart/form-data">

                        <div class="form-group">
                            {{ csrf_field() }}
                            <label for="name">Название</label>
                            <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}"
                                   required>
                            @if ($errors->has('name'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="short_description">Краткое описание</label>
                            <input id="short_description" class="form-control" name="short_description"
                                   required>{{old('short_description')}}</input>
                            @if ($errors->has('short_description'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('short_description') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="description">Описание</label>
                            <textarea id="description" class="form-control"
                                      name="description">{{old('description')}}</textarea>
                            @if ($errors->has('description'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="students" style="padding-bottom: 10px;">Команда:</label><br>
                            <select class="selectpicker2  form-control" data-live-search="true" id="team" name="team[]"
                                    multiple
                                    data-width="auto">
                                @foreach (\App\User::all() as $student)
                                    <option data-tokens="{{ $student->id }}"
                                            value="{{ $student->id }}">{{$student->name}}</option>
                                @endforeach
                            </select>

                            <script>
                                $('.selectpicker2').selectpicker();
                            </script>
                        </div>

                        <div class="form-group">
                            <label for="students" style="padding-bottom: 10px;">Привязать к заданию:</label><br>
                            <select class="selectpicker  form-control" data-live-search="true" id="task" name="task"
                                    data-width="auto">
                                <option data-tokens="-1"
                                        value="">Не привязывать к задаче
                                </option>
                                @foreach ($user->courses->where('state', 'started')->where('is_sdl', false) as $course)
                                    @foreach ($course->lessons as $lesson)
                                        @foreach ($lesson->steps as $step)
                                            @foreach ($step->tasks->where('answer', null)->where('is_code', false) as $task)
                                                <option data-tokens="{{ $course->id }}_{{ $task->id }}"
                                                        value="{{ $course->id }}_{{ $task->id }}"
                                                        data-subtext="{{$lesson->name}} ({{$course->name}})">{{$task->name}}</option>
                                            @endforeach
                                        @endforeach
                                    @endforeach
                                @endforeach
                            </select>

                            

                            <script>
                                $('.selectpicker').selectpicker();
                            </script>
                        </div>

                        <div class="form-group">
                            <label for="students" style="padding-bottom: 10px;">Привязать к идее:</label><br>
                            <select class="selectpicker  form-control" data-live-search="true" id="idea" name="idea"
                                    data-width="auto">
                                <option data-tokens="-1"
                                        value=""> Не привязывать к идее
                                </option>
                                @foreach (\App\Idea::all()->where("is_approved", true) as $idea)
                                    @
                                    <option value="{{$idea->id}}">{{$idea->name}}</option>
                                @endforeach
                            </select>

                            

                            <script>
                                $('.selectpicker').selectpicker();
                            </script>
                        </div>



                        <input type="submit" class="btn btn-success" value="Создать"/>
                    </form>
                </div>
            </div>
        </div>
        <script>
            var simplemde_description = new EasyMDE({
                spellChecker: false,
                autosave: true,
                element: document.getElementById("description")
            });
        </script>
    </div>
@endsection
