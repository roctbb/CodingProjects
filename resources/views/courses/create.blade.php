@extends('layouts.left-menu')

@section('title')
    Добавление курса
@endsection

@section('content')
    <div class="container-xl px-0">
        <div class="gc-card gc-page-header mb-3">
            <div>
                <a class="assessment-back-link" href="{{ url('/insider/courses') }}"><i class="icon ion-chevron-left"></i> К курсам</a>
                <h2 class="mb-1">Создание курса</h2>
                <p class="mb-0 text-muted">Задайте основу курса. Программу, учеников и материалы можно настроить после создания.</p>
            </div>
        </div>

        <div class="row g-3 align-items-start">
            <div class="col-12 col-lg-8">
                <div class="gc-card overflow-hidden">
                    <form method="POST" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <div class="p-3 p-md-4">
                            <div class="mb-3">
                                <h5 class="mb-1">Основа курса</h5>
                                <p class="mb-0 text-muted small">Эти поля появятся в карточке курса и в рабочей области учеников.</p>
                            </div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="name" class="form-label">Название</label>
                                    <input id="name" type="text" class="form-control rounded-3" name="name" value="{{old('name')}}" required>
                                    @error('name')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label for="program" class="form-label">Программа</label>
                                    <select id="program" class="form-select rounded-3" name="program" required>
                                        <option value="-1">Новая программа на основе курса</option>
                                        @foreach($programs as $program)
                                            <option value="{{$program->id}}">{{$program->name}}</option>
                                        @endforeach
                                    </select>
                                    @error('program')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                    <div class="form-text">Можно создать новую программу или взять структуру существующей.</div>
                                </div>

                                <div class="col-12">
                                    <label for="description" class="form-label">Описание</label>
                                    <textarea id="description" class="form-control rounded-3" name="description" rows="5" required>{{old('description')}}</textarea>
                                    @error('description')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="gc-form-footer justify-content-end gap-2">
                            <button type="submit" class="btn btn-success rounded-3 fw-semibold px-4">Создать курс</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="gc-card course-create-aside p-3 p-md-4 sticky-lg-top">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-route"></i></span>
                        <div>
                            <h5 class="mb-1">После создания</h5>
                            <p class="mb-0 text-muted small">Курс стартует как черновик.</p>
                        </div>
                    </div>
                    <ul class="course-create-steps list-unstyled mb-0 d-grid gap-2">
                        <li><span>1</span><strong>Добавьте уроки и главы</strong></li>
                        <li><span>2</span><strong>Настройте учеников и преподавателей</strong></li>
                        <li><span>3</span><strong>Откройте курс, когда материалы готовы</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
