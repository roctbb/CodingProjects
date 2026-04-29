<!doctype html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="utf-8">
    <title>
        @yield('title')
         - {{ config('app.name', 'Laravel') }}
    </title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ config('app.name', 'Laravel') }}">
    @include('layouts.partials.npm-vendor-assets')
    <link href="{{ asset('build/css/legacy/theme.css') }}" rel="stylesheet" type="text/css" media="all"/>
    <link href="{{ asset('build/css/legacy-theme.css') }}" rel="stylesheet" type="text/css" media="all"/>
    <script type="module" src="{{ asset('build/js/mathjax-config.js') }}"></script>
    <script id="MathJax-script" async src="{{ asset('build/js/vendor/mathjax/tex-mml-chtml.js') }}"></script>

    @include('layouts.partials.pipeline-theme-scripts')
    @yield('head')
</head>

<body>

<div class="layout layout-nav-side">
    <nav class="navbar navbar-expand-lg bg-dark navbar-dark sticky-top" aria-label="Основная навигация">

        <a class="navbar-brand" href="{{ url('/') }}">
            <span><img src="{{ url('images/icons/icons8-idea-64.png') }}" height="35" alt="">&nbsp;</span>
            CodingProjects
        </a>

        <div class="d-flex align-items-center">
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar-collapse"
                    aria-controls="navbar-collapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="d-block d-lg-none ml-2">

                @if (\Auth::check())
                    <div class="dropdown">
                        <button class="btn p-0 border-0 bg-transparent" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">

                            <img alt="Image" src="{{ \Auth::User()->imageUrl() }}" class="avatar menu"/>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="{{url('insider/profile')}}"><i class="icon ion-person"></i>
                                Профиль</a>
                            <form method="POST" action="{{ route('logout') }}">
                                {{ csrf_field() }}
                                <button type="submit" class="dropdown-item"><i class="icon ion-reply"></i>Выход</button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="collapse navbar-collapse flex-column" id="navbar-collapse">
            <ul class="navbar-nav d-lg-block">

            @if (\Auth::check())
                        <li class="nav-item">
                            <a class="nav-link {{(Request::is('insider/courses*') ? 'font-weight-bold' : '') }}"
                               href="{{url('/insider/courses')}}">Мои курсы</a></li>
                        <li class="nav-item"><a
                                    class="nav-link {{(Request::is('insider/market*') ? 'font-weight-bold' : '') }}"
                                    href="{{url('insider/market')}}">Магазин</a></li>
                        @else
                            <li class="nav-item"><a
                                        class="nav-link {{((Request::is('courses*') or Request::is('categories*')) ? 'font-weight-bold' : '') }}"
                                        href="{{url('courses')}}">Каталог курсов</a></li>
                            <li class="nav-item"><a class="nav-link {{(Request::is('games*') ? 'font-weight-bold' : '') }}"
                                                    href="{{url('login')}}">Войти</a></li>
                        @endif
            </ul>
            <hr>
            <div class="d-none d-lg-block w-100">

                <span class="text-small text-muted">Ресурсы</span>

                <ul class="nav nav-small flex-column mt-2">

                    <li class="nav-item"><a class="nav-link" target="_blank"
                                            href="https://storage.geekclass.ru">Storage</a></li>
                    <li class="nav-item"><a class="nav-link" target="_blank" href="https://paste.geekclass.ru">Paste</a>
                    </li>

                </ul>
            </div>
        </div>
        @if (\Auth::check())
            <div class="d-none d-lg-block">
                <div class="dropup">
                    <button class="btn p-0 border-0 bg-transparent" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">

                        <img alt="Image" src="{{ \Auth::User()->imageUrl() }}" class="avatar border-white" width="67" height="67"/>
                    </button>

                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="{{url('insider/profile')}}"><i class="icon ion-person"></i>
                            Профиль</a>
                        <form method="POST" action="{{ route('logout') }}">
                            {{ csrf_field() }}
                            <button type="submit" class="dropdown-item"><i class="icon ion-reply"></i>Выход</button>
                        </form>
                    </div>

                </div>
            </div>
        @endif

    </nav>
    <main class="container-fluid pb-4">
        <div class="row justify-content-center">
            <div class="col-11">

                <div class="align-items-center justify-content-center pt-4">

                    @if(Session::has('alert-class') and Session::get('alert-destination')=='head')
                        <div class="alert {{ Session::get('alert-class') }} alert-dismissible" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
                                        aria-hidden="true">&times;</span>
                            </button>
                            <strong>{{Session::get('alert-title')}}</strong> {{ Session::get('alert-text') }}
                        </div>
                    @endif


                    @yield('content')


                </div>
            </div>
        </div>


    </main>
</div>

<!-- Required vendor scripts (Do not remove) -->

<!-- Optional Vendor Scripts (Remove the plugin script here and comment initializer script out of index.js if site does not use that feature) -->


{!! \NoCaptcha::renderJs() !!}
@include('layouts.partials.common-footer-scripts')
@include('layouts.partials.yandex-metrika')

</body>

</html>
