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

    <form method="POST" action="{{ route('password.email') }}" class="auth-card p-4">
        {{ csrf_field() }}

        <div class="d-flex align-items-center gap-3 mb-4">
            <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-key"></i></span>
            <div class="min-width-0">
                <span class="gc-eyebrow">доступ</span>
                <h1 class="h4 fw-bold mb-0">Восстановление пароля</h1>
            </div>
        </div>

        <div class="form-floating mb-3">
            <input id="email" type="email" class="form-control rounded-3" name="email" value="{{ old('email') }}" placeholder="you@example.com" required>
            <label for="email">Email</label>

            @if ($errors->has('email'))
                <span class="text-danger small d-block mt-1">
                    <strong>{{ $errors->first('email') }}</strong>
                </span>
            @endif
        </div>

        <button type="submit" class="btn btn-success rounded-3 fw-semibold w-100 py-2">
            Восстановить пароль
        </button>

        <div class="border-top pt-3 mt-3 text-center small">
            <a href="{{ url('/login') }}" class="text-decoration-none fw-semibold">Вернуться ко входу</a>
        </div>
    </form>
@endsection
