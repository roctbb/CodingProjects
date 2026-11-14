@extends('layouts.empty-dark')
@section('title', 'Новый пароль')
@section('auth-background-image', url('/images/bg/'.random_int(1,16).'.jpg'))
@section('content')
<form id="password-reset-form" method="POST" action="{{ route('password.update') }}" class="auth-card gc-auth-card" data-auth-form data-form-page>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <x-gc-auth-heading title="Новый пароль" description="После сохранения используйте его для входа в аккаунт." />
    @if(session('status'))<p class="alert alert-primary" role="status">{{ session('status') }}</p>@endif
    <div class="gc-form-stack">
        <x-gc-field name="email" label="Email"><input id="email" type="email" name="email" class="form-control" value="{{ old('email', $email ?? '') }}" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" required></x-gc-field>
        <x-gc-password-field label="Новый пароль" :minlength="8" :maxlength="null" hint="Не менее 8 символов." />
        <x-gc-password-field name="password_confirmation" id="password-confirm" label="Повторите пароль" confirm="password" :minlength="8" :maxlength="null" />
        <button type="submit" class="btn btn-primary w-100">Сохранить пароль</button>
    </div>
    <p class="gc-auth-footer"><a href="{{ url('/login') }}">Вернуться ко входу</a></p>
</form>
@endsection
