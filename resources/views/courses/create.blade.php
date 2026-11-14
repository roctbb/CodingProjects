@extends('layouts.left-menu')
@section('title', 'Создание курса')
@section('content')
<x-gc-authoring-page title="Создание курса" :description="'Программу, участников и расписание можно настроить после создания.'" :back="url('/insider/courses')" back-label="К курсам" form="course-create-form" submit="Создать курс">
    <form id="course-create-form" method="POST" enctype="multipart/form-data" class="gc-card gc-form-sheet" data-authoring-form>
        @csrf
<x-gc-form-section title="Основное" description="Название и описание увидят ученики.">
<x-gc-field name="name" label="Название курса"><input id="name" name="name" type="text" class="form-control" value="{{ old('name', '') }}" required maxlength="255"></x-gc-field>
<x-gc-field name="description" label="Описание курса"><textarea id="description" name="description" class="form-control" rows="4" maxlength=1024 required>{{ old('description', '') }}</textarea></x-gc-field>
</x-gc-form-section>
<x-gc-form-section title="Программа и оформление">
<x-gc-field name="program" label="Программа" hint="Выберите готовую программу или создайте пустую. При выборе готовой программы курсы используют общие уроки и ступени."><select id="program" name="program" class="form-select" required><option value="-1" @selected((string) old('program', '-1') === '-1')>Новая программа</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected((string)old('program') === (string)$program->id)>{{ $program->name }}</option>@endforeach</select></x-gc-field>
<x-gc-course-icon-picker />
</x-gc-form-section>
    </form>
</x-gc-authoring-page>
@endsection
