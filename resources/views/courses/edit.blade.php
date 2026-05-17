@extends('layouts.left-menu')

@section('title')
    Изменение курса "{{$course->name}}"
@endsection

@section('content')
    @php
        $selectedCategoryIds = collect(old('categories', $course->categories->pluck('id')->all()))->map(fn ($id) => (int) $id);
        $selectedTeacherIds = collect(old('teachers', $course->teachers->pluck('id')->all()))->map(fn ($id) => (int) $id);
        $selectedStudentIds = collect(old('students', $course->students->pluck('id')->all()))->map(fn ($id) => (int) $id);
        $courseStartDate = $course->start_date ? $course->start_date->format('Y-m-d') : '';
        $coursePosterUrl = $course->learningAvatarPosterUrl();
        $programPosterGeneratedAt = optional($course->program)->learning_avatar_poster_generated_at;
    @endphp

    <div class="container-xl px-0">
        <div class="gc-card gc-page-header mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id) }}"><i class="icon ion-chevron-left"></i> К курсу</a>
                <h2 class="mb-1 text-truncate">Настройки курса</h2>
                <p class="mb-0 text-muted text-truncate">{{ $course->name }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-start justify-content-md-end">
                <span class="badge course-status-pill course-status-pill--primary">{{ $course->state }}</span>
                @if($course->mode)
                    <span class="badge course-status-pill course-status-pill--muted">{{ $course->mode }}</span>
                @endif
            </div>
        </div>

        <div class="gc-card overflow-hidden">
            <form method="POST" enctype="multipart/form-data">
                {{ csrf_field() }}

                <div class="p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <h5 class="mb-1">Основное</h5>
                            <p class="mb-0 text-muted small">Название, описание и публичные ссылки курса.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="name" class="form-label">Название</label>
                            <input id="name" type="text" class="form-control rounded-3" name="name" value="{{ old('name', $course->name) }}" required>
                            @error('name')
                                <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="site" class="form-label">Ссылка на описание курса</label>
                            <input id="site" type="text" class="form-control rounded-3" name="site" value="{{ old('site', $course->site) }}">
                            @error('site')
                                <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="image" class="form-label">Ссылка на обложку</label>
                            <input id="image" type="text" class="form-control rounded-3" name="image" value="{{ old('image', $course->image) }}">
                            @error('image')
                                <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="git" class="form-label">Git репозиторий</label>
                            <input id="git" type="text" class="form-control rounded-3" name="git" value="{{ old('git', $course->git) }}">
                            @error('git')
                                <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">Описание</label>
                            <textarea id="description" class="form-control rounded-3" name="description" rows="5" required>{{ old('description', $course->description) }}</textarea>
                            @error('description')
                                <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="col-12">
                            <div class="border rounded-3 p-3 bg-body-tertiary">
                                <div class="d-flex flex-column flex-lg-row gap-3 align-items-start">
                                    <div class="flex-shrink-0">
                                        <img
                                            src="{{ $coursePosterUrl ?: url('/images/avatar-layers/room-system/posters/default.png') }}"
                                            alt="Плакат курса"
                                            class="rounded-3 border bg-white"
                                            style="width: 120px; aspect-ratio: 3 / 4; object-fit: cover;"
                                        >
                                    </div>
                                    <div class="flex-grow-1 min-width-0">
                                        <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                                            <div>
                                                <h6 class="mb-1">Плакат программы для комнаты ученика</h6>
                                                <p class="mb-2 text-muted small">
                                                    GPT-прокси соберет постер по названию и описанию программы. Он генерируется один раз и переиспользуется во всех курсах этой программы; иначе останется стандартный.
                                                </p>
                                                @if($programPosterGeneratedAt)
                                                    <p class="mb-0 text-muted small">Обновлен: {{ $programPosterGeneratedAt->format('d.m.Y H:i') }}</p>
                                                @endif
                                            </div>
                                            <button type="submit" form="course-poster-form" class="btn btn-outline-primary rounded-3 fw-semibold align-self-start">
                                                {{ $programPosterGeneratedAt ? 'Обновить плакат' : 'Сгенерировать плакат' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if (Auth::user()->role=='admin')
                    <div class="border-top p-3 p-md-4">
                        <div class="mb-3">
                            <h5 class="mb-1">Доступ и команда</h5>
                            <p class="mb-0 text-muted small">Тип курса, направления и преподаватели.</p>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="mode" class="form-label">Тип курса</label>
                                <select class="form-select rounded-3" id="mode" name="mode">
                                    <option value="private" @selected(old('mode', $course->mode) == 'private')>Скрытый</option>
                                    <option value="offline" @selected(old('mode', $course->mode) == 'offline')>Офлайн</option>
                                    <option value="zoom" @selected(old('mode', $course->mode) == 'zoom')>Платный онлайн курс</option>
                                    <option value="paid" @selected(old('mode', $course->mode) == 'paid')>Платный онлайн курс без преподавателя</option>
                                    <option value="open" @selected(old('mode', $course->mode) == 'open')>Бесплатный онлайн курс</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="invite" class="form-label">Инвайт</label>
                                <input id="invite" type="text" class="form-control rounded-3" name="invite" value="{{ old('invite', $course->invite) }}">
                                @error('invite')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="categories" class="form-label d-flex align-items-center justify-content-between gap-2">
                                    <span>Образовательные направления</span>
                                    <span class="badge rounded-pill bg-body-tertiary form-selected-count" id="categories-selected-count">{{ $selectedCategoryIds->count() }} выбрано</span>
                                </label>
                                <select class="form-select rounded-3" id="categories" name="categories[]" multiple data-selected-count="#categories-selected-count" data-enhanced-multiselect data-placeholder="Выберите направления" data-search-placeholder="Найти направление">
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected($selectedCategoryIds->contains($category->id))>{{ $category->title }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label for="teachers" class="form-label d-flex align-items-center justify-content-between gap-2">
                                    <span>Учителя</span>
                                    <span class="badge rounded-pill bg-body-tertiary form-selected-count" id="teachers-selected-count">{{ $selectedTeacherIds->count() }} выбрано</span>
                                </label>
                                <select class="form-select rounded-3" id="teachers" name="teachers[]" multiple data-selected-count="#teachers-selected-count" data-enhanced-multiselect data-placeholder="Выберите учителей" data-search-placeholder="Найти учителя">
                                    @foreach ($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" @selected($selectedTeacherIds->contains($teacher->id))>{{ $teacher->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="border-top p-3 p-md-4">
                    <div class="mb-3">
                        <h5 class="mb-1">Ученики и расписание</h5>
                        <p class="mb-0 text-muted small">Состав группы, дни занятий и импорт материалов.</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="students" class="form-label d-flex align-items-center justify-content-between gap-2">
                                <span>Ученики</span>
                                <span class="badge rounded-pill bg-body-tertiary form-selected-count" id="students-selected-count">{{ $selectedStudentIds->count() }} выбрано</span>
                            </label>
                            <select class="form-select rounded-3" id="students" name="students[]" multiple data-selected-count="#students-selected-count" data-enhanced-multiselect data-placeholder="Выберите учеников" data-search-placeholder="Найти ученика">
                                @foreach ($students as $student)
                                    <option value="{{ $student->id }}" @selected($selectedStudentIds->contains($student->id))>{{ $student->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if ($course->state == 'draft')
                            <div class="col-12 col-md-6">
                                <label for="start_date" class="form-label">Дата начала</label>
                                <input id="start_date" type="text" class="form-control rounded-3 date" value="{{ old('start_date', $courseStartDate) }}" name="start_date">
                                @error('start_date')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        @endif

                        <div class="col-12 col-md-6">
                            <label for="weekdays" class="form-label">Дни недели</label>
                            <input id="weekdays" type="text" class="form-control rounded-3" name="weekdays" value="{{ old('weekdays', $course->weekdays) }}" placeholder="1;4">
                            @error('weekdays')
                                <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="telegram" class="form-label">Telegram чат</label>
                            <input id="telegram" type="text" class="form-control rounded-3" name="telegram" value="{{ old('telegram', $course->telegram) }}">
                            @error('telegram')
                                <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="import" class="form-label">Импорт</label>
                            <input id="import" type="file" class="form-control rounded-3" name="import">
                            @error('import')
                                <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="gc-form-footer justify-content-end">
                    <button type="submit" class="btn btn-success rounded-3 fw-semibold px-4">Сохранить настройки</button>
                </div>
            </form>
            <form id="course-poster-form"
                  method="POST"
                  action="{{ url('/insider/courses/'.$course->id.'/poster') }}"
                  class="d-none"
                  data-fullscreen-loading
                  data-loading-message="Генерирую плакат программы">
                {{ csrf_field() }}
            </form>
        </div>
    </div>
@endsection
