<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - {{ config('app.name', 'Laravel') }}</title>

    @include('layouts.partials.npm-vendor-assets')
    <link href="{{ asset('build/css/legacy-theme.css') }}" rel="stylesheet">
    <script type="module" src="{{ asset('build/js/mathjax-config.js') }}"></script>
    <script id="MathJax-script" async src="{{ asset('build/js/vendor/mathjax/tex-mml-chtml.js') }}"></script>

    @include('layouts.partials.pipeline-theme-scripts')
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
                    <a class="gc-sidebar__link {{ Request::is('insider/market*') ? 'active' : '' }}" href="{{ url('insider/market') }}">
                        <i class="fas fa-store"></i> Магазин
                    </a>
                </li>
                <li>
                    <a class="gc-sidebar__link {{ Request::is('insider/community*') ? 'active' : '' }}" href="{{ url('insider/community') }}">
                        <i class="fas fa-users"></i> Сообщество
                    </a>
                </li>
            @else
                <li>
                    <a class="gc-sidebar__link {{ Request::is('login') ? 'active' : '' }}" href="{{ url('login') }}">
                        <i class="fas fa-sign-in-alt"></i> Войти
                    </a>
                </li>
            @endif
        </ul>

        <div class="gc-sidebar__section-label mt-auto">Ресурсы</div>
        <ul class="gc-sidebar__nav">
            <li>
                <a class="gc-sidebar__link" target="_blank" rel="noopener" href="https://storage.geekclass.ru">
                    <i class="fas fa-cloud"></i> Storage
                </a>
            </li>
            <li>
                <a class="gc-sidebar__link" target="_blank" rel="noopener" href="https://paste.geekclass.ru">
                    <i class="fas fa-clipboard"></i> Paste
                </a>
            </li>
            <li>
                <button class="gc-sidebar__link" id="gcThemeToggle">
                    <i class="fas fa-moon"></i> <span>Тема</span>
                </button>
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
                <div class="gc-toast-container">
                    <div class="toast show align-items-center text-bg-{{ str_replace('alert-', '', Session::get('alert-class')) }} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000">
                        <div class="d-flex">
                            <div class="toast-body">
                                <strong>{{ Session::get('alert-title') }}</strong> {{ Session::get('alert-text') }}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Закрыть"></button>
                        </div>
                    </div>
                </div>
            @endif

            @yield('tabs')
            @yield('content')
        </div>
    </div>
</div>

<div id="gcBackdrop" class="gc-backdrop"></div>

@if (Auth::check())
    <nav class="gc-bottom-nav" aria-label="Мобильная навигация">
        <a href="{{ url('/insider/courses') }}" class="gc-bottom-nav__item {{ Request::is('insider/courses*') ? 'active' : '' }}">
            <i class="fas fa-graduation-cap"></i><span>Курсы</span>
        </a>
        <a href="{{ url('/insider/market') }}" class="gc-bottom-nav__item {{ Request::is('insider/market*') ? 'active' : '' }}">
            <i class="fas fa-store"></i><span>Магазин</span>
        </a>
        <a href="{{ url('/insider/community') }}" class="gc-bottom-nav__item {{ Request::is('insider/community*') ? 'active' : '' }}">
            <i class="fas fa-users"></i><span>Люди</span>
        </a>
        <a href="{{ url('/insider/profile') }}" class="gc-bottom-nav__item {{ Request::is('insider/profile*') ? 'active' : '' }}">
            <i class="fas fa-user"></i><span>Профиль</span>
        </a>
    </nav>
@endif

{!! \NoCaptcha::renderJs() !!}
@include('layouts.partials.common-footer-scripts')
@include('layouts.partials.yandex-metrika')

</body>
</html>
