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

    {!! \NoCaptcha::renderJs() !!}
    @yield('head')
</head>
<body>
@include('layouts.partials.geek-topbar')

<main class="geek-main-shell container-xxl">
    @include('layouts.partials.flash-alert')

    @yield('content')

    @include('layouts.partials.app-footer')
</main>

@php
    $cpuiDatepickers = true;
    $cpuiTabsSelector = '.nav-tabs a, .nav-pills a';
    $cpuiInitPopovers = true;
    $includeActionFormScript = true;
@endphp
@include('layouts.partials.common-footer-scripts')
</body>
</html>
