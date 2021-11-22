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
                                                    <span class="help-block error-block">
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
                                                    <span class="help-block error-block"><strong>{{ $errors->first('password') }}</strong></span>
                                                @endif
                                            </div>

                                            <div class="form-group">

                                                <button class="btn btn-lg btn-primary btn-block"
                                                        type="submit">Вход
                                                </button>
                                            </div>
                                            <div class="form-check text-left"
                                                 style="color: #0A6187; margin-top: 0px; margin-bottom: 15px;">
                                                <input type="checkbox" name="remember" class="form-check-input"
                                                       id="exampleCheck1">
                                                <label class="form-check-label" for="exampleCheck1">Не выходить из
                                                    системы</label>
                                            </div>
                                            <p style="margin-top: 15px;" class="text-left">
                                                <a style="color: #0A6187;" href="{{url('/register')}}"><i
                                                            class="icon ion-person-add"></i>&nbsp;Регистрация</a><br>
                                                <a style="color: #0A6187;" href="{{url('/password/reset')}}">&nbsp;<i
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

