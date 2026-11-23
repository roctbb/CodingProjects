@extends('layouts.empty-dark')

@section('title', 'Вход')

@section('auth-background-image', url('/images/bg/'.random_int(1,16).'.jpg'))

@section('content')
    <form method="POST" action="{{ route('login', request()->only('service_login')) }}" class="auth-card gc-auth-card">
        @csrf
        <a href="{{ url('/') }}" class="gc-auth-brand"><x-brand-logo width="26" height="26" alt="" /><span>{{ config('app.name', 'Laravel') }}</span></a>
        <h1 class="gc-auth-title">Войти в аккаунт</h1>
        <p class="gc-help-text gc-auth-description">Продолжим работу над проектами.</p>

        @if (session('oidc_error'))
            <div class="alert alert-danger rounded-3 small" role="alert">{{ session('oidc_error') }}</div>
        @endif
        @if (session('oidc_status'))
            <div class="alert alert-success rounded-3 small" role="status">{{ session('oidc_status') }}</div>
        @endif

        <div class="mb-3">
            <label for="inputEmail" class="form-label">Email</label>
            <input type="email" name="email" id="inputEmail" class="form-control rounded-3"
                   placeholder="you@example.com" value="{{ old('email') }}" autocomplete="email" required autofocus @error('email') aria-invalid="true" aria-describedby="login-email-error" @enderror>
            @error('email')
                <div class="text-danger small mt-1" id="login-email-error" role="alert">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="inputPassword" class="form-label">Пароль</label>
            <div class="gc-password-control">
                <input type="password" name="password" id="inputPassword" class="form-control rounded-3" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="login-password-error" @enderror>
                <button type="button" class="gc-password-toggle" data-password-target="inputPassword" aria-label="Показать пароль" aria-pressed="false"><i class="far fa-eye" aria-hidden="true"></i></button>
            </div>
            @error('password')
                <div class="text-danger small mt-1" id="login-password-error" role="alert">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
            <div class="form-check mb-0">
                <input type="checkbox" name="remember" class="form-check-input" id="rememberMe" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label text-muted small" for="rememberMe">Не выходить</label>
            </div>
            <a href="{{ url('/password/reset') }}" class="small text-decoration-none">Забыли пароль?</a>
        </div>

        <button class="btn btn-primary w-100" type="submit">Войти <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i></button>

        @if (config('services.silaeder_oidc.enabled'))
            <a class="btn btn-outline-secondary w-100 mt-3 d-flex align-items-center justify-content-center gap-2"
               href="{{ route('silaeder.login', request()->only('service_login')) }}">
                <img class="silaeder-login-button__logo" src="{{ asset('images/silaeder-logo.png') }}" alt="">
                <span>Войти через ЛК Силаэдра</span>
            </a>
        @endif

        <div class="gc-auth-footer small text-muted">
            Нет аккаунта?
            <a href="{{ url('/register') }}" class="text-decoration-none fw-medium">Зарегистрироваться</a>
        </div>
    </form>
@endsection
