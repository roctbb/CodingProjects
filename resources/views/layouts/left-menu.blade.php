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
    <link href="{{url('assets/css/theme.css')}}" rel="stylesheet" type="text/css" media="all"/>
    <link href="{{ asset('build/css/legacy-theme.css') }}" rel="stylesheet" type="text/css" media="all"/>
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
    <script id="MathJax-script" async src="{{ asset('build/js/vendor/mathjax/tex-mml-chtml.js') }}"></script>

    @include('layouts.partials.pipeline-theme-scripts')

    <style>
        *[data-tooltip] {
            position: relative;
        }

        *[data-tooltip]::before {
            content: attr(data-tooltip);
            position: absolute;
            padding: 2px 10px;
            border-radius: 3px;
            color: #fff;
            background: #333741;
            display: none;
            top: 20px;
            left: -100%;
        }

        *[data-tooltip]:hover::before {
            display: block;
        }
    </style>
    @yield('head')
</head>

<body>

<div class="layout layout-nav-side">
    <div class="navbar navbar-expand-lg bg-dark navbar-dark sticky-top" style="width:19rem;">

        <a class="navbar-brand" href="{{ url('/') }}" style="line-height: 50px; font-size: 1.3rem;">
            <span><img style="height: 35px; margin-bottom: 0px;"
                       src="{{ url('images/icons/icons8-idea-64.png') }}">&nbsp;</span>
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
                        <a href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">

                            <img alt="Image" src="{{ \Auth::User()->imageUrl() }}" class="avatar menu"/>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="{{url('insider/profile')}}"><i class="icon ion-person"></i>
                                Профиль</a>
                            <a class="dropdown-item" href="{{ route('logout') }}"
                               onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i
                                        class="icon ion-reply"></i>Выход</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="collapse navbar-collapse flex-column" id="navbar-collapse">
            <ul class="navbar-nav d-lg-block">

            @if (\Auth::check())
                        <li class="nav-item">
                            <a class="nav-link {{(Request::is('insider/courses*') ? 'active-link' : '') }}"
                               href="{{url('/insider/courses')}}">Мои курсы</a></li>
                        <li class="nav-item"><a
                                    class="nav-link {{(Request::is('insider/market*') ? 'active-link' : '') }}"
                                    href="{{url('insider/market')}}">Магазин</a></li>
                        @else
                            <li class="nav-item"><a
                                        class="nav-link {{((Request::is('courses*') or Request::is('categories*')) ? 'active-link' : '') }}"
                                        href="{{url('courses')}}">Каталог курсов</a></li>
                            <li class="nav-item"><a class="nav-link {{(Request::is('games*') ? 'active-link' : '') }}"
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
                    <a href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">

                        <img alt="Image" src="{{ \Auth::User()->imageUrl() }}" class="avatar menu-avatar"/>
                    </a>

                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="{{url('insider/profile')}}"><i class="icon ion-person"></i>
                            Профиль</a>
                        <a class="dropdown-item" href="{{ route('logout') }}"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i
                                    class="icon ion-reply"></i>Выход</a>
                    </div>

                </div>
            </div>
        @endif

    </div>
    <div class="container-fluid" style="padding-bottom: 30px;">
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


    </div>
</div>

<!-- Required vendor scripts (Do not remove) -->

<!-- Optional Vendor Scripts (Remove the plugin script here and comment initializer script out of index.js if site does not use that feature) -->


{!! \NoCaptcha::renderJs() !!}
<form style="display: none;" id="logout-form" method="POST" action="{{ route('logout') }}">{{ csrf_field() }}</form>
@include('layouts.partials.common-footer-scripts')
<!-- Yandex.Metrika counter -->
<script type="text/javascript">
    (function (m, e, t, r, i, k, a) {
        m[i] = m[i] || function () {
            (m[i].a = m[i].a || []).push(arguments)
        };
        m[i].l = 1 * new Date();
        k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a)
    })
    (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

    ym(55625236, "init", {
        clickmap: true,
        trackLinks: true,
        accurateTrackBounce: true,
        webvisor: true
    });
</script>
<noscript>
    <div><img src="https://mc.yandex.ru/watch/55625236" style="position:absolute; left:-9999px;" alt=""/></div>
</noscript>
<!-- /Yandex.Metrika counter -->

</body>

</html>
