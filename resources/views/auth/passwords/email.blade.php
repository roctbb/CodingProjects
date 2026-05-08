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
                                <h3 class="card-title text-white fw-light mt-3 mb-3">
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

                                <form method="POST" action="{{ route('password.email') }}"
                                      class="form-signin text-start">

                                    <div class="card">
                                        <div class="card-body">
                                            {{ csrf_field() }}

                                            <div class="mb-3">
                                                <label for="email" class="form-label">E-Mail адрес</label>

                                                <input id="email" type="email" class="form-control form-control-lg"
                                                       name="email" value="{{ old('email') }}" required>

                                                @if ($errors->has('email'))
                                                    <span class="text-danger d-block">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                                @endif
                                            </div>

                                            <div class="mb-3">
                                                <button type="submit" class="btn btn-primary">
                                                    Восстановить пароль
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
