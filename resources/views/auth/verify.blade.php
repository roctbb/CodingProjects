@extends('layouts.left-menu')

@section('title')
    Подтверждение
@endsection

@section('content')
    <div class="container-xl px-0">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-7 col-xl-6">
                <div class="gc-card p-3 p-md-4">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <span class="gc-icon-tile gc-icon-tile--lg flex-shrink-0">
                            <i class="fas fa-envelope-open-text fs-4"></i>
                        </span>
                        <div class="min-width-0">
                            <span class="gc-eyebrow">аккаунт</span>
                            <h2 class="fw-bold lh-sm mb-1">{{ __('Подтвердите E-mail адрес') }}</h2>
                            <p class="text-muted mb-0">{{ __('Для продолжения работы с ' . config('app.name', 'Laravel') . ' нужно подтвердить почту.') }}</p>
                        </div>
                    </div>

                    @if (session('resent'))
                        <div class="gc-session-alert gc-session-alert--success gc-alert-row" role="status">
                            <span class="gc-session-alert__icon">
                                <i class="fas fa-check"></i>
                            </span>
                            <div class="min-width-0">
                                {{ __('Ссылка для подтверждения e-mail адреса отправлена.') }}
                            </div>
                        </div>
                    @endif

                    <div class="d-flex flex-column flex-sm-row gap-2 border-top pt-3">
                        <a class="btn btn-success rounded-3 fw-semibold" href="{{ route('verification.resend') }}">
                            {{ __('Получить ссылку на почту') }}
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary rounded-3 fw-semibold w-100">
                                {{ __('Выйти') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
