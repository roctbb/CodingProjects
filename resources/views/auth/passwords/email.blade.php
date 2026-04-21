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
                        <div class="alert alert-success auth-success-panel" role="status">
                            {{ session('status') }}
                            <div class="auth-success-panel__hint">Если письмо не пришло, можно отправить повторно через минуту.</div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
                        {{ csrf_field() }}

                        <div class="mb-3">
                            <label for="email">Электронная почта</label>

                            <input id="email" type="email" class="form-control form-control-lg"
                                   name="email" value="{{ old('email') }}" autocomplete="email" required>

                            @if ($errors->has('email'))
                                <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('email') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary w-100" id="passwordEmailSubmit"
                                    @if (session('status')) data-resend-seconds="60" disabled @endif>
                                @if (session('status'))
                                    Повторная отправка через 60 сек
                                @else
                                    Отправить письмо для восстановления
                                @endif
                            </button>
                            <div class="form-text auth-resend-note" id="passwordEmailResendNote"></div>
                        </div>

                        <div class="auth-links-row text-start">
                            <a class="auth-link-chip" href="{{url('/login')}}"><i class="icon ion-log-in"></i><span>Вернуться ко входу</span></a>
                            <a class="auth-link-chip" href="{{url('/register')}}"><i class="icon ion-person-add"></i><span>Регистрация</span></a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
