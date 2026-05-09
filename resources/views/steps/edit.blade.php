@extends('layouts.left-menu')

@section('title')
    Изменение темы: "{{$step->name}}"
@endsection

@section('content')
    <div class="container-xl px-0">
        <div class="gc-card gc-page-header mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id) }}"><i class="icon ion-chevron-left"></i> К этапу</a>
                <h2 class="fw-bold lh-sm mb-1">Изменение темы</h2>
                <p class="mb-0 text-muted text-truncate">{{$step->lesson->name}}</p>
            </div>
        </div>

        <form id="step-edit-form" method="POST">
            {{ csrf_field() }}

            <div class="row g-3 align-items-start">
                <div class="col-12 col-lg-8 d-grid gap-3">
            <div class="gc-card overflow-hidden">
                <div class="gc-section-header">
                    <h5 class="mb-1">Материал этапа</h5>
                    <p class="text-muted small mb-0">Основной текст и заметки для преподавателя.</p>
                </div>
                <div class="p-3 p-md-4">
                    <div class="mb-3">
                        <label for="name" class="form-label">Название</label>
                        <input id="name" type="text" class="form-control rounded-3" value="{{old('name', $step->name)}}"
                               name="name" required>
                        @if ($errors->has('name'))
                            <span class="text-danger small d-block mt-1">
                                <strong>{{ $errors->first('name') }}</strong>
                            </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
                            <label for="theory" class="form-label mb-0">Теоретический материал</label>
                            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Улучшение теории">
                                <button type="button" class="btn btn-outline-primary rounded-start-3" data-improve-text="fix_typos" data-field-id="theory">
                                    <i class="icon ion-android-checkbox-outline"></i> Опечатки
                                </button>
                                <button type="button" class="btn btn-outline-info" data-improve-text="improve_style" data-field-id="theory">
                                    <i class="icon ion-android-create"></i> Стиль
                                </button>
                                <button type="button" class="btn btn-outline-secondary rounded-end-3" data-improve-text="both" data-field-id="theory">
                                    <i class="icon ion-android-star"></i> Всё
                                </button>
                            </div>
                        </div>
                        <textarea id="theory" class="form-control rounded-3" data-markdown-editor
                                  name="theory">{{old('theory', $step->theory)}}</textarea>

                        @if ($errors->has('theory'))
                            <span class="text-danger small d-block mt-1">
                                <strong>{{ $errors->first('theory') }}</strong>
                            </span>
                        @endif
                    </div>

                    <div class="mb-0">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
                            <label for="notes" class="form-label mb-0">Комментарий для преподавателя</label>
                            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Улучшение комментария">
                                <button type="button" class="btn btn-outline-primary rounded-start-3" data-improve-text="fix_typos" data-field-id="notes">
                                    <i class="icon ion-android-checkbox-outline"></i> Опечатки
                                </button>
                                <button type="button" class="btn btn-outline-info" data-improve-text="improve_style" data-field-id="notes">
                                    <i class="icon ion-android-create"></i> Стиль
                                </button>
                                <button type="button" class="btn btn-outline-secondary rounded-end-3" data-improve-text="both" data-field-id="notes">
                                    <i class="icon ion-android-star"></i> Всё
                                </button>
                            </div>
                        </div>
                        <textarea id="notes" class="form-control rounded-3" data-markdown-editor
                                  name="notes">{{old('notes', $step->notes)}}</textarea>

                        @if ($errors->has('notes'))
                            <span class="text-danger small d-block mt-1">
                                <strong>{{ $errors->first('notes') }}</strong>
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="gc-card overflow-hidden">
                <div class="gc-section-header">
                    <h5 class="mb-1">Настройки</h5>
                    <p class="text-muted small mb-0">Видео и режим отображения материала.</p>
                </div>
                <div class="p-3 p-md-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label for="video_url" class="form-label">Видео</label>
                            <input id="video_url" type="text" class="form-control rounded-3" value="{{old('video_url', $step->video_url)}}"
                                   name="video_url">

                            @if ($errors->has('video_url'))
                                <span class="text-danger small d-block mt-1">
                                    <strong>{{ $errors->first('video_url') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="col-md-4">
                            <label class="gc-switch-card form-check form-switch">
                                <input type="checkbox" class="form-check-input ms-0 me-2" name="notebook" value="yes"
                                       @if (old('notebook', $step->is_notebook ? 'yes' : '') == 'yes') checked @endif>
                                <span class="form-check-label">Это тетрадка</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

                </div>

                <div class="col-12 col-lg-4">
                    <aside class="gc-card course-create-aside p-3 p-md-4 sticky-lg-top">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-file-alt"></i></span>
                            <div class="min-width-0">
                                <h5 class="mb-1">Этап</h5>
                                <p class="mb-0 text-muted small text-truncate">{{ $step->lesson->name }}</p>
                            </div>
                        </div>
                        <div class="d-grid gap-2 mb-3">
                            <div class="gc-info-tile"><span>Название</span><strong>{{ $step->name }}</strong></div>
                            <div class="gc-info-tile"><span>Задачи</span><strong>{{ $step->tasks->count() }}</strong></div>
                            <div class="gc-info-tile"><span>Режим</span><strong>{{ $step->is_notebook ? 'Тетрадка' : 'Обычный материал' }}</strong></div>
                        </div>
                        <button type="submit" form="step-edit-form" class="btn btn-success rounded-3 fw-semibold w-100">Сохранить</button>
                    </aside>
                </div>
            </div>
        </form>
    </div>
@endsection
@push('editor')
<script type="text/javascript" src="{{ asset('build/js/vendor/easymde.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('build/js/easymde-bridge.js') }}"></script>
@endpush
