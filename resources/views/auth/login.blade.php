@extends('layouts.empty-dark')

@section('title', 'Вход')

@section('auth-background-image', url('/images/bg/'.random_int(1,16).'.jpg'))

@section('content')
    <div class="text-center text-white mb-3">
        <a href="{{ url('/') }}" class="auth-brand d-inline-flex align-items-center gap-2 text-decoration-none text-white">
            <img src="{{ url('images/icons/icons8-idea-64.png') }}" width="28" height="28" alt="">
            <span class="fs-5 fw-semibold">{{ config('app.name', 'Laravel') }}</span>
        </a>
    </div>

    <form method="POST" action="{{ url('/login') }}" class="auth-card p-4">
        @csrf
        <div class="d-flex align-items-center gap-3 mb-4">
            <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-terminal"></i></span>
            <div class="min-width-0">
                <span class="gc-eyebrow">workspace</span>
                <h1 class="h4 fw-bold mb-0">Вход</h1>
            </div>
        </div>

        <div class="form-floating mb-3">
            <input type="email" name="email" id="inputEmail" class="form-control rounded-3"
                   placeholder="you@example.com" value="{{ old('email') }}" autocomplete="email" required autofocus>
            <label for="inputEmail">Email</label>
            @error('email')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-floating mb-3">
            <input type="password" name="password" id="inputPassword" class="form-control rounded-3"
                   placeholder="Введите пароль" autocomplete="current-password" required>
            <label for="inputPassword">Пароль</label>
            @error('password')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
            <div class="form-check mb-0">
                <input type="checkbox" name="remember" class="form-check-input" id="rememberMe" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label text-muted small" for="rememberMe">Не выходить</label>
            </div>
            <a href="{{ url('/password/reset') }}" class="small text-decoration-none">Забыли пароль?</a>
        </div>

        <button class="btn btn-success rounded-3 fw-semibold w-100 py-2 mb-3" type="submit">Войти</button>

        <div class="border-top pt-3 text-center small text-muted">
            Нет аккаунта?
            <a href="{{ url('/register') }}" class="text-decoration-none fw-semibold">Зарегистрироваться</a>
        </div>
    </form>
@endsection
