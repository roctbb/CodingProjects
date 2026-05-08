<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ config('app.name', 'Laravel') }}</title>

    @include('layouts.partials.npm-vendor-assets')
    <link href="{{ asset('build/css/legacy-theme.css') }}" rel="stylesheet">
    <script type="module" src="{{ asset('build/js/nbv.js') }}"></script>
    <link href="{{ asset('build/css/notebook.css') }}" rel="stylesheet">
</head>
<body class="bg-white min-vh-100">

<main class="container-fluid">
    @if(Session::has('alert-class') && Session::get('alert-destination') == 'head')
        <div class="alert {{ Session::get('alert-class') }} alert-dismissible fade show" role="alert">
            <strong>{{ Session::get('alert-title') }}</strong> {{ Session::get('alert-text') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
        </div>
    @endif
    @yield('content')
</main>

@include('layouts.partials.common-footer-scripts')
@stack('editor')
<script type="module" src="{{ asset('build/js/notebook-render.js') }}"></script>
@include('layouts.partials.yandex-metrika')

</body>
</html>
