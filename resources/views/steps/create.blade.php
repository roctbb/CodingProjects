@extends('layouts.left-menu')

@section('title')
    Создание ступени
@endsection

@section('content')
    <div class="form-page">
        <div class="form-page-header gc-card mb-3">
            <div>
                <h2 class="mb-1">Создание ступени</h2>
                <p class="mb-0 text-muted">Добавьте теорию, заметки преподавателя и служебные настройки.</p>
            </div>
        </div>

        <div class="form-layout">
            <div class="gc-card form-card form-card--wide">
                    <form method="POST" class="form-stack">
                        {{ csrf_field() }}

                        @if ($is_lesson)
                            <div class="mb-3">
                                <label for="lesson_name" class="form-label">Название урока</label>

                                <input id="lesson_name" type="text" class="form-control" value="{{old('lesson_name')}}"
                                       name="lesson_name"
                                       required>

                                @if ($errors->has('lesson_name'))
                                    <span class="text-danger d-block">
                                        <strong>{{ $errors->first('lesson_name') }}</strong>
                                    </span>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label pb-2">Описание урока</label>

                                <textarea id="description" class="form-control" data-markdown-editor
                                          name="description">{{old('description')}}</textarea>

                                @if ($errors->has('description'))
                                    <span class="text-danger d-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Дата начала</label>

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

                        <div class="mb-3">
                            <label for="name" class="form-label">Название этапа</label>

                            <input id="name" type="text" class="form-control" value="{{old('name')}}" name="name"
                                   required>

                            @if ($errors->has('name'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="theory" class="form-label">Теоретический материал</label>
                            <textarea id="theory" class="form-control" name="theory" data-markdown-editor
                                      rows="20">{{old('theory')}}</textarea>

                            @if ($errors->has('theory'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('theory') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label pb-2">Комментарий для преподавателя</label>
                            <textarea id="notes" class="form-control" data-markdown-editor
                                      name="notes">{{old('notes')}}</textarea>
                            @if ($errors->has('notes'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('notes') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label for="video_url" class="form-label">Видео</label>

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
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">Создать</button>
                        </div>
                    </form>
            </div>
        </div>
    </div>
@endsection
@push('editor')
<script type="text/javascript" src="{{ asset('build/js/vendor/easymde.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('build/js/easymde-bridge.js') }}"></script>
@endpush
