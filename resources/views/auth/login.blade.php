@extends('layouts.empty-dark')

@section('title')
    Вход
@endsection

@section('content')
    <div class="ge-auth-visual-shell ge-auth-visual-shell--image" style="--auth-bg-image: url('{{ url('/images/bg/'.random_int(1,16).'.jpg') }}')">
        <div class="auth-panel-shell">
            <div class="row justify-content-center">
                <div class="col-12">
                    <form method="POST" action="{{ url('/login') }}" class="auth-form">
                        {{ csrf_field() }}
                        <div class="card auth-card">
                            <div class="card-body">
                                <div class="text-center auth-brand auth-brand--in-card">
                                    <a class="navbar-brand auth-brand-link" href="{{ url('/') }}">
                                        <img class="auth-brand-icon" src="{{ url('images/icons/icons8-idea-64.png') }}" alt="logo">
                                        {{ config('app.name', 'Laravel') }}
                                    </a>
                                    <h3 class="auth-title">Вход</h3>
                                </div>

                                <div class="mb-3">
                                    <label for="inputEmail" class="visually-hidden">Email</label>
                                    <input type="email" name="email" id="inputEmail"
                                           class="form-control-lg form-control"
                                           placeholder="Электронная почта"
                                           autocomplete="email"
                                           required
                                           autofocus>
                                    @if ($errors->has('email'))
                                        <span class="invalid-feedback d-block">
                                            <strong>{{ $errors->first('email') }}</strong>
                                        </span>
                                    @endif
                                </div>
                                <div class="mb-3">
                                    <label for="inputPassword" class="visually-hidden">Пароль</label>
                                    <div class="auth-password-wrap">
                                        <input type="password" id="inputPassword" name="password"
                                               class="form-control-lg form-control"
                                               placeholder="Пароль"
                                               autocomplete="current-password"
                                               required>
                                        <button type="button" class="auth-password-toggle" data-target="inputPassword" aria-label="Показать пароль">
                                            <i class="icon ion-eye"></i>
                                        </button>
                                    </div>
                                    @if ($errors->has('password'))
                                        <span class="invalid-feedback d-block"><strong>{{ $errors->first('password') }}</strong></span>
                                    @endif
                                </div>

                                <div class="mb-3">
                                    <button class="btn btn-lg btn-primary w-100" type="submit">Вход</button>
                                </div>
                                <div class="form-check text-start mb-3">
                                    <input type="checkbox" name="remember" class="form-check-input" id="exampleCheck1">
                                    <label class="form-check-label" for="exampleCheck1">Не выходить из системы</label>
                                </div>
                                <div class="auth-links-row text-start">
                                    <a class="auth-link-chip" href="{{url('/register')}}"><i class="icon ion-person-add"></i><span>Регистрация</span></a>
                                    <a class="auth-link-chip" href="{{url('/password/reset')}}"><i class="icon ion-key"></i><span>Забыли пароль?</span></a>
                                </div>
                            </div>
                        </div>
                    </form>
                    </div>
            </div>
        </div>
    </div>

@endsection
