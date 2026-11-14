@props(['title', 'description' => null])
<header class="gc-auth-heading">
    <a href="{{ url('/') }}" class="gc-auth-brand"><x-brand-logo width="26" height="26" alt="" /><span>{{ config('app.name', 'Laravel') }}</span></a>
    <h1 class="gc-auth-title">{{ $title }}</h1>
    @if($description)<p class="gc-help-text gc-auth-description">{{ $description }}</p>@endif
</header>
