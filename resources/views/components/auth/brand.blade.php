@props([
    'title' => null,
    'inCard' => false,
    'headingLevel' => 1,
])

<div {{ $attributes->class(['auth-brand', 'auth-brand--in-card' => $inCard]) }}>
    <div class="auth-brand-inner">
        <md-filled-tonal-button class="auth-brand-link" href="{{ url('/') }}">
            <img class="auth-brand-icon" src="{{ url('images/icons/icons8-idea-64.png') }}" alt="logo">
            <span class="auth-brand-text">{{ config('app.name', 'Laravel') }}</span>
        </md-filled-tonal-button>

    @if(!is_null($title))
        @php $headingTag = 'h' . max(1, min(6, (int) $headingLevel)); @endphp
        <{{ $headingTag }} class="auth-title">{{ $title }}</{{ $headingTag }}>
    @endif

        {{ $slot }}
    </div>
</div>
