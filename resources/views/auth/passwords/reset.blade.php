@extends('layouts.empty-dark')

@section('title')
    Восстановление пароля
@endsection

@section('content')
    <div class="ge-auth-visual-shell ge-auth-visual-shell--image" style="--auth-bg-image: url('{{ url('/images/bg/'.random_int(1,7).'.jpg') }}')">
        <div class="auth-panel-shell">
            <div class="card auth-card">
                <div class="card-body">
                    <div class="text-center auth-brand auth-brand--in-card">
                        <a class="auth-brand-link" href="{{ url('/') }}">
                            <img class="auth-brand-icon" src="{{ url('images/icons/icons8-idea-64.png') }}" alt="logo">
                            {{ config('app.name', 'Laravel') }}
                        </a>
                        <h3 class="auth-title">Восстановление пароля</h3>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-success">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.request') }}" class="auth-form">
                        {{ csrf_field() }}

                        <input type="hidden" name="token" value="{{ $token }}">

                        <div class="mb-3">
                            <label for="email">Электронная почта</label>

                            <input id="email" type="email" class="form-control form-control-lg"
                                   name="email" value="{{ $email ?? old('email') }}" autocomplete="email" required autofocus>

                            @if ($errors->has('email'))
                                <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('email') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="password">Новый пароль</label>

                            <div class="auth-password-wrap">
                                <input id="password" type="password" class="form-control form-control-lg"
                                       name="password" autocomplete="new-password" required>
                                <button type="button" class="auth-password-toggle" data-target="password" aria-label="Показать пароль">
                                    <i class="icon ion-eye"></i>
                                </button>
                            </div>
                            <div class="auth-password-strength" data-password-strength-target="password">
                                <div class="auth-password-strength__track"><span class="auth-password-strength__bar"></span></div>
                                <span class="auth-password-strength__label">Надежность пароля: не указано</span>
                            </div>

                            @if ($errors->has('password'))
                                <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('password') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="password-confirm">Повторите пароль</label>
                            <div class="auth-password-wrap">
                                <input id="password-confirm" type="password" class="form-control form-control-lg"
                                       name="password_confirmation" autocomplete="new-password" required>
                                <button type="button" class="auth-password-toggle" data-target="password-confirm" aria-label="Показать пароль">
                                    <i class="icon ion-eye"></i>
                                </button>
                            </div>

                            @if ($errors->has('password_confirmation'))
                                <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('password_confirmation') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary w-100">
                                Установить новый пароль
                            </button>
                        </div>

                        <div class="auth-links-row text-start">
                            <a class="auth-link-chip" href="{{url('/login')}}"><i class="icon ion-log-in"></i><span>Вернуться ко входу</span></a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
