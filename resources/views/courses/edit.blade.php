@extends('layouts.left-menu')

@section('title')
    Изменение курса "{{$course->name}}"
@endsection

@section('content')
    <div class="cp-course-form-page">
    <h2 class="cp-heading-lite">Изменение курса "{{$course->name}}"</h2>
    <div class="row cp-row-gap-top">
        <div class="col-12">
            <div class="card cp-form-card">
                <div class="card-body">
                    <form method="POST" class="vstack gap-3" enctype="multipart/form-data">
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
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>
                        @php
                            $selectedMode = old('mode', $course->mode);
                            $selectedCategoryIds = array_map(
                                'strval',
                                (array) old('categories', $course->categories->pluck('id')->toArray())
                            );
                            $selectedTeacherIds = array_map(
                                'strval',
                                (array) old('teachers', $course->teachers->pluck('id')->toArray())
                            );
                            $selectedStudentIds = array_map(
                                'strval',
                                (array) old('students', $course->students->pluck('id')->toArray())
                            );
                        @endphp
                        @if (Auth::user()->role=='admin')
                            <div class="mb-3">
                                <label for="mode" class="cp-label-spaced">Тип курса:</label>
                                <select class="form-select" id="mode" name="mode">
                                    <option data-tokens="private" value="private" @if ($selectedMode === 'private') selected @endif>Скрытый</option>
                                    <option data-tokens="offline" value="offline" @if ($selectedMode === 'offline') selected @endif>Офлайн</option>
                                    <option data-tokens="zoom" value="zoom" @if ($selectedMode === 'zoom') selected @endif>Платный онлайн курс</option>
                                    <option data-tokens="paid" value="paid" @if ($selectedMode === 'paid') selected @endif>Платный онлайн курс без преподавателя</option>
                                    <option data-tokens="open" value="open" @if ($selectedMode === 'open') selected @endif>Бесплатный онлайн курс</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="categories" class="cp-label-spaced">Образовательные
                                    направления:</label>
                                <select class="form-select" id="categories" name="categories[]" multiple>
                                    @foreach (\App\CourseCategory::all() as $category)
                                        <option data-tokens="{{ $category->id }}"
                                                @if (in_array((string) $category->id, $selectedCategoryIds, true)) selected @endif
                                                value="{{ $category->id }}">{{$category->title}}</option>
                                    @endforeach
                                </select>
                            </div>

                        @endif

                        @if (Auth::user()->role=='admin')
                            <div class="mb-3">
                                <label for="teachers" class="cp-label-spaced">Учителя:</label>
                                <select class="form-select" id="teachers" name="teachers[]" multiple>
                                    @foreach (\App\User::where('role', 'teacher')->orWhere('role', 'admin')->get() as $teacher)
                                        <option data-tokens="{{ $teacher->id }}"
                                                @if (in_array((string) $teacher->id, $selectedTeacherIds, true)) selected @endif
                                                value="{{ $teacher->id }}">{{$teacher->name}}</option>
                                    @endforeach
                                </select>
                            </div>

                        @endif

                        @if ($course->state == 'draft')
                            <div class="mb-3{{ $errors->has("start_date") ? ' is-invalid' : '' }}">
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
                                    <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first("start_date") }}</strong>
                                    </span>
                                @endif
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="students" class="cp-label-spaced">Студенты:</label>
                            <select class="form-select" id="students" name="students[]" multiple>
                                @foreach (\App\User::all() as $student)
                                    <option data-tokens="{{ $student->id }}"
                                            @if (in_array((string) $student->id, $selectedStudentIds, true)) selected @endif
                                            value="{{ $student->id }}">{{$student->name}}</option>
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
                                <span class="invalid-feedback d-block">
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
                                <span class="invalid-feedback d-block">
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
                                <span class="invalid-feedback d-block">
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
                                <span class="invalid-feedback d-block">
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
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('image') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="description">Описание</label>
                            <textarea id="description" class="form-control" name="description"
                                      required>@if (old('description')!=""){{old('description')}}@else{{$course->description}}@endif</textarea>
                            @if ($errors->has('description'))
                                <span class="invalid-feedback d-block">
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
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('weekdays') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3{{ $errors->has("import") ? ' is-invalid' : '' }}">
                            <label for="import">Импорт</label>
                            <input id="import" type="file" class="form-control"
                                   name="import">

                            @if ($errors->has("import"))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first("import") }}</strong>
                                    </span>
                            @endif
                        </div>


                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
