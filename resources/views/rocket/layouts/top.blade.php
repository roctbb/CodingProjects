@extends('rocket.layouts.parts.page')

@section('page')
    <header class="header-global">
        @include('rocket.layouts.parts.top.header')
    </header>
    <main>

        @yield('content')


    </main>
    @include('rocket.layouts.parts.top.footer')

@endsection

