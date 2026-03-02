<!doctype html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="utf-8">
    <title>
        @yield('title')
         - {{ config('app.name', 'Laravel') }}
    </title>

    <link rel="stylesheet" href="/css/ionicons.min.css">

    <!-- FontAwesome -->
    <link rel="stylesheet" href="{{ url('rocket/vendor/@fortawesome/fontawesome-free/css/all.min.css') }}">

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ config('app.name', 'Laravel') }}">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <link rel="stylesheet"
          href="//cdn.jsdelivr.net/gh/highlightjs/cdn-release@9.12.0/build/styles/atelier-lakeside-light.min.css">

    <link rel="stylesheet" href="{{url('css/jquery-ui.min.css')}}">
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">


    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Gothic+A1" rel="stylesheet">
    <link href="{{url('assets/css/theme.css')}}" rel="stylesheet" type="text/css" media="all"/>
    <link rel="stylesheet" href="{{url('/css/app.css')}}">
    <script type="text/javascript" src="{{ url('assets/js/jquery.min.js') }}"></script>
    <script type="text/javascript" src="{{ url('assets/js/popper.min.js') }}"></script>
    <script type="text/javascript" src="{{ url('assets/js/bootstrap.js') }}"></script>
    <script src="https://cdn.plot.ly/plotly-1.58.5.min.js"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
    <script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>

    <!-- MathJax for mathematical formulas -->
    <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
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

    <!-- Autosize - resizes textarea inputs as user types -->
    <script type="text/javascript" src="{{ url('assets/js/autosize.min.js') }}"></script>
    <!-- Flatpickr (calendar/date/time picker UI) -->
    <script type="text/javascript" src="{{ url('assets/js/flatpickr.min.js') }}"></script>
    <!-- Prism - displays formatted code boxes -->
    <script type="text/javascript" src="{{ url('assets/js/prism.js') }}"></script>
    <!-- Shopify Draggable - drag, drop and sort items on page -->
    <script type="text/javascript" src="{{ url('assets/js/draggable.bundle.legacy.js') }}"></script>
    <script type="text/javascript" src="{{ url('assets/js/swap-animation.js') }}"></script>
    <!-- Dropzone - drag and drop files onto the page for uploading -->
    <script type="text/javascript" src="{{ url('assets/js/dropzone.min.js') }}"></script>
    <!-- List.js - filter list elements -->
    <script type="text/javascript" src="{{ url('assets/js/list.min.js') }}"></script>

    <!-- Required theme scripts (Do not remove) -->
    <script type="text/javascript" src="{{ url('assets/js/theme.js') }}"></script>

    <script src="{{url('/js/linkify.min.js')}}"></script>
    <script src="{{url('/js/linkify-jquery.min.js')}}"></script>

    <script src="{{ url('/scripts/highlight.min.js') }}"></script>
    <script>hljs.initHighlightingOnLoad();</script>
    <!-- Latest compiled and minified CSS -->

    <script src="{{url('js/jquery-ui.min.js')}}"></script>
    <script src="{{url('/js/bootstrap-select.min.js')}}"></script>
    <link rel="stylesheet" href="{{url('css/bootstrap-select.min.css')}}">

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

                            @if (\Auth::User()->image!=null)
                                <img alt="Image" src="{{url('/media/'.\Auth::User()->image)}}" class="avatar menu"/>
                            @else
                                <img alt="Image" src="{{ url('images/user.jpg') }}"
                                     class="avatar"/>
                            @endif
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="{{url('insider/profile')}}"><i class="icon ion-person"></i>
                                Профиль</a>
                            @if (\Auth::User()->role == 'admin')
                                <a class="dropdown-item" href="{{url('insider/scales')}}"><i
                                            class="icon ion-university"></i> Шкалы</a>
                                <a class="dropdown-item" href="{{url('insider/core/editor')}}"><i
                                            class="icon ion-edit"></i> Редактор карт</a>
                            @endif


                            <a class="dropdown-item" href="{{url('insider/core/'.\Auth::User()->id)}}"><i
                                        class="icon ion-map"></i> Карта</a>
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
                <!--@if (\Auth::User()->role != 'novice')
                    <li class="nav-item">
                        <a class="nav-link {{((Request::is('insider/articles*') or Request::is('articles*'))? 'active-link' : '') }}"
                               href="{{url('/insider/articles')}}">Статьи</a></li>
                    @else
                    <li class="nav-item">
                        <a class="nav-link {{(Request::is('articles*') ? 'active-link' : '') }}"
                               href="{{url('/articles')}}">Статьи</a></li>
                    @endif-->
                        <li class="nav-item">
                            <a class="nav-link {{(Request::is('insider/courses*') ? 'active-link' : '') }}"
                               href="{{url('/insider/courses')}}">Мои курсы</a></li>
                        <li class="nav-item"><a
                                    class="nav-link {{(Request::is('insider/market*') ? 'active-link' : '') }}"
                                    href="{{url('insider/market')}}">Магазин</a></li>
                    <!--<li class="nav-item">
                        <a class="nav-link {{((Request::is('courses*') or Request::is('categories*')) ? 'active-link' : '') }}"
                           href="{{url('courses')}}">Каталог курсов</a></li>
                    <li class="nav-item"><a class="nav-link {{(Request::is('insider/forum*') ? 'active-link' : '') }}"
                                            href="{{url('insider/forum')}}">Ответы</a></li>
                    <li class="nav-item"><a class="nav-link {{(Request::is('insider/ideas*') ? 'active-link' : '') }}"
                                            href="{{url('insider/ideas')}}">Идеи</a></li>
                    @if (\Auth::User()->role != 'novice')
                        <li class="nav-item"><a
                                    class="nav-link {{(Request::is('insider/community*') ? 'active-link' : '') }}"
                                    href="{{url('insider/community')}}">Сообщество</a></li>
                        <li class="nav-item"><a
                                    class="nav-link {{(Request::is('insider/projects*') ? 'active-link' : '') }}"
                                    href="{{url('insider/projects')}}">Проекты</a></li>
                        <li class="nav-item"><a
                                    class="nav-link {{(Request::is('insider/market*') ? 'active-link' : '') }}"
                                    href="{{url('insider/market')}}">Магазин</a></li>
                    @endif
                            <li class="nav-item"><a class="nav-link {{(Request::is('insider/events*') ? 'active-link' : '') }}"
                                            href="{{url('insider/events')}}">События</a></li>

                <li class="nav-item"><a class="nav-link {{(Request::is('insider/games*') ? 'active-link' : '') }}"
                                            href="{{url('insider/games')}}">Игры</a></li>-->
                        @else
                            <li class="nav-item"><a
                                        class="nav-link {{(Request::is('articles*') ? 'active-link' : '') }}"
                                        href="{{url('/articles')}}">Статьи</a></li>
                            <li class="nav-item"><a
                                        class="nav-link {{((Request::is('courses*') or Request::is('categories*')) ? 'active-link' : '') }}"
                                        href="{{url('courses')}}">Каталог курсов</a></li>
                        <!--<li class="nav-item"><a class="nav-link {{(Request::is('games*') ? 'active-link' : '') }}"
                                            href="{{url('games')}}">Игры</a></li>-->
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

                        @if (\Auth::User()->image!=null)
                            <img alt="Image" src="{{url('/media/'.\Auth::User()->image)}}" class="avatar menu-avatar"/>
                        @else
                            <img alt="Image" src="{{ url('images/user.jpg') }}"
                                 class="avatar menu-avatar"/>
                        @endif
                    </a>

                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="{{url('insider/profile')}}"><i class="icon ion-person"></i>
                            Профиль</a>
                        @if (\Auth::User()->role == 'admin')
                            <a class="dropdown-item" href="{{url('insider/scales')}}"><i
                                        class="icon ion-university"></i> Шкалы</a>
                            <a class="dropdown-item" href="{{url('insider/core/editor')}}"><i
                                        class="icon ion-edit"></i> Редактор карт</a>
                        @endif


                        <a class="dropdown-item" href="{{url('insider/core/'.\Auth::User()->id)}}"><i
                                    class="icon ion-map"></i> Карта</a>
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
<script>
    $(function () {
        $(".nav-link").click(function () {
            $(".nav-link.active").removeClass('active');
        });
    });
    $(function () {
        $(".date").datepicker({
            changeMonth: true,
            changeYear: true,
            yearRange: "1940:2025",
            dateFormat: 'yy-mm-dd'
        });

    });
