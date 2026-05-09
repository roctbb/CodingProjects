@extends('layouts.left-menu')

@section('title')
    Изменение урока
@endsection

@section('content')
    <div class="container-xl px-0">
        <div class="gc-card gc-page-header mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id) }}"><i class="icon ion-chevron-left"></i> К курсу</a>
                <h2 class="fw-bold lh-sm mb-1">Изменение урока</h2>
                <p class="mb-0 text-muted text-truncate">{{$lesson->name}}</p>
            </div>
        </div>

        <div class="row g-3 align-items-start">
            <div class="col-12 col-lg-8">
                <div class="gc-card overflow-hidden">
                    <div class="gc-section-header">
                        <h5 class="mb-1">Основное</h5>
                        <p class="text-muted small mb-0">Содержание, глава и параметры урока.</p>
                    </div>
                    <form id="lesson-edit-form" method="POST" enctype="multipart/form-data" class="p-3 p-md-4">
                    {{ csrf_field() }}
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="name" class="form-label">Название</label>
                            <input id="name" type="text" class="form-control rounded-3" value="{{old('name', $lesson->name)}}"
                                   name="name" required>
                            @if ($errors->has('name'))
                                <span class="text-danger small d-block mt-1">
                                    <strong>{{ $errors->first('name') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Дата начала</label>
                            <input id="start_date" type="text" class="form-control rounded-3 date"
                                   value="{{old('start_date', $lesson->getStartDate($course) ? $lesson->getStartDate($course)->format('Y-m-d') : '')}}"
                                   name="start_date">
                            @if ($errors->has("start_date"))
                                <span class="text-danger small d-block mt-1">
                                    <strong>{{ $errors->first("start_date") }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="col-12">
                            <label for="chapter" class="form-label">Глава</label>
                            <select id="chapter" class="form-select rounded-3" name="chapter">
                                @foreach($lesson->program->chapters as $chapter)
                                    <option value="{{$chapter->id}}"
                                            @if ($chapter->id==old('chapter', $lesson->chapter->id)) selected @endif>{{$chapter->name}}</option>
                                @endforeach
                            </select>
                            @if ($errors->has('chapter'))
                                <span class="text-danger small d-block mt-1">
                                    <strong>{{ $errors->first('chapter') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="col-12">
                            <div class="lesson-access-grid">
                                <label class="gc-switch-card form-check form-switch">
                                    <input type="checkbox" class="form-check-input ms-0 me-2" id="open" name="open" value="yes"
                                           @if (old('open', $lesson->is_open ? 'yes' : '') == 'yes') checked @endif>
                                    <span class="form-check-label">Открытый урок</span>
                                </label>

                                <label class="gc-switch-card form-check form-switch">
                                    <input type="checkbox" class="form-check-input ms-0 me-2" id="early_access_enabled" name="early_access_enabled" value="on"
                                           @if (old('early_access_enabled', $lesson->early_access_enabled ? 'on' : '') == 'on') checked @endif>
                                    <span class="form-check-label">Доступен для раннего доступа</span>
                                </label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">Описание</label>
                            <textarea id="description" class="form-control rounded-3" data-markdown-editor data-markdown-autosave="true"
                                      name="description">{{old('description', $lesson->description)}}</textarea>
                            @if ($errors->has('description'))
                                <span class="text-danger small d-block mt-1">
                                    <strong>{{ $errors->first('description') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="col-12">
                            <label for="import" class="form-label">Импорт</label>
                            <input id="import" type="file" class="form-control rounded-3"
                               name="import">
                            @if ($errors->has("import"))
                                <span class="text-danger small d-block mt-1">
                                    <strong>{{ $errors->first("import") }}</strong>
                                </span>
                            @endif
                        </div>
                    </div>

                </form>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <aside class="gc-card course-create-aside p-3 p-md-4 sticky-lg-top">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-layer-group"></i></span>
                        <div class="min-width-0">
                            <h5 class="mb-1">Состояние урока</h5>
                            <p class="mb-0 text-muted small text-truncate">{{ $course->name }}</p>
                        </div>
                    </div>
                    <div class="d-grid gap-2 mb-3">
                        <div class="gc-info-tile"><span>Глава</span><strong>{{ optional($lesson->chapter)->name }}</strong></div>
                        <div class="gc-info-tile"><span>Дата начала</span><strong>{{ $lesson->getStartDate($course) ? $lesson->getStartDate($course)->format('Y-m-d') : 'Не задана' }}</strong></div>
                        <div class="gc-info-tile"><span>Доступ</span><strong>{{ $lesson->is_open ? 'Открытый урок' : 'Только в курсе' }}</strong></div>
                        <div class="gc-info-tile"><span>Ранний доступ</span><strong>{{ $lesson->early_access_enabled ? 'Разрешен' : 'Выключен' }}</strong></div>
                    </div>
                    <button type="submit" form="lesson-edit-form" class="btn btn-success rounded-3 fw-semibold w-100">Сохранить</button>
                </aside>
            </div>
        </div>
    </div>
@endsection
@push('editor')
<script type="text/javascript" src="{{ asset('build/js/vendor/easymde.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('build/js/easymde-bridge.js') }}"></script>
@endpush
