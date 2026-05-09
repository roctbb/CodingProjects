<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ config('app.name', 'Laravel') }}</title>

	    @include('layouts.partials.npm-vendor-assets')
	    @yield('head')
</head>
<body>

<main class="container mt-4">
    @include('layouts.partials.session-alert')
    @yield('content')
</main>

@include('layouts.partials.common-footer-scripts')
@include('layouts.partials.yandex-metrika')

</body>
</html>
