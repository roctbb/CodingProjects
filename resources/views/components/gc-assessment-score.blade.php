@props(['mark', 'maximum', 'state', 'label'])
@php
    $icon = match ($state) {
        'complete' => 'circle-check',
        'partial' => 'circle-half-stroke',
        'review' => 'hourglass-half',
        'blocked' => 'lock',
        default => 'minus',
    };
@endphp
<span {{ $attributes->class(['gc-assessment-score', 'gc-assessment-score--'.$state]) }} title="{{ $label }}">
    <i class="fas fa-{{ $icon }}" aria-hidden="true"></i>
    <span class="gc-assessment-score__value">{{ $mark }}</span>
    <span class="gc-assessment-score__maximum">/ {{ $maximum }}</span>
    <span class="visually-hidden"> · {{ $label }}</span>
</span>
