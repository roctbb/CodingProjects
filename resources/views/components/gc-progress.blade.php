@props(['percent' => 0, 'height' => '6px', 'label' => ''])
<div {{ $attributes->class(['progress'])->merge(['data-progress-height' => $height]) }}>
    <div class="progress-bar bg-primary" data-progress-width="{{ $percent }}%" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">{{ $label }}</div>
</div>
