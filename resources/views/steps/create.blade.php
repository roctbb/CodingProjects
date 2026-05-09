@extends('layouts.left-menu')

@section('title')
    Создание ступени
@endsection

@section('content')
    <div class="container-xl px-0">
        <div class="gc-card gc-page-header mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id.'?chapter='.optional($lesson->chapter)->id) }}"><i class="icon ion-chevron-left"></i> К курсу</a>
                <h2 class="fw-bold lh-sm mb-1">Создание этапа</h2>
                <p class="mb-0 text-muted text-truncate">{{ $lesson->name }}</p>
            </div>
        </div>

        <form id="step-create-form" method="POST">
            {{ csrf_field() }}

            <div class="row g-3 align-items-start">
                <div class="col-12 col-lg-8 d-grid gap-3">
            @if ($is_lesson)
                <div class="gc-card overflow-hidden">
                    <div class="gc-section-header">
                        <h5 class="mb-1">Урок</h5>
                        <p class="text-muted small mb-0">Базовая информация для нового занятия.</p>
                    </div>
                    <div class="p-3 p-md-4">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="lesson_name" class="form-label">Название урока</label>
                                <input id="lesson_name" type="text" class="form-control rounded-3" value="{{old('lesson_name')}}"
                                       name="lesson_name"
                                       required>

                                @if ($errors->has('lesson_name'))
                                    <span class="text-danger small d-block mt-1">
                                        <strong>{{ $errors->first('lesson_name') }}</strong>
                                    </span>
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Дата начала</label>
                                <input id="start_date" type="text" class="form-control rounded-3 date"
                                       value="{{old("start_date")}}" name="start_date"
                                       required>

                                @if ($errors->has("start_date"))
                                    <span class="text-danger small d-block mt-1">
                                        <strong>{{ $errors->first("start_date") }}</strong>
                                    </span>
                                @endif
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label">Описание урока</label>
                                <textarea id="description" class="form-control rounded-3" data-markdown-editor
                                          name="description">{{old('description')}}</textarea>

                                @if ($errors->has('description'))
                                    <span class="text-danger small d-block mt-1">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="gc-card overflow-hidden">
                <div class="gc-section-header">
                    <h5 class="mb-1">Материал этапа</h5>
                    <p class="text-muted small mb-0">То, что увидят ученики на странице занятия.</p>
                </div>
                <div class="p-3 p-md-4">
                    <div class="mb-3">
                        <label for="name" class="form-label">Название этапа</label>
                        <input id="name" type="text" class="form-control rounded-3" value="{{old('name')}}" name="name" required>

                        @if ($errors->has('name'))
                            <span class="text-danger small d-block mt-1">
                                <strong>{{ $errors->first('name') }}</strong>
                            </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="theory" class="form-label">Теоретический материал</label>
                        <textarea id="theory" class="form-control rounded-3" name="theory" data-markdown-editor rows="20">{{old('theory')}}</textarea>

                        @if ($errors->has('theory'))
                            <span class="text-danger small d-block mt-1">
                                <strong>{{ $errors->first('theory') }}</strong>
                            </span>
                        @endif
                    </div>

                    <div class="mb-0">
                        <label for="notes" class="form-label">Комментарий для преподавателя</label>
                        <textarea id="notes" class="form-control rounded-3" data-markdown-editor name="notes">{{old('notes')}}</textarea>
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
                    <p class="text-muted small mb-0">Служебные параметры этапа.</p>
                </div>
                <div class="p-3 p-md-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label for="video_url" class="form-label">Видео</label>
                            <input id="video_url" type="text" class="form-control rounded-3" value="{{old('video_url')}}" name="video_url">

                            @if ($errors->has('video_url'))
                                <span class="text-danger small d-block mt-1">
                                    <strong>{{ $errors->first('video_url') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="col-md-4">
                            <label class="gc-switch-card form-check form-switch">
                                <input type="checkbox" class="form-check-input ms-0 me-2" name="notebook" value="yes" @if(old('notebook') == 'yes') checked @endif>
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
                                <h5 class="mb-1">Новый этап</h5>
                                <p class="mb-0 text-muted small text-truncate">{{ $course->name }}</p>
                            </div>
                        </div>
                        <div class="d-grid gap-2 mb-3">
                            <div class="gc-info-tile"><span>Урок</span><strong>{{ $lesson->name }}</strong></div>
                            <div class="gc-info-tile"><span>Глава</span><strong>{{ optional($lesson->chapter)->name }}</strong></div>
                            <div class="gc-info-tile"><span>Материал</span><strong>Теория и заметки</strong></div>
                        </div>
                        <button type="submit" form="step-create-form" class="btn btn-success rounded-3 fw-semibold w-100">Создать этап</button>
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
