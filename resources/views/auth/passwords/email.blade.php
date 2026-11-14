@extends('layouts.empty-dark')
@section('title', 'Восстановление пароля')
@section('auth-background-image', url('/images/bg/'.random_int(1,16).'.jpg'))
@section('content')
<form id="password-email-form" method="POST" action="{{ route('password.email') }}" class="auth-card gc-auth-card" data-auth-form data-form-page>
    @csrf
    <x-gc-auth-heading title="Забыли пароль?" description="Укажите почту аккаунта — отправим ссылку для восстановления." />
    @if(session('status'))<p class="alert alert-primary" role="status">{{ session('status') }}</p>@endif
    <div class="gc-form-stack">
        <x-gc-field name="email" label="Email"><input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required></x-gc-field>
        <button type="submit" class="btn btn-primary w-100">Отправить ссылку</button>
    </div>
    <p class="gc-auth-footer"><a href="{{ url('/login') }}">Вернуться ко входу</a></p>
</form>
@endsection
