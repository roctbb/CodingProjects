@extends('layouts.left-menu')

@section('title')
    Создание ступени
@endsection

@section('content')
    <div class="cp-step-form-page">
    <h2 class="cp-heading-lite">Создание ступени</h2>

    <div class="row cp-row-gap-top">
        <div class="col-12 col-xl-10">
            <div class="card cp-form-card">
                <div class="card-body">
                    <form method="POST" class="vstack gap-3">
                        {{ csrf_field() }}

                        @if ($is_lesson)
                            <div class="mb-3">
                                <label for="lesson_name">Название урока</label>

                                <input id="lesson_name" type="text" class="form-control" value="{{old('lesson_name')}}"
                                       name="lesson_name"
                                       required>

                                @if ($errors->has('lesson_name'))
                                    <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('lesson_name') }}</strong>
                                    </span>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label for="description" class="cp-label-spaced">Описание урока</label>

                                <textarea id="description" class="form-control"
                                          name="description">{{old('description')}}</textarea>

                                @if ($errors->has('description'))
                                    <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label for="start_date">Дата начала</label>

                                <input id="start_date" type="text" class="form-control date"
                                       value="{{old("start_date")}}" name="start_date"
                                       required>

                                @if ($errors->has("start_date"))
                                    <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first("start_date") }}</strong>
                                    </span>
                                @endif
                            </div>

                        @endif

                        <div class="mb-3">
                            <label for="name">Название этапа</label>

                            <input id="name" type="text" class="form-control" value="{{old('name')}}" name="name"
                                   required>

                            @if ($errors->has('name'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="theory">Теоретический материал</label>
                            <textarea id="theory" class="form-control" name="theory"
                                      rows="20">{{old('theory')}}</textarea>

                            @if ($errors->has('theory'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('theory') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="cp-label-spaced">Комментарий для преподавателя</label>
                            <textarea id="notes" class="form-control"
                                      name="notes">{{old('notes')}}</textarea>
                            @if ($errors->has('notes'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('notes') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label for="video_url">Видео</label>

                            <input id="video_url" type="text" class="form-control" value="{{old('video_url')}}"
                                   name="video_url">

                            @if ($errors->has('video_url'))
                                <span class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('video_url') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="notebook" name="notebook" value="yes">
                            <label class="form-check-label" for="notebook">Это тетрадка</label>
                        </div>
                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">Создать</button>
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
    </div>
@endsection
