@extends('layouts.empty-dark')

@section('title')
    Восстановление пароля
@endsection

@section('auth-background-image', url('/images/bg/'.random_int(1,7).'.jpg'))

@section('head')
    <style>
        .auth-shell {
            background: #2d9ccc;
        }

        .auth-shell main.container.pb-4 {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding-bottom: 0 !important;
        }

        .auth-shell main.container.pb-4 > .row {
            width: 100%;
            align-items: center;
        }

        .auth-shell main.container.pb-4 .align-items-center.justify-content-center.pt-4 {
            width: 100%;
            padding-top: 0 !important;
        }

        .reset-wrap {
            width: 100%;
            display: flex;
            justify-content: center;
            padding: 0.35rem 0;
            position: relative;
        }

        .reset-wrap::before {
            content: "";
            position: fixed;
            inset: 0;
            background: linear-gradient(180deg, rgba(20, 43, 72, 0.35) 0%, rgba(20, 43, 72, 0.26) 100%);
            z-index: -1;
        }

        .reset-shell {
            width: 100%;
            max-width: 560px;
        }

        .reset-heading {
            text-align: center;
            margin-bottom: 0.7rem;
        }

        .reset-brand {
            color: #f5fbff;
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            font-size: 1.55rem;
            font-weight: 600;
            text-decoration: none;
            text-shadow: 0 8px 24px rgba(12, 45, 88, 0.2);
        }

        .reset-brand:hover,
        .reset-brand:focus,
        .reset-brand:active {
            color: #f5fbff;
            text-decoration: none;
        }

        .reset-brand img {
            width: 30px;
            height: 30px;
        }

        .reset-title {
            color: #f7fbff;
            font-size: 1.75rem;
            font-weight: 400;
            margin: 0.32rem 0 0;
            text-shadow: 0 8px 24px rgba(12, 45, 88, 0.2);
        }

        .reset-panel {
            border: 1px solid rgba(255, 255, 255, 0.55);
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 10px 28px rgba(14, 50, 96, 0.2);
            padding: 1.1rem 1.1rem 1rem;
        }

        .reset-label {
            color: #355172;
            font-weight: 600;
            font-size: 0.94rem;
            margin-bottom: 0.3rem;
        }

        .reset-panel .form-control {
            border-radius: 9px;
            font-size: 1rem;
            padding: 0.62rem 0.8rem;
            border-color: #c8d4e3;
            background-color: #f6f9fd;
        }

        .reset-panel .form-control:focus {
            border-color: #3995ff;
            box-shadow: 0 0 0 0.14rem rgba(57, 149, 255, 0.18);
        }

        .reset-error {
            background: #ffe6e8;
            border: 1px solid #f3b5bc;
            border-radius: 8px;
            color: #8a2129;
            display: block;
            margin-top: 0.4rem;
            padding: 0.35rem 0.52rem;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .reset-submit {
            border-radius: 10px;
            font-size: 1.12rem;
            font-weight: 600;
            padding: 0.5rem 0.8rem;
            background: linear-gradient(135deg, #28a745 0%, #229b40 100%);
            border-color: #229b40;
        }

        @media (max-width: 767px) {
            .auth-shell main.container.pb-4 {
                min-height: 100%;
                padding-top: 0.9rem;
                padding-bottom: 0.9rem !important;
            }

            .reset-panel {
                padding: 0.9rem;
            }

            .reset-title {
                font-size: 1.55rem;
            }

            .reset-brand {
                font-size: 1.3rem;
            }
        }
    </style>
@endsection

@section('content')
    <div class="reset-wrap">
        <div class="reset-shell">
            <div class="reset-heading">
                <a class="reset-brand" href="{{ url('/') }}">
                    <img src="{{ url('images/icons/icons8-idea-64.png') }}" alt="">
                    <span>{{ config('app.name', 'Laravel') }}</span>
                </a>
                <h1 class="reset-title">Восстановление пароля</h1>
            </div>

            @if (session('status'))
                <div class="alert alert-success mb-3">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('password.request') }}" class="reset-panel shadow">
                {{ csrf_field() }}
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="mb-3 text-start mb-3">
                    <label for="email" class="reset-label">E-Mail адрес</label>
                    <input id="email" type="email" class="form-control" name="email" value="{{ $email ?? old('email') }}" autocomplete="email" required autofocus>
                    @if ($errors->has('email'))
                        <span class="reset-error" role="alert" aria-live="polite"><strong>{{ $errors->first('email') }}</strong></span>
                    @endif
                </div>

                <div class="mb-3 text-start mb-3">
                    <label for="password" class="reset-label">Новый пароль</label>
                    <input id="password" type="password" class="form-control" name="password" autocomplete="new-password" required>
                    @if ($errors->has('password'))
                        <span class="reset-error" role="alert" aria-live="polite"><strong>{{ $errors->first('password') }}</strong></span>
                    @endif
                </div>

                <div class="mb-3 text-start mb-3">
                    <label for="password-confirm" class="reset-label">Подтверждение пароля</label>
                    <input id="password-confirm" type="password" class="form-control" name="password_confirmation" autocomplete="new-password" required>
                    @if ($errors->has('password_confirmation'))
                        <span class="reset-error" role="alert" aria-live="polite"><strong>{{ $errors->first('password_confirmation') }}</strong></span>
                    @endif
                </div>

                <button type="submit" class="btn btn-success w-100 reset-submit">Установить новый пароль</button>
            </form>
        </div>
    </div>

@endsection
