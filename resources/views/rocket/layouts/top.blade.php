@extends('rocket.layouts.parts.page')

@section('page')
    <header class="header-global">
        @include('rocket.layouts.parts.top.header')
    </header>
    <main>

        @yield('content')
        @include('rocket.layouts.parts.top.footer')

    </main>

@endsection

