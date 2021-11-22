@extends('layouts.left-menu')

@section('content')
    <h2>Создание ступени</h2>

    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="form-horizontal">
                        {{ csrf_field() }}

                        @if ($is_lesson)
                            <div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">
                                <label for="lesson_name">Название урока</label>

                                <input id="lesson_name" type="text" class="form-control" value="{{old('lesson_name')}}"
                                       name="lesson_name"
                                       required>

                                @if ($errors->has('lesson_name'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('lesson_name') }}</strong>
                                    </span>
                                @endif
                            </div>
                            <div class="form-group">
                                <label for="description" style="padding-bottom: 10px;">Описание урока</label>

                                <textarea id="description" class="materialize-textarea"
                                          name="description">{{old('description')}}</textarea>

                                @if ($errors->has('description'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                                @endif
                            </div>
                            <div class="form-group{{ $errors->has("start_date") ? ' has-error' : '' }}">
                                <label for="start_date">Дата начала</label>

                                <input id="start_date" type="text" class="form-control date"
                                       value="{{old("start_date")}}" name="start_date"
                                       required>

                                @if ($errors->has("start_date"))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first("start_date") }}</strong>
                                    </span>
                                @endif
                            </div>

                        @endif

                        <div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">
                            <label for="name">Название этапа</label>

                            <input id="name" type="text" class="form-control" value="{{old('name')}}" name="name"
                                   required>

                            @if ($errors->has('name'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group{{ $errors->has('theory') ? ' has-error' : '' }}">
                            <label for="theory">Теоретический материал</label>
                            <textarea id="theory" class="form-control" name="theory"
                                      rows="20">{{old('theory')}}</textarea>

                            @if ($errors->has('theory'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('theory') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="notes" style="padding-bottom: 10px;">Комментарий для преподавателя</label>
                            <textarea id="notes" class="form-control"
                                      name="notes">{{old('notes')}}</textarea>
                            @if ($errors->has('notes'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('notes') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-group{{ $errors->has('video_url') ? ' has-error' : '' }}">
                            <label for="video_url">Видео</label>

                            <input id="video_url" type="text" class="form-control" value="{{old('video_url')}}"
                                   name="video_url">

                            @if ($errors->has('video_url'))
                                <span class="help-block error-block">
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
                            <div class="col-md-8 col-md-offset-4">
                                <button type="submit" class="btn btn-success">Создать</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <script>
                var simplemde_description = new EasyMDE({
                    spellChecker: false,
                    element: document.getElementById("description")
                });
                var simplemde_theory = new EasyMDE({spellChecker: false, element: document.getElementById("theory")});
                var simplemde_notes = new EasyMDE({spellChecker: false, element: document.getElementById("notes")});
            </script>
        </div>
    </div>
@endsection
