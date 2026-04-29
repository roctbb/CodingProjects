<!doctype html>
<html lang="{{ app()->getLocale() }}" class="@hasSection('auth-background-image') auth-shell-html @endif">

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="utf-8">
    <title>
        @yield('title')
         - {{ config('app.name', 'Laravel') }}
    </title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ config('app.name', 'Laravel') }}">
    @include('layouts.partials.npm-vendor-assets')
    <link href="{{ asset('build/css/legacy/theme.css') }}" rel="stylesheet" type="text/css" media="all"/>
    <link href="{{ asset('build/css/legacy-theme.css') }}" rel="stylesheet" type="text/css" media="all"/>
    @include('layouts.partials.pipeline-theme-scripts')


    @yield('head')

</head>

<body class="auth-shell @hasSection('auth-background-image') auth-shell-with-background @endif">

@hasSection('auth-background-image')
    <div class="auth-shell-background" data-background-image="@yield('auth-background-image')"></div>
@endif


<main class="container pb-4">
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


</main>


<!-- Required vendor scripts (Do not remove) -->

<!-- Optional Vendor Scripts (Remove the plugin script here and comment initializer script out of index.js if site does not use that feature) -->


{!! \NoCaptcha::renderJs() !!}
@include('layouts.partials.common-footer-scripts')
@include('layouts.partials.yandex-metrika')

</body>

</html>
