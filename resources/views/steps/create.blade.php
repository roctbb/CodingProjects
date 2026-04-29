@extends('layouts.left-menu')

@section('title')
    Создание ступени
@endsection

@section('content')
    <h2>Создание ступени</h2>

    <div class="row mt-3">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        {{ csrf_field() }}

                        @if ($is_lesson)
                            <div class="form-group">
                                <label for="lesson_name">Название урока</label>

                                <input id="lesson_name" type="text" class="form-control" value="{{old('lesson_name')}}"
                                       name="lesson_name"
                                       required>

                                @if ($errors->has('lesson_name'))
                                    <span class="text-danger d-block">
                                        <strong>{{ $errors->first('lesson_name') }}</strong>
                                    </span>
                                @endif
                            </div>
                            <div class="form-group">
                                <label for="description" class="pb-2">Описание урока</label>

                                <textarea id="description" class="form-control" data-markdown-editor
                                          name="description">{{old('description')}}</textarea>

                                @if ($errors->has('description'))
                                    <span class="text-danger d-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                                @endif
                            </div>
                            <div class="form-group">
                                <label for="start_date">Дата начала</label>

                                <input id="start_date" type="text" class="form-control date"
                                       value="{{old("start_date")}}" name="start_date"
                                       required>

                                @if ($errors->has("start_date"))
                                    <span class="text-danger d-block">
                                        <strong>{{ $errors->first("start_date") }}</strong>
                                    </span>
                                @endif
                            </div>

                        @endif

                        <div class="form-group">
                            <label for="name">Название этапа</label>

                            <input id="name" type="text" class="form-control" value="{{old('name')}}" name="name"
                                   required>

                            @if ($errors->has('name'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="theory">Теоретический материал</label>
                            <textarea id="theory" class="form-control" name="theory" data-markdown-editor
                                      rows="20">{{old('theory')}}</textarea>

                            @if ($errors->has('theory'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('theory') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="notes" class="pb-2">Комментарий для преподавателя</label>
                            <textarea id="notes" class="form-control" data-markdown-editor
                                      name="notes">{{old('notes')}}</textarea>
                            @if ($errors->has('notes'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('notes') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="video_url">Видео</label>

                            <input id="video_url" type="text" class="form-control" value="{{old('video_url')}}"
                                   name="video_url">

                            @if ($errors->has('video_url'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('video_url') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input" name="notebook" value="yes">
                                Это тетрадка
                            </label>
                        </div>
                        <div class="form-group">
                            <div class="col-md-8 offset-md-4">
                                <button type="submit" class="btn btn-success">Создать</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
