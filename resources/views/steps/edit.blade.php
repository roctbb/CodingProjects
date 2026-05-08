@extends('layouts.left-menu')

@section('title')
    Изменение темы: "{{$step->name}}"
@endsection

@section('content')
    <div class="form-page">
        <div class="form-page-header gc-card mb-3">
            <div>
                <h2 class="mb-1">Изменение темы</h2>
                <p class="mb-0 text-muted text-truncate">{{$step->name}}</p>
            </div>
        </div>

        <div class="form-layout">
            <div class="gc-card form-card form-card--wide">
                    <form method="POST" class="form-stack">
                        {{ csrf_field() }}
                        <div class="mb-3">
                            <label for="name" class="form-label">Название</label>

                            @if (old('name')!="")
                                <input id="name" type="text" class="form-control" value="{{old('name')}}"
                                       name="name" required>
                            @else
                                <input id="name" type="text" class="form-control" value="{{$step->name}}"
                                       name="name" required>
                            @endif
                            @if ($errors->has('name'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label for="theory" class="form-label pb-2">Теоретический материал</label>
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-improve-text="fix_typos" data-field-id="theory">
                                    <i class="icon ion-android-checkbox-outline"></i> Исправить опечатки
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info" data-improve-text="improve_style" data-field-id="theory">
                                    <i class="icon ion-android-create"></i> Улучшить стиль
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" data-improve-text="both" data-field-id="theory">
                                    <i class="icon ion-android-star"></i> Исправить и улучшить
                                </button>
                            </div>
                            @if (old('theory')!="")
                                <textarea id="theory" class="form-control" data-markdown-editor
                                          name="theory">{{old('theory')}}</textarea>
                            @else
                                <textarea id="theory" class="form-control" data-markdown-editor
                                          name="theory">{{$step->theory}}</textarea>
                            @endif

                            @if ($errors->has('theory'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('theory') }}</strong>
                                    </span>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label pb-2">Комментарий для преподавателя</label>
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-improve-text="fix_typos" data-field-id="notes">
                                    <i class="icon ion-android-checkbox-outline"></i> Исправить опечатки
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info" data-improve-text="improve_style" data-field-id="notes">
                                    <i class="icon ion-android-create"></i> Улучшить стиль
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" data-improve-text="both" data-field-id="notes">
                                    <i class="icon ion-android-star"></i> Исправить и улучшить
                                </button>
                            </div>
                            @if (old('notes')!="")
                                <textarea id="notes" class="form-control" data-markdown-editor
                                          name="notes">{{old('notes')}}</textarea>
                            @else
                                <textarea id="notes" class="form-control" data-markdown-editor
                                          name="notes">{{$step->notes}}</textarea>
                            @endif

                            @if ($errors->has('notes'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('notes') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input" name="notebook" value="yes"
                                       @if ($step->is_notebook) checked @endif>
                                Это тетрадка
                            </label>
                        </div>

                        <div class="mb-3">
                            <label for="video_url" class="form-label">Видео</label>

                            @if (old('video_url')!="")
                                <input id="video_url" type="text" class="form-control" value="{{old('video_url')}}"
                                       name="video_url">
                            @else
                                <input id="video_url" type="text" class="form-control" value="{{$step->video_url}}"
                                       name="video_url">
                            @endif
                            @if ($errors->has('video_url'))
                                <span class="text-danger d-block">
                                        <strong>{{ $errors->first('video_url') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">Сохранить</button>
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
