<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ config('app.name', 'Laravel') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') - {{ config('app.name', 'Laravel') }}</title>

    @include('layouts.partials.npm-vendor-assets')

    @yield('head')
</head>
<body class="app-material-shell">

@php
    $menuAvatarPrimary = null;
    $menuAvatarFallback = url('images/user.jpg');
    if (Auth::check()) {
        $menuAvatarPrimary = Auth::user()->image ? url('/media/'.Auth::user()->image) : $menuAvatarFallback;
    }
@endphp

<div class="app-material-shell__layout">
    <aside id="appMaterialNav" class="app-material-nav" aria-label="Основная навигация">
        <div class="app-material-nav__section">
            <h2 class="app-material-nav__title">Навигация</h2>
            <ul class="app-material-nav__list">
                @if (Auth::check())
                    <li>
                        <a class="app-material-nav__link {{ Request::is('insider/courses*') ? 'is-active' : '' }}" href="{{ url('/insider/courses') }}" aria-label="Мои курсы">
                            <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                            <span>Мои курсы</span>
                        </a>
                    </li>
                    <li>
                        <a class="app-material-nav__link {{ Request::is('insider/market*') ? 'is-active' : '' }}" href="{{ url('/insider/market') }}" aria-label="Магазин">
                            <i class="fas fa-store" aria-hidden="true"></i>
                            <span>Магазин</span>
                        </a>
                    </li>
                @else
                    <li>
                        <a class="app-material-nav__link {{ (Request::is('courses*') || Request::is('categories*')) ? 'is-active' : '' }}" href="{{ url('/courses') }}" aria-label="Каталог курсов">
                            <i class="fas fa-book-open" aria-hidden="true"></i>
                            <span>Каталог курсов</span>
                        </a>
                    </li>
                    <li>
                        <a class="app-material-nav__link {{ Request::is('login') ? 'is-active' : '' }}" href="{{ url('/login') }}" aria-label="Войти">
                            <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                            <span>Войти</span>
                        </a>
                    </li>
                @endif
            </ul>
        </div>

        <div class="app-material-nav__section">
            <h2 class="app-material-nav__title">Инструменты</h2>
            <ul class="app-material-nav__list app-material-nav__list--small">
                <li>
                    <a class="app-material-nav__link" target="_blank" rel="noopener noreferrer" href="https://blog.geekclass.ru" aria-label="Блог">
                        <i class="fas fa-newspaper" aria-hidden="true"></i>
                        <span>Блог</span>
                    </a>
                </li>
                <li>
                    <a class="app-material-nav__link" target="_blank" rel="noopener noreferrer" href="https://notes.geekclass.ru" aria-label="GeekPaste">
                        <i class="fas fa-sticky-note" aria-hidden="true"></i>
                        <span>GeekPaste</span>
                    </a>
                </li>
            </ul>
        </div>

        @if (Auth::check())
            <div class="app-material-nav__section">
                <h2 class="app-material-nav__title">Профиль</h2>
                <ul class="app-material-nav__list app-material-nav__list--small">
                    <li>
                        <a class="app-material-nav__link {{ Request::is('insider/profile*') ? 'is-active' : '' }}" href="{{ url('/insider/profile') }}" aria-label="Мой профиль">
                            <i class="fas fa-user-circle" aria-hidden="true"></i>
                            <span>Мой профиль</span>
                        </a>
                    </li>
                    @if (Auth::user()->role == 'admin')
                        <li>
                            <a class="app-material-nav__link {{ Request::is('insider/market/orders*') ? 'is-active' : '' }}" href="{{ url('/insider/market/orders') }}" aria-label="Заказы магазина">
                                <i class="fas fa-box" aria-hidden="true"></i>
                                <span>Заказы магазина</span>
                            </a>
                        </li>
                    @endif
                </ul>
            </div>

            <div class="app-material-user">
                <div class="app-material-user__row">
                    <img alt="User" src="{{ $menuAvatarPrimary }}"
                         data-image-fallback="{{ $menuAvatarFallback }}"
                         class="app-material-user__avatar">
                    <div>
                        <p class="app-material-user__name">{{ Auth::user()->name }}</p>
                        <p class="app-material-user__balance"><i class="fas fa-coins" aria-hidden="true"></i> {{ Auth::user()->balance() }}</p>
                    </div>
                </div>
                <md-icon-button type="submit" form="logout-form" class="app-material-user__logout">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                    <span class="app-material-user__logout-text">Выход</span>
                </md-icon-button>
            </div>
        @endif

    </aside>

    <div id="main-content" class="app-material-main">
        <md-icon-button type="button"
                class="app-material-nav-toggle"
                data-ui-nav-toggle
                aria-controls="appMaterialNav"
                aria-expanded="false"
                aria-label="Открыть меню">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </md-icon-button>

        @include('layouts.partials.flash-alert')

        <div class="insider-page-shell">
            @yield('content')
        </div>
    </div>
</div>

<button type="button" class="app-material-nav-backdrop" data-ui-nav-backdrop hidden aria-label="Закрыть меню"></button>

{!! \NoCaptcha::renderJs() !!}
<form class="d-none" id="logout-form" method="POST" action="{{ route('logout') }}">{{ csrf_field() }}</form>

@php
    $cpuiDatepickers = true;
    $cpuiApplyLinkify = true;
    $cpuiLinkifySelector = 'div';
    $cpuiInitPopovers = false;
    $includeActionFormScript = true;
@endphp
@include('layouts.partials.common-footer-scripts')
</body>
</html>
