@props(['label', 'value', 'icon', 'href' => null, 'external' => false])

<div {{ $attributes->class(['gc-contact']) }}>
    <dt><span class="gc-contact__icon" aria-hidden="true"><i class="{{ $icon }}"></i></span>{{ $label }}</dt>
    <dd>@if($href)<a class="gc-contact__link" href="{{ $href }}" @if($external) target="_blank" rel="noopener noreferrer" @endif>{{ $value }}</a>@else{{ $value }}@endif</dd>
</div>
