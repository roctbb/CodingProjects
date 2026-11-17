@extends('layouts.left-menu')
@section('title', 'Подгруппы — '.$course->name)
@section('content')
<x-gc-course-header :course="$course" active="subgroups" />
@php
    $createdGroup = $groups->firstWhere('id', (int) session('created_subgroup_id'));
    $unassignedStudents = $students->filter(fn($student) => !$memberships->has($student->id));
@endphp
<div class="gc-stack">
    @if(session('status'))<div class="gc-help-text" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="gc-section-header"><div class="gc-subgroup-heading"><h2 class="gc-section-title mb-0">Подгруппы</h2><button type="button" class="gc-text-action gc-subgroup-create-toggle" data-subgroup-create-toggle aria-controls="subgroup-create" aria-expanded="false" hidden>+ Подгруппа</button></div><p class="gc-help-text mb-0">Общая программа, отдельное расписание. Каждый ученик входит в одну подгруппу.</p></div>
    <div class="gc-card overflow-hidden">
        <form method="POST" action="{{ url('/insider/courses/'.$course->id.'/subgroups') }}" class="gc-subgroup-create" id="subgroup-create" data-subgroup-create data-open="{{ $errors->any() && old('subgroup_action') === 'create' ? 'true' : 'false' }}">
            @csrf<input type="hidden" name="subgroup_action" value="create">
            <label class="gc-workspace-field" for="subgroup-name"><span>Название новой подгруппы</span><input id="subgroup-name" class="form-control form-control-sm" name="name" maxlength="100" required placeholder="Например, Подгруппа А" value="{{ old('subgroup_action') === 'create' ? old('name') : '' }}"></label>
            <div class="gc-subgroup-create-actions"><button class="btn btn-primary btn-sm" type="submit" aria-label="Создать подгруппу">Создать</button><button type="button" class="btn-options" data-subgroup-create-cancel aria-label="Отменить создание подгруппы" title="Отменить" hidden><i class="fas fa-xmark" aria-hidden="true"></i></button></div>
        </form>
        @if($groups->isEmpty())<p class="gc-help-text p-3 mb-0" data-subgroup-empty>Создайте подгруппу, затем добавьте в неё учеников.</p>@endif
        @foreach($groups as $group)
            <div class="gc-subgroup-item" data-subgroup-editor>
                <div class="gc-subgroup-row" data-subgroup-display>
                    <div class="gc-subgroup-identity"><span class="gc-subgroup-title">{{ $group->name }}</span><span class="gc-help-text">Ученики: {{ $students->filter(fn($s) => (int)$memberships->get($s->id) === $group->id)->count() }}</span></div>
                    <div class="gc-subgroup-actions">
                        <a class="gc-subgroup-schedule" href="{{ url('/insider/courses/'.$course->id.'/planner?subgroup='.$group->id) }}" aria-label="Расписание подгруппы «{{ $group->name }}»" title="Расписание подгруппы"><i class="fas fa-calendar-days" aria-hidden="true"></i><span>Расписание</span></a>
                        <button type="button" class="btn-options" data-subgroup-edit aria-label="Переименовать подгруппу «{{ $group->name }}»" title="Переименовать" hidden><i class="fas fa-pen" aria-hidden="true"></i></button>
                    </div>
                </div>
                @if($createdGroup?->id === $group->id && $unassignedStudents->isNotEmpty())
                    <form method="POST" action="{{ url('/insider/courses/'.$course->id.'/subgroups/assign') }}" class="gc-subgroup-remaining" data-subgroup-add-remaining>
                        @csrf<input type="hidden" name="membership_version" value="{{ $membershipVersion }}"><input type="hidden" name="subgroup_id" value="{{ $group->id }}">
                        @foreach($unassignedStudents as $student)<input type="hidden" name="students[]" value="{{ $student->id }}">@endforeach
                        <button type="submit" class="gc-text-action" title="Добавить всех учеников без подгруппы в «{{ $group->name }}»">Добавить оставшихся — {{ $unassignedStudents->count() }}</button>
                    </form>
                @endif
                <form method="POST" action="{{ url('/insider/courses/'.$course->id.'/subgroups/'.$group->id) }}" class="gc-subgroup-rename" data-subgroup-edit-form>
                    @csrf<input type="hidden" name="revision" value="{{ $group->revision }}">
                    <label class="visually-hidden" for="subgroup-{{ $group->id }}">Название подгруппы</label><input id="subgroup-{{ $group->id }}" class="form-control form-control-sm" name="name" maxlength="100" value="{{ $group->name }}" required>
                    <div class="gc-subgroup-rename-actions"><button type="submit" class="btn btn-outline-secondary btn-sm">Сохранить</button><button type="button" class="btn-options" data-subgroup-edit-cancel aria-label="Отменить переименование" title="Отменить" hidden><i class="fas fa-xmark" aria-hidden="true"></i></button></div>
                </form>
            </div>
        @endforeach
    </div>
    <form method="POST" action="{{ url('/insider/courses/'.$course->id.'/subgroups/assign') }}" class="gc-card gc-subgroup-roster" data-subgroup-roster>
        @csrf<input type="hidden" name="membership_version" value="{{ $membershipVersion }}">
        <div class="gc-subgroup-roster-toolbar">
            <label class="gc-subgroup-search" data-subgroup-search-field hidden><i class="fas fa-magnifying-glass" aria-hidden="true"></i><input type="search" class="form-control form-control-sm" data-subgroup-search aria-label="Найти ученика" placeholder="Найти ученика…" maxlength="120" autocomplete="off"></label>
            <div class="gc-subgroup-selection" data-subgroup-selection hidden><span class="gc-help-text" role="status" data-subgroup-selected-count>Выбрано: 0</span><button type="button" class="gc-text-action" data-subgroup-clear aria-label="Снять выбор" title="Снять выбор">Снять</button><button type="button" class="gc-text-action" data-subgroup-select-unassigned>Выбрать без подгруппы (<span data-subgroup-unassigned-count>0</span>)</button></div>
            <label class="visually-hidden" for="subgroup-target">Перевести выбранных учеников</label><div class="gc-subgroup-transfer-controls" data-subgroup-transfer><select class="form-select form-select-sm" id="subgroup-target" name="subgroup_id"><option value="">Без подгруппы</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected((string) old('subgroup_id', session('created_subgroup_id')) === (string) $group->id)>{{ $group->name }}</option>@endforeach</select><button type="submit" class="btn btn-outline-secondary btn-sm" @disabled($students->isEmpty())>Перевести</button></div>
        </div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th scope="col"><label class="form-check mb-0"><input type="checkbox" class="form-check-input" data-subgroup-select-all aria-label="Выбрать всех учеников" hidden><span class="form-check-label">Ученик</span></label></th><th scope="col">Подгруппа</th></tr></thead><tbody>
        @forelse($students as $student)
            <tr><td><label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="students[]" value="{{ $student->id }}" data-subgroup="{{ $memberships->get($student->id) }}"><span class="form-check-label">{{ $student->name }}</span></label></td><td>{{ $groups->firstWhere('id', $memberships->get($student->id))?->name ?? 'Без подгруппы' }}@if($student->pivot->hidden_from_stats)<small class="gc-help-text d-block">Скрыт из статистики</small>@endif</td></tr>
        @empty<tr><td colspan="2" class="gc-help-text p-4">Добавьте учеников в настройках курса.</td></tr>@endforelse
            <tr data-subgroup-search-empty hidden><td colspan="2" class="gc-help-text p-3">Ученики не найдены. <button type="button" class="gc-text-action" data-subgroup-search-reset>Сбросить поиск</button></td></tr>
        </tbody></table></div>
    </form>
</div>
@endsection
