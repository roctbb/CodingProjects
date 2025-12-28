<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Primary Meta Tags -->
    <title>
      @yield('title')
       - {{ config('app.name', 'Laravel') }}
    </title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="title" content="@yield('title')">
    <meta name="author" content="Ростислав Бородин">
    <meta name="description" content="@yield('description')">
    <meta name="keywords" content="@yield('keywords')"/>

    <!-- Open Graph / Facebook -->

    <!--
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://demo.themesberg.com/rocket">
    <meta property="og:title" content="Rocket - Careers Page">
    <meta property="og:description"
          content="Rocket is a premium SaaS Bootstrap 4 Dashboard template featuring over 27 presentational and technical pages including pricing, support, team, careers and many more.">
    <meta property="og:image" content="https://themesberg.s3.us-east-2.amazonaws.com/public/products/rocket/rocket-preview.jpg">-->

    <!-- Favicon -->

    <!--
    <link rel="apple-touch-icon" sizes="120x120" href="../assets/img/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon/favicon-16x16.png">
    <link rel="manifest" href="../assets/img/favicon/site.webmanifest">
    <link rel="mask-icon" href="../assets/img/favicon/safari-pinned-tab.svg" color="#ffffff">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="theme-color" content="#ffffff">-->

    <!-- Fontawesome -->
    <link type="text/css" href="{{ url('rocket/vendor/@fortawesome/fontawesome-free/css/all.min.css') }}" rel="stylesheet">

    <!-- Prism -->
    <link type="text/css" href="{{ url('rocket/vendor/prismjs/themes/prism.css') }}" rel="stylesheet">

    <!-- VectorMap -->
    <link rel="stylesheet" href="{{ url('rocket/vendor/jqvmap/dist/jqvmap.min.css') }}">

    <!-- Rocket CSS -->
    <link type="text/css" href="{{ url('rocket/css/rocket.css') }}" rel="stylesheet">

    <link rel="stylesheet"
          href="//cdn.jsdelivr.net/gh/highlightjs/cdn-release@9.12.0/build/styles/atelier-lakeside-light.min.css">

    @yield('style')
    <!-- NOTICE: You can use the _analytics.html partial to include production code specific code & trackers -->

</head>

<body>
@yield('page')
<!-- Core -->
<script src="{{ url('rocket/vendor/jquery/dist/jquery.min.js') }}"></script>
<script src="{{ url('rocket/vendor/popper.js/dist/umd/popper.min.js') }}"></script>
<script src="{{ url('rocket/vendor/bootstrap/dist/js/bootstrap.min.js') }}"></script>
<script src="{{ url('rocket/vendor/headroom.js/dist/headroom.min.js') }}"></script>

<!-- Vendor JS -->
<script src="{{ url('rocket/vendor/countup.js/dist/countUp.min.js') }}"></script>
<script src="{{ url('rocket/vendor/jquery-countdown/dist/jquery.countdown.min.js') }}"></script>
<script src="{{ url('rocket/vendor/smooth-scroll/dist/smooth-scroll.polyfills.min.js') }}"></script>
<script src="{{ url('rocket/vendor/prismjs/prism.js') }}"></script>

<!-- Chartist -->
<script src="{{ url('rocket/vendor/chartist/dist/chartist.min.js') }}"></script>
<script src="{{ url('rocket/vendor/chartist-plugin-tooltips/dist/chartist-plugin-tooltip.min.js') }}"></script>

<!-- Vector Maps -->
<script src="{{ url('rocket/vendor/jqvmap/dist/jquery.vmap.min.js') }}"></script>
<script src="{{ url('rocket/vendor/jqvmap/dist/maps/jquery.vmap.world.js') }}"></script>

<!-- Rocket JS -->
<script src="{{ url('rocket/assets/js/rocket.js') }}"></script>
<script src="{{url('/js/nbv.js')}}"></script>
<script src="{{ url('/scripts/highlight.min.js') }}"></script>
<script>hljs.initHighlightingOnLoad();</script>

</body>

</html>
