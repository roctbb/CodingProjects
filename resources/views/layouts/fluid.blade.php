<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-100">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @yield('title')
         - {{ config('app.name', 'Laravel') }}
    </title>

    @include('layouts.partials.npm-vendor-assets')
    <script src="{{ asset('build/js/nbv.js') }}"></script>
    <link href="{{ asset('build/css/notebook.css') }}" rel="stylesheet" type="text/css" media="all"/>
    <link href="{{ asset('build/css/legacy/theme.css') }}" rel="stylesheet" type="text/css" media="all"/>
    <link href="{{ asset('build/css/legacy-theme.css') }}" rel="stylesheet" type="text/css" media="all"/>
</head>
<body class="h-100 bg-white">

<main class="container-fluid min-vh-100">
    @if(Session::has('alert-class') and Session::get('alert-destination')=='head')
        <div class="alert {{ Session::get('alert-class') }} alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
                        aria-hidden="true">&times;</span>
            </button>
            <strong>{{Session::get('alert-title')}}</strong> {{ Session::get('alert-text') }}
        </div>
    @endif
    @yield('content')
</main>

@include('layouts.partials.common-footer-scripts')
@include('layouts.partials.yandex-metrika')


</body>
</html>
