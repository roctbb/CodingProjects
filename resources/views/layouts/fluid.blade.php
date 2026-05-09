<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ config('app.name', 'Laravel') }}</title>

	    @include('layouts.partials.npm-vendor-assets')
	    <script type="module" src="{{ asset('build/js/nbv.js') }}"></script>
    <link href="{{ asset('build/css/notebook.css') }}" rel="stylesheet">
</head>
<body class="bg-body min-vh-100">

<main class="container-fluid">
    @include('layouts.partials.session-alert')
    @yield('content')
</main>

@include('layouts.partials.common-footer-scripts')
@stack('editor')
<script type="module" src="{{ asset('build/js/notebook-render.js') }}"></script>
@include('layouts.partials.yandex-metrika')

</body>
</html>
