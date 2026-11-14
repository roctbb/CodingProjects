@php
    $brandLight = \App\Support\Branding::palette();
    $brandDark = \App\Support\Branding::palette('dark');
    $brandButtons = \App\Support\Branding::buttons();
    $brandDarkButtons = \App\Support\Branding::buttons('dark');
@endphp

<link rel="icon" href="{{ \App\Support\Branding::logoUrl() }}">
<style id="gc-brand-theme">
    :root {
        --gc-primary: {{ $brandLight['primary'] }};
        --gc-primary-hover: {{ $brandLight['primary_hover'] }};
        --gc-primary-light: {{ $brandLight['primary_light'] }};
        --gc-secondary: {{ $brandLight['secondary'] }};
        --gc-accent: {{ $brandLight['accent'] }};
        --gc-info: {{ $brandLight['info'] }};
        --gc-success: {{ $brandLight['success'] }};
        --gc-warning: {{ $brandLight['warning'] }};
        --gc-danger: {{ $brandLight['danger'] }};
        --gc-button-primary: {{ $brandButtons['primary'] }};
        --gc-button-primary-hover: {{ $brandButtons['primary_hover'] }};
        --gc-button-primary-text: {{ $brandButtons['primary_text'] }};
        --gc-button-secondary: {{ $brandButtons['secondary'] }};
        --gc-button-secondary-hover: {{ $brandButtons['secondary_hover'] }};
        --gc-button-secondary-text: {{ $brandButtons['secondary_text'] }};
        --gc-button-info: {{ $brandButtons['info'] }};
        --gc-button-info-hover: {{ $brandButtons['info_hover'] }};
        --gc-button-info-text: {{ $brandButtons['info_text'] }};
        --gc-button-success: {{ $brandButtons['success'] }};
        --gc-button-success-hover: {{ $brandButtons['success_hover'] }};
        --gc-button-success-text: {{ $brandButtons['success_text'] }};
        --gc-button-warning: {{ $brandButtons['warning'] }};
        --gc-button-warning-hover: {{ $brandButtons['warning_hover'] }};
        --gc-button-warning-text: {{ $brandButtons['warning_text'] }};
        --gc-button-danger: {{ $brandButtons['danger'] }};
        --gc-button-danger-hover: {{ $brandButtons['danger_hover'] }};
        --gc-button-danger-text: {{ $brandButtons['danger_text'] }};
        --bs-primary: {{ $brandLight['primary'] }};
        --bs-primary-rgb: {{ \App\Support\Branding::rgb($brandLight['primary']) }};
        --bs-secondary: {{ $brandLight['secondary'] }};
        --bs-secondary-rgb: {{ \App\Support\Branding::rgb($brandLight['secondary']) }};
        --bs-info: {{ $brandLight['info'] }};
        --bs-info-rgb: {{ \App\Support\Branding::rgb($brandLight['info']) }};
        --bs-success: {{ $brandLight['success'] }};
        --bs-success-rgb: {{ \App\Support\Branding::rgb($brandLight['success']) }};
        --bs-warning: {{ $brandLight['warning'] }};
        --bs-warning-rgb: {{ \App\Support\Branding::rgb($brandLight['warning']) }};
        --bs-danger: {{ $brandLight['danger'] }};
        --bs-danger-rgb: {{ \App\Support\Branding::rgb($brandLight['danger']) }};
        --bs-link-color: {{ $brandLight['primary'] }};
        --bs-link-color-rgb: {{ \App\Support\Branding::rgb($brandLight['primary']) }};
        --bs-link-hover-color: {{ $brandLight['primary_hover'] }};
        --bs-link-hover-color-rgb: {{ \App\Support\Branding::rgb($brandLight['primary_hover']) }};
        --bs-primary-text-emphasis: color-mix(in srgb, var(--gc-primary) 72%, var(--gc-on-surface));
        --bs-primary-bg-subtle: color-mix(in srgb, var(--gc-primary) 11%, var(--gc-surface));
        --bs-primary-border-subtle: color-mix(in srgb, var(--gc-primary) 26%, var(--gc-border));
        --bs-info-text-emphasis: color-mix(in srgb, var(--gc-info) 72%, var(--gc-on-surface));
        --bs-info-bg-subtle: color-mix(in srgb, var(--gc-info) 11%, var(--gc-surface));
        --bs-info-border-subtle: color-mix(in srgb, var(--gc-info) 26%, var(--gc-border));
        --bs-success-text-emphasis: color-mix(in srgb, var(--gc-success) 72%, var(--gc-on-surface));
        --bs-success-bg-subtle: color-mix(in srgb, var(--gc-success) 11%, var(--gc-surface));
        --bs-success-border-subtle: color-mix(in srgb, var(--gc-success) 26%, var(--gc-border));
    }

    [data-bs-theme="dark"] {
        --gc-primary: {{ $brandDark['primary'] }};
        --gc-primary-hover: {{ $brandDark['primary_hover'] }};
        --gc-primary-light: {{ $brandDark['primary_light'] }};
        --gc-secondary: {{ $brandDark['secondary'] }};
        --gc-accent: {{ $brandDark['accent'] }};
        --gc-info: {{ $brandDark['info'] }};
        --gc-success: {{ $brandDark['success'] }};
        --gc-warning: {{ $brandDark['warning'] }};
        --gc-danger: {{ $brandDark['danger'] }};
        --gc-button-primary: {{ $brandDarkButtons['primary'] }};
        --gc-button-primary-hover: {{ $brandDarkButtons['primary_hover'] }};
        --gc-button-primary-text: {{ $brandDarkButtons['primary_text'] }};
        --gc-button-secondary: {{ $brandDarkButtons['secondary'] }};
        --gc-button-secondary-hover: {{ $brandDarkButtons['secondary_hover'] }};
        --gc-button-secondary-text: {{ $brandDarkButtons['secondary_text'] }};
        --gc-button-info: {{ $brandDarkButtons['info'] }};
        --gc-button-info-hover: {{ $brandDarkButtons['info_hover'] }};
        --gc-button-info-text: {{ $brandDarkButtons['info_text'] }};
        --gc-button-success: {{ $brandDarkButtons['success'] }};
        --gc-button-success-hover: {{ $brandDarkButtons['success_hover'] }};
        --gc-button-success-text: {{ $brandDarkButtons['success_text'] }};
        --gc-button-warning: {{ $brandDarkButtons['warning'] }};
        --gc-button-warning-hover: {{ $brandDarkButtons['warning_hover'] }};
        --gc-button-warning-text: {{ $brandDarkButtons['warning_text'] }};
        --gc-button-danger: {{ $brandDarkButtons['danger'] }};
        --gc-button-danger-hover: {{ $brandDarkButtons['danger_hover'] }};
        --gc-button-danger-text: {{ $brandDarkButtons['danger_text'] }};
        --bs-primary: {{ $brandDark['primary'] }};
        --bs-primary-rgb: {{ \App\Support\Branding::rgb($brandDark['primary']) }};
        --bs-secondary: {{ $brandDark['secondary'] }};
        --bs-secondary-rgb: {{ \App\Support\Branding::rgb($brandDark['secondary']) }};
        --bs-info: {{ $brandDark['info'] }};
        --bs-info-rgb: {{ \App\Support\Branding::rgb($brandDark['info']) }};
        --bs-success: {{ $brandDark['success'] }};
        --bs-success-rgb: {{ \App\Support\Branding::rgb($brandDark['success']) }};
        --bs-warning: {{ $brandDark['warning'] }};
        --bs-warning-rgb: {{ \App\Support\Branding::rgb($brandDark['warning']) }};
        --bs-danger: {{ $brandDark['danger'] }};
        --bs-danger-rgb: {{ \App\Support\Branding::rgb($brandDark['danger']) }};
        --bs-link-color: {{ $brandDark['primary'] }};
        --bs-link-color-rgb: {{ \App\Support\Branding::rgb($brandDark['primary']) }};
        --bs-link-hover-color: {{ $brandDark['primary_hover'] }};
        --bs-link-hover-color-rgb: {{ \App\Support\Branding::rgb($brandDark['primary_hover']) }};
        --bs-primary-text-emphasis: color-mix(in srgb, var(--gc-primary) 72%, var(--gc-on-surface));
        --bs-primary-bg-subtle: color-mix(in srgb, var(--gc-primary) 16%, var(--gc-surface));
        --bs-primary-border-subtle: color-mix(in srgb, var(--gc-primary) 32%, var(--gc-border));
        --bs-info-text-emphasis: color-mix(in srgb, var(--gc-info) 72%, var(--gc-on-surface));
        --bs-info-bg-subtle: color-mix(in srgb, var(--gc-info) 16%, var(--gc-surface));
        --bs-info-border-subtle: color-mix(in srgb, var(--gc-info) 32%, var(--gc-border));
        --bs-success-text-emphasis: color-mix(in srgb, var(--gc-success) 72%, var(--gc-on-surface));
        --bs-success-bg-subtle: color-mix(in srgb, var(--gc-success) 16%, var(--gc-surface));
        --bs-success-border-subtle: color-mix(in srgb, var(--gc-success) 32%, var(--gc-border));
    }

</style>
