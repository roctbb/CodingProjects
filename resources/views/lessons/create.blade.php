@extends('layouts.left-menu')

@section('title')
    Создание урока
@endsection

@section('content')
    <div class="container-xl px-0">
        <div class="gc-card gc-page-header mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id) }}"><i class="icon ion-chevron-left"></i> К курсу</a>
                <h2 class="fw-bold lh-sm mb-1">Создание урока</h2>
                <p class="mb-0 text-muted text-truncate">{{ $course->name }}</p>
            </div>
        </div>

        <div class="row g-3 align-items-start">
            <div class="col-12 col-lg-8">
                <div class="gc-card overflow-hidden">
                    <div class="gc-section-header">
                        <h5 class="mb-1">Основное</h5>
                        <p class="text-muted small mb-0">Название и описание, которые увидят ученики.</p>
                    </div>
                    <form id="lesson-create-form" method="POST" class="p-3 p-md-4">
                    {{ csrf_field() }}
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="name" class="form-label">Название урока</label>
                            <input id="name" type="text" class="form-control rounded-3" value="{{old('name')}}"
                                   name="name" required>
                            @if ($errors->has('name'))
                                <span class="text-danger small d-block mt-1"><strong>{{ $errors->first('name') }}</strong></span>
                            @endif
                        </div>

                        <div class="col-md-5">
                            <label for="chapter" class="form-label">Глава</label>
                            <select id="chapter" class="form-select rounded-3" name="chapter" required>
                                @foreach($course->program->chapters as $chapter)
                                    <option value="{{$chapter->id}}" @if ($chapter->id == old('chapter', $course->default_chapter_id ?? optional($course->program->chapters->first())->id)) selected @endif>{{$chapter->name}}</option>
                                @endforeach
                            </select>
                            @if ($errors->has('chapter'))
                                <span class="text-danger small d-block mt-1"><strong>{{ $errors->first('chapter') }}</strong></span>
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

                        <div class="col-12">
                            <label class="gc-switch-card form-check form-switch">
                                <input type="checkbox" class="form-check-input ms-0 me-2" id="early_access_enabled" name="early_access_enabled" value="on"
                                       @if (old('early_access_enabled') == 'on') checked @endif>
                                <span class="form-check-label">Доступен для раннего доступа</span>
                            </label>
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
                            <h5 class="mb-1">Новый урок</h5>
                            <p class="mb-0 text-muted small text-truncate">{{ $course->name }}</p>
                        </div>
                    </div>
                    <div class="d-grid gap-2 mb-3">
                        <div class="gc-info-tile"><span>Статус</span><strong>Черновик</strong></div>
                        <div class="gc-info-tile"><span>Первый этап</span><strong>Введение</strong></div>
                    </div>
                    <button type="submit" form="lesson-create-form" class="btn btn-success rounded-3 fw-semibold w-100">Создать урок</button>
                </aside>
            </div>
        </div>
    </div>
@endsection
@push('editor')
<script type="text/javascript" src="{{ asset('build/js/vendor/easymde.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('build/js/easymde-bridge.js') }}"></script>
@endpush
