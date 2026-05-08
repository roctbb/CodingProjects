<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ config('app.name', 'Laravel') }}</title>

    @include('layouts.partials.npm-vendor-assets')
    <link href="{{ asset('build/css/legacy-theme.css') }}" rel="stylesheet">
    @yield('head')
</head>
<body>

<div class="gc-layout">
    <aside id="gcSidebar" class="gc-sidebar">
        <a class="gc-sidebar__brand" href="{{ url('/') }}">
            <img src="{{ url('images/icons/icons8-idea-64.png') }}" alt="">
            <span>GeekClass</span>
        </a>

        <ul class="gc-sidebar__nav">
            @if (Auth::check())
                <li>
                    <a class="gc-sidebar__link {{ Request::is('insider/courses*') ? 'active' : '' }}" href="{{ url('/insider/courses') }}">
                        <i class="fas fa-graduation-cap"></i> Мои курсы
                    </a>
                </li>
                <li>
                    <a class="gc-sidebar__link {{ Request::is('insider/market*') ? 'active' : '' }}" href="{{ url('/insider/market') }}">
                        <i class="fas fa-store"></i> Магазин
                    </a>
                </li>
                <li>
                    <a class="gc-sidebar__link {{ Request::is('insider/profile*') ? 'active' : '' }}" href="{{ url('/insider/profile') }}">
                        <i class="fas fa-user-circle"></i> Профиль
                    </a>
                </li>
                @if (Auth::user()->role == 'admin')
                    <li>
                        <a class="gc-sidebar__link {{ Request::is('insider/market/orders*') ? 'active' : '' }}" href="{{ url('/insider/market/orders') }}">
                            <i class="fas fa-box"></i> Заказы
                        </a>
                    </li>
                @endif
            @else
                <li>
                    <a class="gc-sidebar__link {{ Request::is('login') ? 'active' : '' }}" href="{{ url('/login') }}">
                        <i class="fas fa-sign-in-alt"></i> Войти
                    </a>
                </li>
            @endif
        </ul>

        <div class="gc-sidebar__section-label">Инструменты</div>
        <ul class="gc-sidebar__nav" style="flex: 0">
            <li>
                <a class="gc-sidebar__link" target="_blank" rel="noopener" href="https://blog.geekclass.ru">
                    <i class="fas fa-newspaper"></i> Блог
                </a>
            </li>
            <li>
                <a class="gc-sidebar__link" target="_blank" rel="noopener" href="https://notes.geekclass.ru">
                    <i class="fas fa-sticky-note"></i> GeekPaste
                </a>
            </li>
        </ul>

        @if (Auth::check())
            <div class="gc-sidebar__user">
                <div class="dropdown">
                    <button class="gc-sidebar__user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="{{ Auth::user()->imageUrl() }}" class="gc-sidebar__avatar" alt="">
                        <span>{{ Auth::user()->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ url('insider/profile') }}"><i class="fas fa-user me-2"></i>Профиль</a></li>
                        <li><span class="dropdown-item-text text-muted"><i class="fas fa-coins me-2"></i>{{ Auth::user()->balance() }} GC</span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item"><i class="fas fa-sign-out-alt me-2"></i>Выход</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        @endif
    </aside>

    <div class="gc-main">
        <div class="gc-topbar">
            <button id="gcSidebarToggle" class="gc-topbar__toggle" aria-label="Меню">
                <i class="fas fa-bars"></i>
            </button>
            <span class="fw-medium">GeekClass</span>

            @if (Auth::check())
                <div class="dropdown ms-auto">
                    <button class="btn btn-link p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="{{ Auth::user()->imageUrl() }}" class="avatar" alt="">
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ url('insider/profile') }}">Профиль</a></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">Выход</button>
                            </form>
                        </li>
                    </ul>
                </div>
            @endif
        </div>

        <div class="gc-content">
            @if(Session::has('alert-class') && Session::get('alert-destination') == 'head')
                <div class="alert {{ Session::get('alert-class') }} alert-dismissible fade show" role="alert">
                    <strong>{{ Session::get('alert-title') }}</strong> {{ Session::get('alert-text') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </div>
</div>

<div id="gcBackdrop" class="gc-backdrop"></div>

{!! \NoCaptcha::renderJs() !!}
@include('layouts.partials.common-footer-scripts')
@include('layouts.partials.yandex-metrika')

</body>
</html>
