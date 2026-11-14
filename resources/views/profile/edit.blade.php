@extends('layouts.left-menu')
@section('title', 'Личные настройки')
@section('content')
    @php
        $isOwner = $guest->id == $user->id;
        $section = request('section') === 'account' || $errors->has('password') || session('oidc_error') || session('status') ? 'account' : 'personal';
        $canEditBirthday = in_array($guest->role, ['teacher', 'admin']);
        $birthdayValue = old('birthday', optional($user->birthday)->format('Y-m-d'));
        $gradeValue = old('grade', $user->grade_year ? $user->grade() : '');
        $genderValue = old('gender', $user->learningAvatarGenderKey());
    @endphp
    <div class="gc-form-layout" data-form-page>
    <div class="gc-page-masthead mb-4">
    <x-gc-page-header :title="$isOwner ? 'Личные настройки' : 'Редактирование профиля'" :description="$user->name">
        <x-slot:breadcrumb><a href="{{ url('/insider/profile/'.$user->id) }}">← К профилю</a></x-slot:breadcrumb>
    </x-gc-page-header>
    <x-gc-tab-strip>
    <nav class="nav gc-page-tabs" aria-label="Личные настройки">
        <a class="nav-link {{ $section === 'personal' ? 'active' : '' }}" href="{{ url('/insider/profile/'.$user->id.'/edit?section=personal') }}" @if($section === 'personal') aria-current="page" @endif>Профиль</a>
        <a class="nav-link {{ $section === 'account' ? 'active' : '' }}" href="{{ url('/insider/profile/'.$user->id.'/edit?section=account') }}" @if($section === 'account') aria-current="page" @endif>Аккаунт</a>
    </nav>
    </x-gc-tab-strip>
    </div>
    @if($section === 'personal')
        <div class="gc-authoring-body">
            <x-gc-form-errors />
            @include('profile.partials.personal-form')
            <x-gc-form-actions form="profile-edit-form" label="Сохранить изменения" :cancel="url('/insider/profile/'.$user->id)" />
        </div>
    @else
        @include('profile.partials.account-settings')
    @endif
    </div>
@endsection
