@extends('layouts.left-menu')

@section('title')
    Изменение курса "{{$course->name}}"
@endsection

@section('content')

    <div class="form-page">
        <div class="form-page-header gc-card mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id) }}"><i class="icon ion-chevron-left"></i> К курсу</a>
                <h2 class="mb-1 text-truncate">Настройки курса</h2>
                <p class="mb-0 text-muted text-truncate">{{ $course->name }}</p>
            </div>
            <div class="form-page-status">
                <span class="badge course-status-pill course-status-pill--primary">{{ $course->state }}</span>
                @if($course->mode)
                    <span class="badge course-status-pill course-status-pill--muted">{{ $course->mode }}</span>
                @endif
            </div>
        </div>

        <div class="gc-card form-card form-card--wide">
                    <form method="POST" enctype="multipart/form-data" class="form-grid form-grid--settings">
                        {{ csrf_field() }}
                        <div class="mb-3">
                            <label for="name">Название</label>

                            @if (old('name')!="")
                                <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}"
                                       required>
                            @else
                                <input id="name" type="text" class="form-control" name="name" value="{{$course->name}}"
                                       required>
                            @endif
                            @if ($errors->has('name'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>
                        @if (Auth::user()->role=='admin')
                            <div class="mb-3">
                                <label for="mode" class="pb-2">Тип курса:</label><br>
                                <select class="form-select" id="mode" name="mode">
                                    <option value="private" @if($course->mode == 'private') selected @endif>Скрытый</option>
                                    <option value="offline" @if($course->mode == 'offline') selected @endif>Офлайн</option>
                                    <option value="zoom" @if($course->mode == 'zoom') selected @endif>Платный онлайн курс</option>
                                    <option value="paid" @if($course->mode == 'paid') selected @endif>Платный онлайн курс без преподавателя</option>
                                    <option value="open" @if($course->mode == 'open') selected @endif>Бесплатный онлайн курс</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="categories" class="pb-2">Образовательные
                                    направления:</label><br>
                                <select class="form-select" id="categories" name="categories[]" multiple>
                                    @foreach (\App\CourseCategory::all() as $category)
                                        <option value="{{ $category->id }}" @if($course->categories->contains($category->id)) selected @endif>{{$category->title}}</option>
                                    @endforeach
                                </select>
                            </div>

                        @endif

                        @if (Auth::user()->role=='admin')
                            <div class="mb-3">
                                <label for="teachers" class="pb-2">Учителя:</label><br>
                                <select class="form-select" id="teachers" name="teachers[]" multiple>
                                    @foreach (\App\User::where('role', 'teacher')->orWhere('role', 'admin')->get() as $teacher)
                                        <option value="{{ $teacher->id }}" @if($course->teachers->contains($teacher->id)) selected @endif>{{$teacher->name}}</option>
                                    @endforeach
                                </select>
                            </div>

                        @endif

                        @if ($course->state == 'draft')
                            <div class="mb-3">
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
                                    <span class="text-danger d-block">
                                        <strong>{{ $errors->first("start_date") }}</strong>
                                    </span>
                                @endif
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="students" class="pb-2">Студенты:</label><br>
                            <select class="form-select" id="students" name="students[]" multiple>
                                @foreach (\App\User::all() as $student)
                                    <option value="{{ $student->id }}" @if($course->students->contains($student->id)) selected @endif>{{$student->name}}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
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
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('invite') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="git">Git репозиторий</label>

                            @if (old('git')!="")
                                <input id="git" type="text" class="form-control" name="git" value="{{old('git')}}">
                            @else
                                <input id="git" type="text" class="form-control" name="git" value="{{$course->git}}"
                                >
                            @endif
                            @if ($errors->has('git'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('git') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="telegram">Telegram чат</label>

                            @if (old('telegram')!="")
                                <input id="telegram" type="text" class="form-control" name="telegram"
                                       value="{{old('telegram')}}">
                            @else
                                <input id="telegram" type="text" class="form-control" name="telegram"
                                       value="{{$course->telegram}}">
                            @endif
                            @if ($errors->has('telegram'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('telegram') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="site">Ссылка на описание курса</label>

                            @if (old('site')!="")
                                <input id="site" type="text" class="form-control" name="site" value="{{old('site')}}">
                            @else
                                <input id="site" type="text" class="form-control" name="site" value="{{$course->site}}"
                                >
                            @endif
                            @if ($errors->has('site'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('site') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
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
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('image') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="description">Описание</label>
                            <textarea id="description" class="form-control" name="description"
                                      required>@if (old('description')!=""){{old('description')}}@else{{$course->description}}@endif</textarea>
                            @if ($errors->has('description'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <hr>

                        <div class="mb-3">
                            <label for="weekdays">Дни недели</label>

                            @if (old('weekdays')!="")
                                <input id="weekdays" type="text" class="form-control" name="weekdays"
                                       value="{{old('weekdays')}}" placeholder="1;4">
                            @else
                                <input id="weekdays" type="text" class="form-control" name="weekdays"
                                       value="{{$course->weekdays}}" placeholder="1;4">
                            @endif
                            @if ($errors->has('weekdays'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('weekdays') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="import">Импорт</label>
                            <input id="import" type="file" class="form-control"
                                   name="import">

                            @if ($errors->has("import"))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first("import") }}</strong>
                                    </span>
                            @endif
                        </div>


                        <div class="form-actions form-actions--full">
                            <button type="submit" class="btn btn-success">Сохранить настройки</button>
                        </div>
                    </form>
        </div>
    </div>
@endsection
