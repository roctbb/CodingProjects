<!doctype html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="utf-8">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="GeekClass">
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
    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>

    <script src="{{ url('/assets/js/easymde.min.js') }}"></script>

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

    <script src="//cdn.jsdelivr.net/gh/highlightjs/cdn-release@9.12.0/build/highlight.min.js"></script>
    <script>hljs.initHighlightingOnLoad();</script>
    <!-- Latest compiled and minified CSS -->

    <script src="{{url('js/jquery-ui.min.js')}}"></script>
    <script src="{{url('/js/bootstrap-select.min.js')}}"></script>
    <link rel="stylesheet" href="{{url('css/bootstrap-select.min.css')}}">


    @yield('head')
    <link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">
</head>

<body style="background-color: #2D9CCC;">


<div class="container" style="padding-bottom: 30px;">
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