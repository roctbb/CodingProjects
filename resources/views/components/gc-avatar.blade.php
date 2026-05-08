@props(['src', 'size' => '', 'alt' => ''])
<img src="{{ $src }}" alt="{{ $alt }}" {{ $attributes->merge(['class' => 'avatar ' . $size]) }}>
