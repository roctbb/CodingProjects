@extends('layouts.empty-dark')

@section('title', 'Вход')

@section('auth-background-image', url('/images/bg/'.random_int(1,16).'.jpg'))

@section('content')
    <div class="text-center mb-3">
        <a href="{{ url('/') }}" class="d-inline-flex align-items-center gap-2 text-decoration-none text-white">
            <img src="{{ url('images/icons/icons8-idea-64.png') }}" width="28" height="28" alt="">
            <span class="fs-5 fw-medium">{{ config('app.name', 'Laravel') }}</span>
        </a>
        <h1 class="text-white fs-4 fw-normal mt-1">Вход</h1>
    </div>

    <form method="POST" action="{{ url('/login') }}" class="auth-card">
        @csrf
        <div class="mb-3">
            <label for="inputEmail" class="form-label">Email</label>
            <input type="email" name="email" id="inputEmail" class="form-control"
                   placeholder="you@example.com" value="{{ old('email') }}" autocomplete="email" required autofocus>
            @error('email')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="inputPassword" class="form-label">Пароль</label>
            <input type="password" name="password" id="inputPassword" class="form-control"
                   placeholder="Введите пароль" autocomplete="current-password" required>
            @error('password')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <button class="btn btn-success w-100 mb-3" type="submit">Вход</button>

        <div class="form-check mb-3">
            <input type="checkbox" name="remember" class="form-check-input" id="rememberMe" {{ old('remember') ? 'checked' : '' }}>
            <label class="form-check-label text-muted small" for="rememberMe">Не выходить из системы</label>
        </div>

        <div class="d-flex flex-column gap-1 small">
            <a href="{{ url('/register') }}" class="text-decoration-none"><i class="fas fa-user-plus me-1"></i>Регистрация</a>
            <a href="{{ url('/password/reset') }}" class="text-decoration-none"><i class="fas fa-key me-1"></i>Забыли пароль?</a>
        </div>
    </form>
@endsection
