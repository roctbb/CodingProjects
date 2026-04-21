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

    @yield('head')
</head>
<body class="geek-auth-body">
<main class="auth-shell">
    @include('layouts.partials.flash-alert')

    <div class="auth-shell-card">
        @yield('content')
    </div>
</main>

@php
    $cpuiTabsSelector = '.nav-tabs a, .nav-pills a';
    $enableBlankTargetLinks = false;
    $includeActionFormScript = false;
@endphp
@include('layouts.partials.common-footer-scripts')
</body>
</html>
