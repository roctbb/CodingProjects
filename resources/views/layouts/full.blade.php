<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ config('app.name', 'Laravel') }}</title>

	    @include('layouts.partials.npm-vendor-assets')
	    {!! \NoCaptcha::renderJs() !!}
    @yield('head')
</head>
<body class="gc-public-shell">

<header class="gc-public-header d-flex align-items-center gap-3 px-3 px-md-4 py-3 bg-body border-bottom">
    <a href="{{ url('/') }}" class="gc-public-brand d-flex align-items-center gap-2 text-decoration-none text-body me-auto">
        <img src="{{ url('images/icons/icons8-idea-64.png') }}" width="32" height="32" alt="">
        <span class="fw-semibold fs-5">{{ config('app.name', 'Laravel') }}</span>
    </a>

    <nav class="d-none d-md-flex align-items-center gap-1 me-1">
        @if (Auth::check())
            <a class="gc-public-link" href="{{ url('/insider/courses') }}">Мои курсы</a>
        @else
            <a class="gc-public-link" href="{{ url('courses') }}">Каталог курсов</a>
        @endif
    </nav>

    <button class="btn btn-outline-secondary rounded-3 gc-public-icon-btn" id="gcThemeToggle" type="button" aria-label="Переключить тему">
        <i class="fas fa-moon"></i>
    </button>

    @if (Auth::check())
        <div class="dropdown">
            <button class="btn btn-outline-secondary rounded-3 fw-semibold dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
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
        <a class="btn btn-success rounded-3 fw-semibold px-3" href="/login">Вход</a>
    @endif
</header>

<main class="container-lg py-4">
    @include('layouts.partials.session-alert')

    @yield('content')

    <footer class="gc-public-footer border-top pt-4 mt-5">
        <div class="row g-4">
            <div class="col-12 col-md-3 col-lg-2">
                <img src="{{ url('/images/logo.png') }}" width="120" alt="CodingProjects" class="mb-2">
                <small class="d-block text-muted">&copy; 2016–{{ now()->year }}</small>
            </div>
            <div class="col-12 col-md-9 col-lg-10">
                <h6>CodingProjects</h6>
                <ul class="list-unstyled small d-flex flex-wrap gap-2 mb-0">
                    <li><a class="gc-public-footer-link" target="_blank" rel="noopener" href="https://gekkon-club.ru/courses">Курсы</a></li>
                    <li><a class="gc-public-footer-link" target="_blank" rel="noopener" href="https://github.com/geekon-school/">GitHub</a></li>
                    <li><a class="gc-public-footer-link" target="_blank" rel="noopener" href="https://storage.geekclass.ru">Storage</a></li>
                    <li><a class="gc-public-footer-link" target="_blank" rel="noopener" href="https://paste.geekclass.ru">Paste</a></li>
                    <li><a class="gc-public-footer-link" target="_blank" rel="noopener" href="https://exam.geekclass.ru">Exam</a></li>
                    <li><a class="gc-public-footer-link" target="_blank" rel="noopener" href="https://arena.geekclass.ru">Arena</a></li>
                    <li><a class="gc-public-footer-link" target="_blank" rel="noopener" href="https://battle.geekclass.ru">CodeBattle</a></li>
                </ul>
            </div>
        </div>
    </footer>
</main>

@include('layouts.partials.common-footer-scripts')
@include('layouts.partials.yandex-metrika')

</body>
</html>
