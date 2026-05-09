@props(['percent' => 0, 'height' => '6px', 'label' => ''])
@php
    $color = $percent >= 80 ? 'success' : ($percent >= 40 ? 'info' : ($percent >= 1 ? 'warning' : 'secondary'));
@endphp
<div class="progress" data-progress-height="{{ $height }}">
    <div class="progress-bar bg-{{ $color }}" data-progress-width="{{ $percent }}%" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">{{ $label }}</div>
</div>
