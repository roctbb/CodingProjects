<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ config('app.name', 'Laravel') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @yield('title')
        - {{ config('app.name', 'Laravel') }}
    </title>

    @include('layouts.partials.npm-vendor-assets')

    <script>
        window.MathJax = {
            tex: {
                inlineMath: [['$', '$'], ['\\(', '\\)']],
                displayMath: [['$$', '$$'], ['\\[', '\\]']]
            },
            options: {
                skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre']
            }
        };
    </script>
    <script id="MathJax-script" async src="{{ url('/js/mathjax/tex-mml-chtml.js') }}"></script>
    <script>hljs.initHighlightingOnLoad();</script>

    @yield('head')
</head>
<body class="geek-shell-body {{ (Request::is('insider/courses*') || Request::is('insider/market*')) ? 'courses-list-fixed-sidebar' : '' }}">
@php
    $menuAvatarPrimary = null;
    $menuAvatarFallback = url('images/user.jpg');
    if (Auth::check()) {
        $menuAvatarPrimary = Auth::user()->image ? url('/media/'.Auth::user()->image) : $menuAvatarFallback;
    }
@endphp

<div class="layout layout-nav-side app-side-layout geek-shell">
    <aside class="navbar navbar-expand-lg app-side-navbar" aria-label="Основная навигация">
        <a class="navbar-brand app-side-brand app-side-brand--wordmark" href="{{ url('/') }}">
            <span class="app-side-brand-wordmark">
                <span class="app-side-brand-wordmark-main">Coding</span><span class="app-side-brand-wordmark-accent">Projects</span>
            </span>
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#navbar-collapse"
                aria-controls="navbar-collapse" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse flex-column" id="navbar-collapse">
            <div class="app-side-section-title">Навигация</div>
            <ul class="navbar-nav d-lg-block w-100 app-side-nav">
                @if (Auth::check())
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('insider/courses*') ? 'active-link' : '' }}"
                           href="{{ url('/insider/courses') }}" @if(Request::is('insider/courses*')) aria-current="page" @endif>
                            <span class="app-side-link-content"><i class="fa-solid fa-graduation-cap app-side-link-icon"></i><span class="app-side-link-label">Мои курсы</span></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('insider/market*') ? 'active-link' : '' }}"
                           href="{{ url('insider/market') }}" @if(Request::is('insider/market*')) aria-current="page" @endif>
                            <span class="app-side-link-content"><i class="fa-solid fa-store app-side-link-icon"></i><span class="app-side-link-label">Магазин</span></span>
                        </a>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link {{ (Request::is('courses*') || Request::is('categories*')) ? 'active-link' : '' }}"
                           href="{{ url('courses') }}" @if(Request::is('courses*') || Request::is('categories*')) aria-current="page" @endif>
                            <span class="app-side-link-content"><i class="fa-solid fa-book-open app-side-link-icon"></i><span class="app-side-link-label">Каталог курсов</span></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('login') ? 'active-link' : '' }}" href="{{ url('login') }}" @if(Request::is('login')) aria-current="page" @endif>
                            <span class="app-side-link-content"><i class="fa-solid fa-right-to-bracket app-side-link-icon"></i><span class="app-side-link-label">Войти</span></span>
                        </a>
                    </li>
                @endif
            </ul>

            <div class="app-side-section-title mt-3 d-none d-xl-block">Инструменты</div>
            <button class="btn app-side-section-toggle d-xl-none mt-3" type="button"
                    data-bs-toggle="collapse" data-bs-target="#sideTools"
                    aria-expanded="false" aria-controls="sideTools">
                <i class="fa-solid fa-toolbox app-side-toggle-icon"></i> Инструменты
            </button>
            <ul id="sideTools" class="nav nav-small flex-column app-side-nav app-side-nav--secondary w-100 collapse d-xl-flex">
                <li class="nav-item">
                    <a class="nav-link" target="_blank" href="https://storage.geekclass.ru"><span class="app-side-link-content"><i class="fa-solid fa-box-archive app-side-link-icon"></i><span class="app-side-link-label">Storage</span></span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" target="_blank" href="https://paste.geekclass.ru"><span class="app-side-link-content"><i class="fa-solid fa-note-sticky app-side-link-icon"></i><span class="app-side-link-label">GeekPaste</span></span></a>
                </li>
            </ul>

            @if (Auth::check())
                <div class="app-side-section-title mt-3 d-none d-xl-block">Быстрый доступ</div>
                <button class="btn app-side-section-toggle d-xl-none mt-3" type="button"
                        data-bs-toggle="collapse" data-bs-target="#sideQuickLinks"
                        aria-expanded="false" aria-controls="sideQuickLinks">
                    <i class="fa-solid fa-bolt app-side-toggle-icon"></i> Быстрый доступ
                </button>
                <ul id="sideQuickLinks" class="nav nav-small flex-column app-side-nav app-side-nav--secondary app-side-nav--quick w-100 collapse d-xl-flex">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('insider/profile') }}"><span class="app-side-link-content"><i class="fa-solid fa-user app-side-link-icon"></i><span class="app-side-link-label">Профиль</span></span></a>
                    </li>
                    @if (Auth::user()->role == 'admin')
                        <li class="nav-item">
                            <a class="nav-link" href="{{ url('insider/market/orders') }}"><span class="app-side-link-content"><i class="fa-solid fa-inbox app-side-link-icon"></i><span class="app-side-link-label">Заказы магазина</span></span></a>
                        </li>
                    @endif
                </ul>
            @endif
        </div>

        @if (Auth::check())
            <div class="dropup app-side-user-wrap">
                <div class="app-side-balance-chip">
                    <span><i class="fa-solid fa-coins app-side-link-icon"></i> Баланс</span>
                    <strong>{{ Auth::user()->balance() }}</strong>
                </div>
                <a class="app-side-user-trigger" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <img alt="Image" src="{{ $menuAvatarPrimary }}"
                         onerror="if(!this.dataset.fallback){this.dataset.fallback='1';this.src='{{ $menuAvatarFallback }}';}"
                         class="avatar menu-avatar"/>
                    <span class="app-side-user-name">{{ Auth::user()->name }}</span>
                    <i class="fa-solid fa-chevron-up app-side-user-chevron"></i>
                </a>

                <div class="dropdown-menu dropdown-menu-dark">
                    <a class="dropdown-item {{ Request::is('insider/profile*') ? 'active' : '' }}"
                       href="{{ url('insider/profile') }}" @if(Request::is('insider/profile*')) aria-current="page" @endif><i class="fa-solid fa-user app-side-link-icon"></i> Профиль</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{ route('logout') }}"
                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i class="fa-solid fa-right-from-bracket app-side-link-icon"></i> Выход</a>
                </div>
            </div>
        @endif
    </aside>

    <main class="container-fluid app-page-content">
        <div class="row justify-content-center">
            <div class="col-12 col-xxl-11 app-content-column py-4">
                @if(Session::has('alert-class') && Session::get('alert-destination') == 'head')
                    <div class="alert {{ Session::get('alert-class') }} alert-dismissible" role="alert">
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                        </button>
                        <strong>{{ Session::get('alert-title') }}</strong> {{ Session::get('alert-text') }}
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </main>
</div>

{!! \NoCaptcha::renderJs() !!}

@php
    $cpuiDatepickers = true;
    $cpuiTabsSelector = '.nav-tabs a, .nav-pills a';
    $cpuiInitPopovers = true;
    $enableMathJaxTypeset = true;
    $includeActionFormScript = true;
@endphp
@include('layouts.partials.common-footer-scripts')
</body>
</html>
