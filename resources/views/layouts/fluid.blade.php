<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @yield('title')
        - {{ config('app.name', 'Laravel') }}
    </title>

    @include('layouts.partials.npm-vendor-assets')
    <script>hljs.initHighlightingOnLoad();</script>
    <script src="{{ url('src-min-noconflict/ace.js') }}" type="text/javascript" charset="utf-8"></script>
    <script src="{{ url('/js/nbv.js') }}"></script>

    {!! \NoCaptcha::renderJs() !!}
    @yield('head')
</head>
<body class="fluid-layout-body geek-fluid-body">
<main class="container-fluid fluid-layout-container geek-fluid-shell">
    @include('layouts.partials.flash-alert')

    @yield('content')
</main>

@php
    $cpuiTabsSelector = '.nav-tabs a, .nav-pills a';
    $cpuiInitPopovers = false;
    $enableBlankTargetLinks = true;
    $includeActionFormScript = false;
@endphp
@include('layouts.partials.common-footer-scripts')
</body>
</html>
