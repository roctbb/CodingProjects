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

<header class="d-flex flex-column flex-md-row align-items-center p-3 px-md-4 mb-3 bg-white border-bottom shadow-sm">
    <img class="mr-3" src="{{ url('images/icons/icons8-idea-64.png') }}" height="40" alt="{{ config('app.name', 'Laravel') }}">
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
        <ul class="navbar-nav user-menu-nav">
            <li class="nav-item dropdown">
                <button class="btn btn-link p-2 text-dark dropdown-toggle" id="dropdown01" data-toggle="dropdown"
                   aria-haspopup="true" aria-expanded="false">
                    {{ Auth::user()->name }}</button>

                <div class="dropdown-menu" aria-labelledby="dropdown01">
                    <a class="dropdown-item" href="{{url('insider/profile')}}"><i class="icon ion-person"></i>
                        Профиль</a>
                    <form method="POST" action="{{ route('logout') }}">
                        {{ csrf_field() }}
                        <button type="submit" class="dropdown-item"><i class="icon ion-reply"></i>Выход</button>
                    </form>

                </div>
            </li>
        </ul>
    @else
        <a class="btn btn-outline-primary" href="/login">Вход</a>
    @endif
</header>


<main class="mx-auto col-md-11 col-12 mt-4">
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
                <img class="mb-2 logo" src="{{url('/images/logo.png')}}" width="150" alt="CodingProjects">
                <small class="d-block mb-3 text-muted">&copy; 2016-{{ \Carbon\Carbon::now()->year }}  </small>
            </div>
            <div class="col-6 col-md-9 col-lg-10 mt-3">
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
</main>

@include('layouts.partials.common-footer-scripts')
@include('layouts.partials.yandex-metrika')

</body>
</html>
