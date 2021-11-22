@extends('layouts.left-menu')

@section('title')
    GeekClass: Изменение проекта "{{$project->name}}"
@endsection

@section('content')

    <h2>Изменение проекта "{{$project->name}}"</h2>
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
                                <input id="name" type="text" class="form-control" name="name" value="{{$project->name}}"
                                       required>
                            @endif
                            @if ($errors->has('name'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="url">URL Обложки</label>

                            @if (old('url')!="")
                                <input id="url" type="text" class="form-control" name="url" value="{{old('url')}}">
                            @else
                                <input id="url" type="text" class="form-control" name="url" value="{{$project->url}}">
                            @endif
                            @if ($errors->has('url'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('url') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="short_description">Краткое описание</label>
                            <textarea id="short_description" class="form-control" name="short_description"
                                      required>@if (old('short_description')!=""){{old('short_description')}}@else{{$project->short_description}}@endif</textarea>
                            @if ($errors->has('short_description'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('short_description') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="description">Описание</label>
                            <textarea id="description" class="form-control" name="description"
                            >@if (old('description')!=""){{old('description')}}@else{{$project->description}}@endif</textarea>
                            @if ($errors->has('description'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="students" style="padding-bottom: 10px;">Команда (кроме вас):</label><br>
                            <select class="selectpicker2  form-control" data-live-search="true" id="team" name="team[]"
                                    multiple
                                    data-width="auto">
                                @foreach (\App\User::all() as $student)
                                    <option data-tokens="{{ $student->id }}"
                                            value="{{ $student->id }}">{{$student->name}}</option>
                                @endforeach
                            </select>

                            <script>
                                $('.selectpicker2').selectpicker('val', [{{implode(',', $project->team->pluck('id')->toArray())}}]);
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
                                {{--                                             <script>
                                                        $('.selectpicker').selectpicker('val', '{{ $course->id }}_{{ $task->id }}');
                                                    </script> --}}
                            </select>

                        </div>

                        <div class="form-group">
                            <label for="students" style="padding-bottom: 10px;">Привязать к идее:</label><br>
                            <select class="selectpicker  form-control" data-live-search="true" id="idea" name="idea"
                        data-width="auto" value="{{$project->basedOn->count() > 0 ? $project->basedOn[0] : ""}}">
                                <option data-tokens="-1"
                                        value=""> Не привязывать к идее
                                </option>
                                @foreach (\App\Idea::all()->where("is_approved", true) as $idea)
                                    
                                    <option value="{{$idea->id}}">{{$idea->name}}</option>
                                @endforeach
                            </select>

                            

                            <script>
                                $('.selectpicker').selectpicker();
                            </script>
                        </div>


                        <button type="submit" class="btn btn-success">Сохранить</button>
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

