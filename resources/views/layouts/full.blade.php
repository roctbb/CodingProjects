<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
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

    {!! \NoCaptcha::renderJs() !!}
    @yield('head')

</head>
<body>

<div class="d-flex flex-column flex-md-row align-items-center p-3 px-md-4 mb-3 bg-white border-bottom shadow-sm">
    <img style="height: 40px;" src="{{ url('images/icons/icons8-idea-64.png') }}">&nbsp;&nbsp;&nbsp;
    <h5 class="my-0 mr-md-auto font-weight-normal"><a class="p-2 text-dark" href='{{url('/')}}'> {{ config('app.name', 'Laravel') }}</a></h5>
    <nav class="my-2 my-md-0 mr-md-3">

        @if (\Auth::check())
            <a class="p-2 text-dark {{(Request::is('insider/courses*') ? 'active' : '') }}"
               href="{{url('/insider/courses')}}">Мои курсы</a>
            <a class="p-2 text-dark {{(Request::is('courses*') or Request::is('categories*') ? 'active' : '') }}"
                    href="{{url('/insider/courses')}}">Каталог курсов</a>
        @else
            <a class="p-2 text-dark {{(Request::is('courses*') or Request::is('categories*') ? 'active' : '') }}"
               href="{{url('courses')}}">Каталог курсов</a>
        @endif
    </nav>
    @if (\Auth::check())
        <ul class="navbar-nav" style="width: 260px;">
            <li class="nav-item dropdown">
                <a class="p-2 text-dark dropdown-toggle" href="#" id="dropdown01" data-toggle="dropdown"
                   aria-haspopup="true" aria-expanded="false">
                    {{ Auth::user()->name }}</a>

                <div class="dropdown-menu" aria-labelledby="dropdown01">
                    <a class="dropdown-item" href="{{url('insider/profile')}}"><i class="icon ion-person"></i>
                        Профиль</a>
                    <a class="dropdown-item" href="{{ route('logout') }}"
                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i
                                class="icon ion-reply"></i>Выход</a>

                </div>
            </li>
        </ul>
    @else
        <a class="btn btn-outline-primary" href="/login">Вход</a>
    @endif
</div>


<div class="mx-auto col-md-11 col-12" style="margin-top: 30px">
    @if(Session::has('alert-class') and Session::get('alert-destination')=='head')
        <div class="alert {{ Session::get('alert-class') }} alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
                        aria-hidden="true">&times;</span>
            </button>
            <strong>{{Session::get('alert-title')}}</strong> {{ Session::get('alert-text') }}
        </div>
    @endif

    @yield('content')

    <footer class="pt-4 my-md-5 border-top">
        <div class="row">
            <div class="col-12 col-md-3 col-lg-2">
                <img class="mb-2 logo" src="{{url('/images/logo.png')}}" style="width: 150px;">
                <small class="d-block mb-3 text-muted">&copy; 2016-{{ \Carbon\Carbon::now()->year }}  </small>
            </div>
            <div class="col-6 col-md-9 col-lg-10" style="margin-top: 15px;">
                <h5>CodingProjects</h5>
                <ul class="list-unstyled text-small">

                    <li><a class="text-muted" target="_blank" href="https://gekkon-club.ru/courses">Курсы</a></li>
                    <li><a class="text-muted" target="_blank" href="https://github.com/geekon-school/">GitHub</a></li>
                    <li><a class="text-muted" target="_blank" href="https://storage.geekclass.ru">Storage</a></li>
                    <li><a class="text-muted" target="_blank" href="https://paste.geekclass.ru">Paste</a></li>

                </ul>
            </div>
        </div>
    </footer>
</div>

<form style="display: none;" id="logout-form" method="POST" action="{{ route('logout') }}">{{ csrf_field() }}</form>
@include('layouts.partials.common-footer-scripts')
<!-- Yandex.Metrika counter -->
<script type="text/javascript" >
    (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
    (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

    ym(55625236, "init", {
        clickmap:true,
        trackLinks:true,
        accurateTrackBounce:true,
        webvisor:true
    });
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/55625236" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->

</body>
</html>
