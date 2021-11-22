<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>


    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <script src="{{ url('/assets/js/easymde.min.js') }}"></script>

    <!-- Styles -->
    <!-- Latest compiled and minified CSS -->
    <!-- Compiled and minified CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <link rel="stylesheet" href="{{url('/css/app.css')}}">


    <link rel="stylesheet" href="{{url('/css/app.css')}}">
    <script
            src="https://code.jquery.com/jquery-3.3.1.min.js"
            integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
            crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.11.0/umd/popper.min.js"
            integrity="sha384-b/U6ypiBEHpOf/4+1nzFpr53nxSS+GLCkfwBdFNTxtclqqenISfwAzpKaMNFNmj4"
            crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/js/bootstrap.min.js"
            integrity="sha384-h0AbiXch4ZDo7tp9hKZ4TsHbi047NrKGLO3SEJAg45jXxnGIfYzk4Si90RDIqNm1"
            crossorigin="anonymous"></script>
    <script src="{{url('/js/linkify.min.js')}}"></script>
    <script src="{{url('/js/linkify-jquery.min.js')}}"></script>
    <link rel="stylesheet"
          href="//cdn.jsdelivr.net/gh/highlightjs/cdn-release@9.12.0/build/styles/atelier-lakeside-light.min.css">
    <script src="//cdn.jsdelivr.net/gh/highlightjs/cdn-release@9.12.0/build/highlight.min.js"></script>
    <script>hljs.initHighlightingOnLoad();</script>
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="{{url('css/bootstrap-select.min.css')}}">
    <link rel="stylesheet" href="{{url('css/jquery-ui.min.css')}}">
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="{{url('js/jquery-ui.min.js')}}"></script>
    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">

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
    <script src="{{url('/js/bootstrap-select.min.js')}}"></script>
    @yield('head')

</head>
<body>

<div class="d-flex flex-column flex-md-row align-items-center p-3 px-md-4 mb-3 bg-white border-bottom shadow-sm">
    <img style="height: 40px;" src="{{ url('images/icons/icons8-idea-64.png') }}">&nbsp;&nbsp;&nbsp;
    <h5 class="my-0 mr-md-auto font-weight-normal"><a class="p-2 text-dark" href='{{url('/')}}'> GeekClass</a></h5>
    <nav class="my-2 my-md-0 mr-md-3">

        @if (\Auth::check())
            @if (\Auth::User()->role != 'novice')
                <a class="p-2 text-dark {{(Request::is('insider/articles*') ? 'active' : '') }}"
                   href="{{url('/insider/articles')}}">Статьи</a>
            @else
                <a class="p-2 text-dark {{(Request::is('articles*') ? 'active' : '') }}"
                   href="{{url('/articles')}}">Статьи</a>
            @endif
            <a class="p-2 text-dark {{(Request::is('insider/courses*') ? 'active' : '') }}"
               href="{{url('/insider/courses')}}">Мои курсы</a>
            <a class="p-2 text-dark {{(Request::is('courses*') or Request::is('categories*') ? 'active' : '') }}"
                   href="{{url('/insider/courses')}}">Каталог курсов</a>
            <a class="p-2 text-dark {{(Request::is('insider/forum*') ? 'active' : '') }}"
               href="{{url('insider/forum')}}">Ответы</a>
            <a class="p-2 text-dark {{(Request::is('insider/ideas*') ? 'active' : '') }}"
               href="{{url('insider/ideas')}}">Идеи</a>
            @if (\Auth::User()->role != 'novice')
                <a class="p-2 text-dark {{(Request::is('insider/community*') ? 'active' : '') }}"
                   href="{{url('insider/community')}}">Сообщество</a>
                <a class="p-2 text-dark {{(Request::is('insider/projects*') ? 'active' : '') }}"
                   href="{{url('insider/projects')}}">Проекты</a>
                <a class="p-2 text-dark {{(Request::is('insider/market*') ? 'active' : '') }}"
                   href="{{url('insider/market')}}">Магазин</a>
            @endif
            <a class="p-2 text-dark {{(Request::is('insider/events*') ? 'active' : '') }}"
            href="{{url('insider/events')}}">События</a>
        
           <a class="p-2 text-dark {{(Request::is('insider/games*') ? 'active' : '') }}"
               href="{{url('insider/games')}}">Игры</a>
        @else
            <a class="p-2 text-dark {{(Request::is('articles*') ? 'active' : '') }}"
               href="{{url('/articles')}}">Статьи</a>
            <a class="p-2 text-dark {{(Request::is('courses*') or Request::is('categories*') ? 'active' : '') }}"
               href="{{url('courses')}}">Каталог курсов</a>
            <!--<a class="p-2 text-dark {{(Request::is('games*') ? 'active' : '') }}" href="{{url('games')}}">Игры</a>-->
        @endif
    </nav>
    @if (\Auth::check())
        <ul class="navbar-nav" style="width: 260px;">
            <li class="nav-item dropdown">
                <a class="p-2 text-dark dropdown-toggle" href="#" id="dropdown01" data-toggle="dropdown"
                   aria-haspopup="true" aria-expanded="false">
                    {{ Auth::user()->name }}</a>

                <div class="dropdown-menu" aria-labelledby="dropdown01">
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
            </li>
        </ul>
    @else
        <a class="btn btn-outline-primary" href="/login">Вход</a>
    @endif
</div>


<div class="mx-auto col-md-11 col-12" style="margin-top: 30px">
    @if(Session::has('alert-class') and Session::get('alert-destination')=='head')
        <div class="alert {{ Session::get('alert-class') }} alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
                        aria-hidden="true">&times;</span>
            </button>
            <strong>{{Session::get('alert-title')}}</strong> {{ Session::get('alert-text') }}
        </div>
    @endif

    @yield('content')

    <footer class="pt-4 my-md-5 border-top">
        <div class="row">
            <div class="col-12 col-md-3 col-lg-2">
                <img class="mb-2 logo" src="{{url('/images/logo.png')}}" style="width: 150px;">
                <small class="d-block mb-3 text-muted">&copy; 2016-{{ \Carbon\Carbon::now()->year }}  </small>
            </div>
            <div class="col-6 col-md-9 col-lg-10" style="margin-top: 15px;">
                <h5>Школа программирования Геккон</h5>
                <ul class="list-unstyled text-small">

                    <li><a class="text-muted" target="_blank" href="https://gekkon-club.ru/programming">Сайт
                            Геккон-клуба</a></li>
                    <li><a class="text-muted" target="_blank" href="https://gekkon-club.ru/programming">Бот в ВК</a></li>
                    @if (\Auth::check())
                        <li><a class="text-muted" target="_blank" href="https://gekkon-club.ru/programming">Беседа в ВК</a></li>
                    @endif
                    <li><a class="text-muted" target="_blank" href="https://github.com/geekon-school/">GitHub</a></li>
                    <li><a class="text-muted" target="_blank" href="https://storage.geekclass.ru">Storage</a></li>
                    <li><a class="text-muted" target="_blank" href="https://paste.geekclass.ru">Paste</a></li>

                </ul>
            </div>
        </div>
    </footer>
</div>

<!-- Compiled and minified JavaScript -->


<!-- Scripts -->
<!--
    <script src="{{ asset('js/app.js') }}"></script>-->
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
<script type="text/javascript" >
    (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
    (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

    ym(55625236, "init", {
        clickmap:true,
        trackLinks:true,
        accurateTrackBounce:true,
        webvisor:true
    });
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/55625236" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->

</body>
</html>