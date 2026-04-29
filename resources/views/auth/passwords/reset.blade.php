@extends('layouts.empty-dark')

@section('title')
    Восстановление пароля
@endsection

@section('auth-background-image', url('/images/bg/'.random_int(1,7).'.jpg'))

@section('content')
    <div class="main-container fullscreen">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-5 col-lg-6 col-md-7">
                    <div class="text-center">
                        <div class="row">
                            <div class="col-md-12">
                                <a class="navbar-brand text-white" href="{{ url('/') }}">
            <span><img src="{{ url('images/icons/icons8-idea-64.png') }}" height="35" alt="">&nbsp;</span>
                                    {{ config('app.name', 'Laravel') }}
                                </a>
                                <h3 class="card-title text-white font-weight-light mt-3 mb-3">
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
                                                <label for="email" class="form-label">E-Mail адрес:</label>


                                                <input id="email" type="email" class="form-control form-control-lg"
                                                       name="email"
                                                       value="{{ $email ?? old('email') }}" required autofocus>

                                                @if ($errors->has('email'))
                                                    <span class="text-danger d-block">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                                @endif
                                            </div>

                                            <div class="form-group">
                                                <label for="password" class="form-label">Новый
                                                    пароль:</label>

                                                <input id="password" type="password"
                                                       class="form-control form-control-lg"
                                                       name="password" required>

                                                @if ($errors->has('password'))
                                                    <span class="text-danger d-block">
                                        <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                                                @endif
                                            </div>

                                            <div class="form-group">
                                                <label for="password-confirm" class="form-label">Подтверждение
                                                    пароля:</label>
                                                    <input id="password-confirm" type="password" class="form-control form-control-lg"
                                                           name="password_confirmation" required>

                                                    @if ($errors->has('password_confirmation'))
                                                        <span class="text-danger d-block">
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
