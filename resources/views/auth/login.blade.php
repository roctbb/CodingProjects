@extends('layouts.empty-dark')

@section('title')
    Вход
@endsection

@section('auth-background-image', url('/images/bg/'.random_int(1,16).'.jpg'))

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
                                    Вход</h3>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <form method="POST" action="{{ url('/login') }}" class="form-signin">

                                    <div class="card">
                                        <div class="card-body">
                                            {{ csrf_field() }}
                                            <div class="form-group">
                                                <label for="inputEmail" class="sr-only">Email</label>
                                                <input type="email" name="email" id="inputEmail"
                                                       class="form-control-lg form-control"
                                                       placeholder="Email address"
                                                       required
                                                       autofocus>
                                                @if ($errors->has('email'))
                                                    <span class="text-danger d-block">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                                @endif
                                            </div>
                                            <div class="form-group">
                                                <label for="inputPassword" class="sr-only">Пароль</label>
                                                <input type="password" id="inputPassword" name="password"
                                                       class="form-control-lg form-control"
                                                       placeholder="Password"
                                                       required>
                                                @if ($errors->has('password'))
                                                    <span class="text-danger d-block"><strong>{{ $errors->first('password') }}</strong></span>
                                                @endif
                                            </div>

                                            <div class="form-group">

                                                <button class="btn btn-lg btn-primary btn-block"
                                                        type="submit">Вход
                                                </button>
                                            </div>
                                            <div class="form-check text-left mb-3">
                                                <input type="checkbox" name="remember" class="form-check-input"
                                                       id="exampleCheck1">
                                                <label class="form-check-label" for="exampleCheck1">Не выходить из
                                                    системы</label>
                                            </div>
                                            <p class="text-left mt-3">
                                                <a class="text-info" href="{{url('/register')}}"><i
                                                            class="icon ion-person-add"></i>&nbsp;Регистрация</a><br>
                                                <a class="text-info" href="{{url('/password/reset')}}">&nbsp;<i
                                                            class="icon ion-key"></i>&nbsp;&nbsp;Забыли
                                                    пароль?</a>
                                            </p>
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
