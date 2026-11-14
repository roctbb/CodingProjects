<script>
    // Notebook cells invoke Prism explicitly; ordinary examples use Highlight.js.
    window.Prism = window.Prism || {};
    window.Prism.manual = true;
    (function () {
        try {
            var theme = localStorage.getItem('gc-theme');
            if (theme) document.documentElement.setAttribute('data-bs-theme', theme);
        } catch (e) {}
    })();
</script>
@php
    $appStylesPath = public_path('build/css/app.css');
    $appStylesVersion = file_exists($appStylesPath) ? filemtime($appStylesPath) : null;
@endphp
<link rel="stylesheet" href="{{ asset('build/css/app.css') }}@if($appStylesVersion)?v={{ $appStylesVersion }}@endif">
@include('layouts.partials.brand-theme')
