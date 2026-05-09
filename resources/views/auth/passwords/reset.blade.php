@extends('layouts.empty-dark')

@section('title')
    Восстановление пароля
@endsection

@section('auth-background-image', url('/images/bg/'.random_int(1,7).'.jpg'))

@section('content')
    <div class="text-center text-white mb-3">
        <a href="{{ url('/') }}" class="auth-brand d-inline-flex align-items-center gap-2 text-decoration-none text-white">
            <img src="{{ url('images/icons/icons8-idea-64.png') }}" width="28" height="28" alt="">
            <span class="fs-5 fw-semibold">{{ config('app.name', 'Laravel') }}</span>
        </a>
    </div>

    @if (session('status'))
        <div class="gc-session-alert gc-session-alert--success auth-card gc-alert-row" role="status">
            <span class="gc-session-alert__icon">
                <i class="fas fa-check"></i>
            </span>
            <div class="min-width-0">{{ session('status') }}</div>
        </div>
    @endif

    <form method="POST" action="{{ route('password.request') }}" class="auth-card p-4">
        {{ csrf_field() }}
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="d-flex align-items-center gap-3 mb-4">
            <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-lock"></i></span>
            <div class="min-width-0">
                <span class="gc-eyebrow">доступ</span>
                <h1 class="h4 fw-bold mb-0">Новый пароль</h1>
            </div>
        </div>

        <div class="form-floating mb-3">
            <input id="email" type="email" class="form-control rounded-3" name="email" value="{{ $email ?? old('email') }}" placeholder="you@example.com" autocomplete="email" required autofocus>
            <label for="email">Email</label>
            @if ($errors->has('email'))
                <span class="text-danger small d-block mt-1" role="alert" aria-live="polite"><strong>{{ $errors->first('email') }}</strong></span>
            @endif
        </div>

        <div class="form-floating mb-3">
            <input id="password" type="password" class="form-control rounded-3" name="password" placeholder="Новый пароль" autocomplete="new-password" required>
            <label for="password">Новый пароль</label>
            @if ($errors->has('password'))
                <span class="text-danger small d-block mt-1" role="alert" aria-live="polite"><strong>{{ $errors->first('password') }}</strong></span>
            @endif
        </div>

        <div class="form-floating mb-3">
            <input id="password-confirm" type="password" class="form-control rounded-3" name="password_confirmation" placeholder="Подтверждение пароля" autocomplete="new-password" required>
            <label for="password-confirm">Подтверждение пароля</label>
            @if ($errors->has('password_confirmation'))
                <span class="text-danger small d-block mt-1" role="alert" aria-live="polite"><strong>{{ $errors->first('password_confirmation') }}</strong></span>
            @endif
        </div>

        <button type="submit" class="btn btn-success rounded-3 fw-semibold w-100 py-2">Установить новый пароль</button>
    </form>
@endsection
