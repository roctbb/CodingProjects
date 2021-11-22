@extends('layouts.left-menu')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Подтвердите E-mail адрес.') }}</div>

                <div class="card-body">
                    @if (session('resent'))
                    <div class="alert alert-success" role="alert">
                        {{ __('Ссылка для подтверждения e-mail адреса отправлена.') }}
                    </div>
                    @endif

                    {{ __('Для продолжения работы с GeekClass вам необходимо подтвердить свой e-mail адрес.') }}
                    {{ __('Если вы не получили сообщение или видите это сообщение впервые') }}, <a href="{{ route('verification.resend') }}">{{ __('нажмите сюда, чтобы получить ссылку на почту') }}</a>.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection