<header class="app-topbar border-bottom">
    <div class="container-fluid app-topbar-inner">
        <nav class="navbar navbar-expand-lg p-0">
            <a class="navbar-brand app-topbar-brand d-flex align-items-center gap-2" href="{{ url('/') }}">
                <img class="app-logo-icon" src="{{ url('images/icons/icons8-idea-64.png') }}" alt="logo">
                <span class="app-topbar-brand-text">{{ config('app.name', 'Laravel') }}</span>
            </a>

            <button class="navbar-toggler app-topbar-toggler" type="button"
                    data-bs-toggle="collapse" data-bs-target="#mainTopbarNav"
                    aria-controls="mainTopbarNav" aria-expanded="false" aria-label="Переключить навигацию">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainTopbarNav">
                <ul class="navbar-nav app-topbar-nav me-auto mb-2 mb-lg-0">
                    @if (Auth::check())
                        <li class="nav-item">
                            <a class="nav-link {{ Request::is('insider/courses*') ? 'active' : '' }}" href="{{ url('/insider/courses') }}">Мои курсы</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ (Request::is('courses*') || Request::is('categories*')) ? 'active' : '' }}" href="{{ url('/insider/courses') }}">Каталог курсов</a>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link {{ (Request::is('courses*') || Request::is('categories*')) ? 'active' : '' }}" href="{{ url('courses') }}">Каталог курсов</a>
                        </li>
                    @endif
                </ul>

                @if (Auth::check())
                    <ul class="navbar-nav app-topbar-user">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle px-3" href="#" id="dropdown01" role="button"
                               data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                {{ Auth::user()->name }}
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdown01">
                                <a class="dropdown-item" href="{{ url('insider/profile') }}"><i class="icon fa-solid fa-user"></i> Профиль</a>
                                <a class="dropdown-item" href="{{ route('logout') }}"
                                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i class="icon fa-solid fa-reply"></i> Выход</a>
                            </div>
                        </li>
                    </ul>
                @else
                    <a class="btn btn-outline-primary app-topbar-login-btn" href="{{ url('/login') }}">Вход</a>
                @endif
            </div>
        </nav>
    </div>
</header>
