<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - {{ config('app.name', 'Laravel') }}</title>

	    @include('layouts.partials.npm-vendor-assets')

    @include('layouts.partials.pipeline-theme-scripts')
    @yield('head')
</head>
<body>

@php $workspaceNavigation = app(\App\Support\WorkspaceNavigation::class)->forUser(Auth::user(), request()); @endphp
<div class="gc-layout">
    <aside id="gcSidebar" class="gc-sidebar">
        <a class="gc-sidebar__brand" href="{{ url('/') }}">
            <x-brand-logo alt="" />
            <span>{{ config('app.name', 'Laravel') }}</span>
        </a>

        <button class="gc-sidebar__close gc-icon-button btn btn-outline-secondary" type="button" data-sidebar-close aria-label="Закрыть меню"><i class="fas fa-times" aria-hidden="true"></i></button>
        <nav class="gc-sidebar__scroll" aria-label="Главная навигация">
            <ul class="gc-sidebar__nav">
            @if (Auth::check())
                @foreach($workspaceNavigation['items'] as $navItem)
                    @continue($navItem['key'] === 'profile')
                    @php $navActive = $navItem['shortcutActive'] ?? $navItem['active']; @endphp
                    <li>
                        <a class="gc-sidebar__link {{ $navActive ? 'active' : '' }}" href="{{ url($navItem['path']) }}" @if($navActive) aria-current="page" @endif>
                            <i class="fas fa-{{ $navItem['icon'] }}" aria-hidden="true"></i> {{ $navItem['label'] }}
                        </a>
                    </li>
                @endforeach
                @include('layouts.partials.sidebar-course-links')
            @else
                <li>
                    <a class="gc-sidebar__link {{ Request::is('login') ? 'active' : '' }}" href="{{ url('login') }}">
                        <i class="fas fa-sign-in-alt"></i> Войти
                    </a>
                </li>
            @endif
            </ul>
        </nav>

        @if (Auth::check())
            <div class="gc-sidebar__user">
                <div class="dropdown">
                    <button class="gc-sidebar__user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <x-gc-avatar :user="Auth::user()" img-class="gc-sidebar__avatar" alt="" />
                        <span class="gc-sidebar__user-name">{{ Auth::user()->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ url('insider/profile') }}"><i class="fas fa-user me-2"></i>Профиль</a></li>
                        <li><a class="dropdown-item" href="{{ url('/insider/profile/'.Auth::id().'/edit?section=account') }}"><i class="fas fa-sliders-h" aria-hidden="true"></i> Личные настройки</a></li>
                        @include('layouts.partials.sidebar-settings-link')
                        <li><hr class="dropdown-divider"></li>
                        @include('layouts.partials.sidebar-user-resources')
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
            <button id="gcSidebarToggle" class="gc-topbar__toggle" aria-label="Открыть меню" aria-controls="gcSidebar" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
            <span class="fw-medium">{{ config('app.name', 'Laravel') }}</span>

            @if (Auth::check())
                <div class="dropdown ms-auto">
                    <button class="gc-topbar__avatar-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Меню профиля">
                        <x-gc-avatar :user="Auth::user()" alt="" />
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ url('insider/profile') }}">Профиль</a></li>
                        <li><a class="dropdown-item" href="{{ url('/insider/profile/'.Auth::id().'/edit?section=account') }}"><i class="fas fa-sliders-h" aria-hidden="true"></i> Личные настройки</a></li>
                        @include('layouts.partials.sidebar-settings-link')
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
            @include('layouts.partials.session-alert')

            @yield('tabs')
            @yield('content')
        </div>
    </div>
</div>

<div id="gcBackdrop" class="gc-backdrop"></div>

@if (Auth::check())
    <nav class="gc-bottom-nav" aria-label="Мобильная навигация">
        @foreach($workspaceNavigation['items'] as $navItem)
            @continue($navItem['key'] === 'pulse')
            <a href="{{ url($navItem['path']) }}" class="gc-bottom-nav__item {{ $navItem['active'] ? 'active' : '' }}" @if($navItem['active']) aria-current="page" @endif>
                <i class="fas fa-{{ $navItem['icon'] }}" aria-hidden="true"></i><span>{{ $navItem['shortLabel'] }}</span>
            </a>
        @endforeach
    </nav>
@endif

{!! \NoCaptcha::renderJs() !!}
@include('layouts.partials.common-footer-scripts')
@include('layouts.partials.yandex-metrika')

</body>
</html>
