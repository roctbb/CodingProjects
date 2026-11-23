@props(['title' => null, 'embedded' => false, 'icon' => null, 'href' => null])

@php($tag = $href ? 'a' : 'section')

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['gc-card' => !$embedded, 'gc-panel--embedded' => $embedded, 'gc-card-link d-block' => $href]) }}>
    @if($title || isset($heading))
        <div class="gc-panel-heading">
            @isset($heading)
                {{ $heading }}
            @else
                <h2>@if($icon)<span class="gc-heading-accent" aria-hidden="true"><i class="{{ $icon }}"></i></span>@endif{{ $title }}</h2>
            @endisset
            @isset($actions) {{ $actions }} @endisset
        </div>
    @endif
    <div class="gc-panel-body">{{ $slot }}</div>
</{{ $tag }}>