</script>
<!-- Latest compiled and minified JavaScript -->

<form style="display: none;" id="logout-form" method="POST" action="{{ route('logout') }}">{{ csrf_field() }}</form>
<script>
    var url = document.location.toString();


    if (url.match('#')) {
        $('a[href="#' + url.split('#')[1] + '"]').tab('show');
        console.log(url.split('#')[1]);
    }

    // Change hash for page-reload
    $('.nav-tabs a').on('shown.bs.tab', function (e) {
        window.location.hash = e.target.hash;
    });

    $('div').linkify({
        target: "_blank"
    });
    $('div.markdown a').attr('target', 'blank');
    $(document).ready(function () {
        $('.selectpicker').selectpicker();
        // Re-render MathJax for dynamically loaded content
        if (window.MathJax) {
            MathJax.typesetPromise();
        }
    });

    // define a handler
    function doc_keyUp(e) {

        // this would test for whichever key is 40 and the ctrl key at the same time
        if (e.ctrlKey && e.keyCode == 81) {
            // call your function to do the thing
            location.href = "/aesthethics"
        }
    }

    // register the handler
    document.addEventListener('keyup', doc_keyUp, false);
    $(document).popover({
        selector: '[data-toggle=popover]',
        trigger: 'hover'
    });


</script>
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
