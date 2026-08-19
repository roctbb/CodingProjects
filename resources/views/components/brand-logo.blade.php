@props([
    'alt' => '',
    'width' => null,
    'height' => null,
])

<img src="{{ \App\Support\Branding::logoUrl() }}"
     alt="{{ $alt }}"
     @if($width) width="{{ $width }}" @endif
     @if($height) height="{{ $height }}" @endif
     {{ $attributes }}>
