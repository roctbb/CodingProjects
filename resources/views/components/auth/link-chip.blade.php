@props([
    'href',
    'icon' => null,
    'label' => null,
])

@php
    $iconClass = null;
    if ($icon) {
        $preparedIcon = str_replace(['ion-', ' '], ['', ''], strtolower($icon));
        $legacyIcons = [
            'person-add' => 'fas fa-user-plus',
            'personadd' => 'fas fa-user-plus',
            'log-in' => 'fas fa-sign-in-alt',
            'log_in' => 'fas fa-sign-in-alt',
            'key' => 'fas fa-key',
        ];

        $iconClass = $legacyIcons[$preparedIcon] ?? 'fas fa-link';
    }
@endphp

<a class="auth-link-chip" href="{{ $href }}">
    @if($iconClass)
        <i class="{{ $iconClass }}" aria-hidden="true"></i>
    @endif
    <span>{{ $label ?? trim($slot) }}</span>
</a>
