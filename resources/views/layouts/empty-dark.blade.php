<!doctype html>
<html lang="{{ app()->getLocale() }}">

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
    <link href="{{url('assets/css/theme.css')}}" rel="stylesheet" type="text/css" media="all"/>
    <link href="{{ asset('build/css/legacy-theme.css') }}" rel="stylesheet" type="text/css" media="all"/>
    @include('layouts.partials.pipeline-theme-scripts')


    @yield('head')

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
<form style="display: none;" id="logout-form" method="POST" action="{{ route('logout') }}">{{ csrf_field() }}</form>
@include('layouts.partials.common-footer-scripts')
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
