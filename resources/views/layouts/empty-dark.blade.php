<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ config('app.name', 'Laravel') }}</title>

    @include('layouts.partials.npm-vendor-assets')
    <link href="{{ asset('build/css/legacy-theme.css') }}" rel="stylesheet">
    @yield('head')
</head>
<body class="auth-shell">

@hasSection('auth-background-image')
    <div class="auth-shell-background" data-background-image="@yield('auth-background-image')"></div>
@endif

<main class="container d-flex align-items-center justify-content-center min-vh-100 py-4">
    <div class="w-100">
        @if(Session::has('alert-class') && Session::get('alert-destination') == 'head')
            <div class="alert {{ Session::get('alert-class') }} alert-dismissible fade show" role="alert">
                <strong>{{ Session::get('alert-title') }}</strong> {{ Session::get('alert-text') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
            </div>
        @endif

        @yield('content')
    </div>
</main>

{!! \NoCaptcha::renderJs() !!}
@include('layouts.partials.common-footer-scripts')
@include('layouts.partials.yandex-metrika')

</body>
</html>
