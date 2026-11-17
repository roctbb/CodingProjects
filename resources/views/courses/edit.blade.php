@extends('layouts.left-menu')
@section('title', 'Настройки курса')
@section('content')
@php
    $selectedIds = fn ($key, $defaults) => collect(session()->hasOldInput() ? old($key, []) : $defaults)->map(fn ($id) => (int)$id)->filter()->unique();
    $selectedCategoryIds = $selectedIds('categories', $course->categories->pluck('id'));
    $selectedTeacherIds = $selectedIds('teachers', $course->teachers->pluck('id'));
    $selectedStudentIds = $selectedIds('students', $course->students->pluck('id'));
    $hiddenTeacherIds = $selectedIds('hidden_teachers', $course->teachers->filter(fn ($teacher) => $teacher->pivot?->hidden_from_stats)->pluck('id'));
    $hiddenStudentIds = $selectedIds('hidden_students', $course->students->filter(fn ($student) => $student->pivot?->hidden_from_stats)->pluck('id'));
    $courseStartDate = $course->start_date?->format('Y-m-d') ?? '';
@endphp
<x-gc-authoring-page title="Настройки курса" :description="$course->name" :back="url('/insider/courses/'.$course->id)" back-label="К курсу" form="course-edit-form" submit="Сохранить курс">
<form id="course-edit-form" method="POST" enctype="multipart/form-data" class="gc-card gc-form-sheet" data-authoring-form>
@csrf
<x-gc-form-section title="Основное">
    <x-gc-field name="name" label="Название курса"><input id="name" name="name" class="form-control" value="{{ old('name', $course->name) }}" maxlength="255" required></x-gc-field>
    <x-gc-field name="description" label="Описание курса"><textarea id="description" name="description" class="form-control" rows="4" maxlength="1024" required>{{ old('description', $course->description) }}</textarea></x-gc-field>
    <x-gc-course-icon-picker :course="$course" />
    <x-gc-toggle name="hide_leaderboard" label="Скрыть рейтинг от учеников" value="1" :checked="$course->hide_leaderboard" />
</x-gc-form-section>
<x-gc-form-section title="Участники" description="Выбор участников сохраняется вместе с настройками курса.">
    <a class="gc-text-action" href="{{ route('courses.subgroups', $course->id) }}">Состав и расписания подгрупп</a>
    <x-gc-field name="students" label="Ученики"><select id="students" name="students[]" class="form-select" multiple data-enhanced-multiselect data-placeholder="Выберите учеников" data-search-placeholder="Найти ученика">@foreach($students as $student)<option value="{{ $student->id }}" @selected($selectedStudentIds->contains($student->id))>{{ $student->name }}</option>@endforeach</select></x-gc-field>
    <x-gc-field name="hidden_students" label="Ученики, скрытые из статистики" hint="Доступ к материалам сохранится. Эти ученики не попадут в журнал, отчёт, рейтинг и экспорт баллов."><select id="hidden_students" name="hidden_students[]" class="form-select" multiple data-membership-source="#students" data-enhanced-multiselect data-placeholder="Никто не скрыт" data-search-placeholder="Найти ученика">@foreach($students as $student)<option value="{{ $student->id }}" @selected($hiddenStudentIds->contains($student->id))>{{ $student->name }}</option>@endforeach</select></x-gc-field>
    @if(Auth::user()->role === 'admin')
        <x-gc-field name="teachers" label="Преподаватели"><select id="teachers" name="teachers[]" class="form-select" multiple data-enhanced-multiselect data-placeholder="Выберите преподавателей" data-search-placeholder="Найти преподавателя">@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($selectedTeacherIds->contains($teacher->id))>{{ $teacher->name }}</option>@endforeach</select></x-gc-field>
        <x-gc-field name="hidden_teachers" label="Преподаватели, скрытые из статистики" hint="Сохраняют доступ к курсу и проверке."><select id="hidden_teachers" name="hidden_teachers[]" class="form-select" multiple data-membership-source="#teachers" data-enhanced-multiselect data-placeholder="Никто не скрыт" data-search-placeholder="Найти преподавателя">@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($hiddenTeacherIds->contains($teacher->id))>{{ $teacher->name }}</option>@endforeach</select></x-gc-field>
    @endif
