@extends('layouts.empty-dark')

@section('head')
    <style>
        html {
            width: 100%;
            height: 100% !important;
        }

        body {
            position: relative;
            z-index: 1;
            width: 100%;
            height: 100% !important;
        }

        body::before {
            content: "";
            z-index: -1;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100% !important;
            background-image: url("{{url('/images/bg/'.random_int(1,7).'.jpg')}}");
            background-size: cover;
            display: block;
            opacity: 0.7;
            filter: blur(3px);
        }
    </style>
@endsection

@section('content')
    <div class="main-container fullscreen">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-5 col-lg-6 col-md-7">
                    <div class="text-center">
                        <div class="row">
                            <div class="col-md-12">
                                <a class="navbar-brand" href="{{ url('/') }}"
                                   style="line-height: 50px; font-size: 1.3rem; color: white;">
            <span><img style="height: 35px; margin-bottom: 0px;"
                       src="{{ url('images/icons/icons8-idea-64.png') }}">&nbsp;</span>
                                    GeekClass
                                </a>
                                <h3 class="card-title"
                                    style="color: white; margin-top: 20px; font-weight: 300; margin-bottom: 15px;">
                                    Восстановление пароля</h3>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                @if (session('status'))
                                    <div class="alert alert-success">
                                        {{ session('status') }}
                                    </div>
                                @endif
                                <form method="POST" action="{{ route('password.request') }}" class="form-signin text-center">

                                    <div class="card">
                                        <div class="card-body">
                                            {{ csrf_field() }}

                                            <input type="hidden" name="token" value="{{ $token }}">

                                            <div class="form-group">
                                                <label for="email" class="control-label">E-Mail адрес:</label>


                                                <input id="email" type="email" class="form-control form-control-lg"
                                                       name="email"
                                                       value="{{ $email ?? old('email') }}" required autofocus>

                                                @if ($errors->has('email'))
                                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                                @endif
                                            </div>

                                            <div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">
                                                <label for="password" class="control-label">Новый
                                                    пароль:</label>

                                                <input id="password" type="password"
                                                       class="form-control form-control-lg"
                                                       name="password" required>

                                                @if ($errors->has('password'))
                                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                                                @endif
                                            </div>

                                            <div class="form-group{{ $errors->has('password_confirmation') ? ' has-error' : '' }}">
                                                <label for="password-confirm" class="control-label">Подтверждение
                                                    пароля:</label>
                                                    <input id="password-confirm" type="password" class="form-control form-control-lg"
                                                           name="password_confirmation" required>

                                                    @if ($errors->has('password_confirmation'))
                                                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('password_confirmation') }}</strong>
                                    </span>
                                                    @endif
                                            </div>

                                            <div class="form-group">
                                                    <button type="submit" class="btn btn-primary">
                                                        Установить новый пароль
                                                    </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

