<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ config('app.name', 'Laravel') }}</title>

    @include('layouts.partials.npm-vendor-assets')
    <link href="{{ asset('build/css/legacy-theme.css') }}" rel="stylesheet">
    {!! \NoCaptcha::renderJs() !!}
    @yield('head')
</head>
<body>

<header class="d-flex align-items-center px-3 px-md-4 py-3 bg-white border-bottom">
    <a href="{{ url('/') }}" class="d-flex align-items-center gap-2 text-decoration-none text-dark me-auto">
        <img src="{{ url('images/icons/icons8-idea-64.png') }}" height="36" alt="">
        <span class="fw-medium fs-5">{{ config('app.name', 'Laravel') }}</span>
    </a>

    <nav class="d-none d-md-flex gap-3 me-3">
        @if (Auth::check())
            <a class="text-dark text-decoration-none" href="{{ url('/insider/courses') }}">Мои курсы</a>
        @else
            <a class="text-dark text-decoration-none" href="{{ url('courses') }}">Каталог курсов</a>
        @endif
    </nav>

    @if (Auth::check())
        <div class="dropdown">
            <button class="btn btn-link text-dark text-decoration-none dropdown-toggle p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ Auth::user()->name }}
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
    @else
        <a class="btn btn-primary" href="/login">Вход</a>
    @endif
</header>

<main class="container-lg py-4">
    @if(Session::has('alert-class') && Session::get('alert-destination') == 'head')
        <div class="alert {{ Session::get('alert-class') }} alert-dismissible fade show" role="alert">
            <strong>{{ Session::get('alert-title') }}</strong> {{ Session::get('alert-text') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
        </div>
    @endif

    @yield('content')

    <footer class="border-top pt-4 mt-5">
        <div class="row">
            <div class="col-12 col-md-3 col-lg-2">
                <img src="{{ url('/images/logo.png') }}" width="120" alt="CodingProjects" class="mb-2">
                <small class="d-block text-muted">&copy; 2016–{{ now()->year }}</small>
            </div>
            <div class="col-6 col-md-9 col-lg-10 mt-3">
                <h6>CodingProjects</h6>
                <ul class="list-unstyled text-muted small">
                    <li><a class="text-muted" target="_blank" href="https://gekkon-club.ru/courses">Курсы</a></li>
                    <li><a class="text-muted" target="_blank" href="https://github.com/geekon-school/">GitHub</a></li>
                    <li><a class="text-muted" target="_blank" href="https://storage.geekclass.ru">Storage</a></li>
                    <li><a class="text-muted" target="_blank" href="https://paste.geekclass.ru">Paste</a></li>
                </ul>
            </div>
        </div>
    </footer>
</main>

@include('layouts.partials.common-footer-scripts')
@include('layouts.partials.yandex-metrika')

</body>
</html>