</x-gc-form-section>
<x-gc-form-section title="Доступ и расписание">
    @if(Auth::user()->role === 'admin')
        <x-gc-field name="mode" label="Тип курса"><select id="mode" name="mode" class="form-select">@foreach(['private'=>'Скрытый', 'offline'=>'Офлайн', 'zoom'=>'Платный онлайн с преподавателем', 'paid'=>'Платный онлайн без преподавателя', 'open'=>'Бесплатный онлайн'] as $key=>$label)<option value="{{ $key }}" @selected(old('mode', $course->mode) === $key)>{{ $label }}</option>@endforeach</select></x-gc-field>
        <x-gc-field name="invite" label="Код приглашения"><input id="invite" name="invite" class="form-control" value="{{ old('invite', $course->invite) }}" maxlength="253"></x-gc-field>
    @else
        <x-gc-field name="invite" label="Код приглашения"><input id="invite" class="form-control" value="{{ $course->invite }}" readonly></x-gc-field>
    @endif
    @if($course->state === 'draft')
        <x-gc-field name="start_date" label="Дата начала"><input id="start_date" name="start_date" type="date" class="form-control" value="{{ old('start_date', $courseStartDate) }}"></x-gc-field>
    @else
        <input type="hidden" name="start_date" value="{{ $courseStartDate }}">
    @endif
    <x-gc-field name="weekdays" label="Дни занятий" hint="Краткая подпись, например «Пн, Чт». Сетка занятий настраивается в плане курса."><input id="weekdays" name="weekdays" class="form-control" value="{{ old('weekdays', $course->weekdays) }}" maxlength="255"></x-gc-field>
    <a class="gc-text-action" href="{{ url('/insider/courses/'.$course->id.'/planner') }}">Открыть план занятий</a>
</x-gc-form-section>
<x-gc-form-section title="Ссылки">
    @foreach(['site'=>'Описание курса на сайте', 'git'=>'Git-репозиторий', 'telegram'=>'Чат курса в Telegram', 'image'=>'Изображение курса'] as $key=>$label)
        <x-gc-field :name="$key" :label="$label"><input id="{{ $key }}" name="{{ $key }}" class="form-control" value="{{ old($key, $course->$key) }}" maxlength="255" inputmode="url"></x-gc-field>
    @endforeach
</x-gc-form-section>
@if(Auth::user()->role === 'admin')
<x-gc-form-section title="Направления">
    <x-gc-field name="categories" label="Образовательные направления"><select id="categories" name="categories[]" class="form-select" multiple data-enhanced-multiselect data-placeholder="Выберите направления" data-search-placeholder="Найти направление">@foreach($categories as $category)<option value="{{ $category->id }}" @selected($selectedCategoryIds->contains($category->id))>{{ $category->title }}</option>@endforeach</select></x-gc-field>
</x-gc-form-section>
@endif
<x-gc-form-section title="Плакат программы" description="Общий плакат программы отображается в комнатах учеников.">
    <div class="gc-form-media"><img src="{{ $course->learningAvatarPosterUrl() ?: url('/images/avatar-layers/room-system/posters/default.png') }}" alt="Плакат программы" class="gc-program-poster"><div class="gc-stack"><p class="gc-help-text mb-0">Плакат создаётся по сохранённому названию и описанию программы.</p><div><button type="submit" form="course-poster-form" class="btn btn-outline-secondary btn-sm">{{ $course->program?->learning_avatar_poster_generated_at ? 'Обновить плакат' : 'Создать плакат' }}</button></div></div></div>
</x-gc-form-section>
<x-gc-form-section title="Импорт материалов">
    <x-gc-field name="import" label="Файл курса" hint="JSON-файл, экспортированный из системы. Импорт будет выполнен при сохранении."><input id="import" type="file" name="import" class="form-control" accept=".json,application/json"></x-gc-field>
</x-gc-form-section>
</form>
<form id="course-poster-form" method="POST" action="{{ url('/insider/courses/'.$course->id.'/poster') }}" data-fullscreen-loading data-loading-message="Создаём плакат программы">@csrf</form>
</x-gc-authoring-page>
@endsection
