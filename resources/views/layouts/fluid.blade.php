<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" style="min-height: 100%;">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <script src="{{ url('/assets/js/easymde.min.js') }}"></script>

    <!-- Styles -->
    <!-- Latest compiled and minified CSS -->
    <!-- Compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/css/bootstrap.min.css"
          integrity="sha384-/Y6pD6FV/Vv2HJnA6t+vslU6fwYXjCFtcEpHbNJ0lyAFsXTsjBbfaDjzALeQsN6M" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">


    <link rel="stylesheet" href="{{url('/css/app.css')}}">
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"
            integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN"
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
    <script src="{{url('src-min-noconflict/ace.js')}}" type="text/javascript" charset="utf-8"></script>
    <!-- Latest compiled and minified JavaScript -->
    <script src="{{url('/js/bootstrap-select.min.js')}}"></script>
    <link rel="stylesheet" href="{{url('css/bootstrap-select.min.css')}}">
    <script src="https://unpkg.com/axios@0.18.0/dist/axios.min.js"></script>

    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/prism/1.5.1/themes/prism.min.css'/>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/marked/0.3.6/marked.min.js'></script>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/prism/1.5.1/prism.min.js' data-manual></script>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/prism/1.5.1/components/prism-python.min.js'
            data-manual></script>
    <script src="{{url('/js/nbv.js')}}"></script>
    <link href="{{url('assets/css/theme.css')}}" rel="stylesheet" type="text/css" media="all"/>
    @auth
        @if(\Auth::user()->currentTheme())
            <link rel="stylesheet" href="/insider/themes/{{\Auth::user()->currentTheme()->id}}/css"/>
            <script src="/insider/themes/{{\Auth::user()->currentTheme()->id}}/js"></script>
        @endif
    @endauth
</head>
<body style="min-height: 100%; height: 100%; background-color: white;">

<div class="container-fluid" style="min-height: calc(100% - 56px);">
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
