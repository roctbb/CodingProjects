@php
    $brandLight = \App\Support\Branding::palette();
    $brandDark = \App\Support\Branding::palette('dark');
    $brandButtons = \App\Support\Branding::buttons();
    $brandDarkButtons = \App\Support\Branding::buttons('dark');
    $brandHasCustomPalette = \App\Support\Branding::hasCustomPalette();
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

    .btn-primary {
        --bs-btn-bg: var(--gc-button-primary);
        --bs-btn-border-color: var(--gc-button-primary);
        --bs-btn-color: var(--gc-button-primary-text);
        --bs-btn-hover-bg: var(--gc-button-primary-hover);
        --bs-btn-hover-border-color: var(--gc-button-primary);
        --bs-btn-hover-color: var(--gc-button-primary-text);
        --bs-btn-active-bg: var(--gc-button-primary-hover);
        --bs-btn-active-border-color: var(--gc-button-primary-hover);
        --bs-btn-active-color: var(--gc-button-primary-text);
    }

    .btn-secondary {
        --bs-btn-bg: var(--gc-button-secondary);
        --bs-btn-border-color: var(--gc-button-secondary);
        --bs-btn-color: var(--gc-button-secondary-text);
        --bs-btn-hover-bg: var(--gc-button-secondary-hover);
        --bs-btn-hover-border-color: var(--gc-button-secondary);
        --bs-btn-hover-color: var(--gc-button-secondary-text);
        --bs-btn-active-bg: var(--gc-button-secondary-hover);
        --bs-btn-active-border-color: var(--gc-button-secondary-hover);
        --bs-btn-active-color: var(--gc-button-secondary-text);
    }

    .btn-info {
        --bs-btn-bg: var(--gc-button-info);
        --bs-btn-border-color: var(--gc-button-info);
        --bs-btn-color: var(--gc-button-info-text);
        --bs-btn-hover-bg: var(--gc-button-info-hover);
        --bs-btn-hover-border-color: var(--gc-button-info);
        --bs-btn-hover-color: var(--gc-button-info-text);
        --bs-btn-active-bg: var(--gc-button-info-hover);
        --bs-btn-active-border-color: var(--gc-button-info-hover);
        --bs-btn-active-color: var(--gc-button-info-text);
        color: var(--gc-button-info-text) !important;
    }

    .btn-success {
        --bs-btn-bg: var(--gc-button-success);
        --bs-btn-border-color: var(--gc-button-success);
        --bs-btn-color: var(--gc-button-success-text);
        --bs-btn-hover-bg: var(--gc-button-success-hover);
        --bs-btn-hover-border-color: var(--gc-button-success);
        --bs-btn-hover-color: var(--gc-button-success-text);
        --bs-btn-active-bg: var(--gc-button-success-hover);
        --bs-btn-active-border-color: var(--gc-button-success-hover);
        --bs-btn-active-color: var(--gc-button-success-text);
        color: var(--gc-button-success-text) !important;
    }

    .btn-warning {
        --bs-btn-bg: var(--gc-button-warning);
        --bs-btn-border-color: var(--gc-button-warning);
        --bs-btn-color: var(--gc-button-warning-text);
        --bs-btn-hover-bg: var(--gc-button-warning-hover);
        --bs-btn-hover-border-color: var(--gc-button-warning);
        --bs-btn-hover-color: var(--gc-button-warning-text);
        --bs-btn-active-bg: var(--gc-button-warning-hover);
        --bs-btn-active-border-color: var(--gc-button-warning-hover);
        --bs-btn-active-color: var(--gc-button-warning-text);
    }

    .btn-danger {
        --bs-btn-bg: var(--gc-button-danger);
        --bs-btn-border-color: var(--gc-button-danger);
        --bs-btn-color: var(--gc-button-danger-text);
        --bs-btn-hover-bg: var(--gc-button-danger-hover);
        --bs-btn-hover-border-color: var(--gc-button-danger);
        --bs-btn-hover-color: var(--gc-button-danger-text);
        --bs-btn-active-bg: var(--gc-button-danger-hover);
        --bs-btn-active-border-color: var(--gc-button-danger-hover);
        --bs-btn-active-color: var(--gc-button-danger-text);
    }

    .btn-outline-primary {
        --bs-btn-color: var(--gc-primary);
        --bs-btn-border-color: var(--gc-primary);
        --bs-btn-hover-bg: var(--gc-primary);
        --bs-btn-hover-border-color: var(--gc-primary);
        --bs-btn-active-bg: var(--gc-primary-hover);
        --bs-btn-active-border-color: var(--gc-primary-hover);
    }

    .btn-outline-info {
        --bs-btn-color: var(--gc-info);
        --bs-btn-border-color: var(--gc-info);
        --bs-btn-hover-bg: var(--gc-info);
        --bs-btn-hover-border-color: var(--gc-info);
        --bs-btn-active-bg: var(--gc-button-info-hover);
        --bs-btn-active-border-color: var(--gc-button-info-hover);
    }

    .btn-outline-success {
        --bs-btn-color: var(--gc-success);
        --bs-btn-border-color: var(--gc-success);
        --bs-btn-hover-bg: var(--gc-success);
        --bs-btn-hover-border-color: var(--gc-success);
        --bs-btn-active-bg: var(--gc-button-success-hover);
        --bs-btn-active-border-color: var(--gc-button-success-hover);
    }

    @if ($brandHasCustomPalette)
    /* Keep the interface neutral, but make navigation and interaction states brand-aware. */
    .gc-sidebar__link:hover {
        background: color-mix(in srgb, var(--gc-primary) 7%, #f8f9fa);
        color: var(--gc-primary-hover);
    }

    .gc-sidebar__link.active {
        background: var(--gc-primary-light);
        box-shadow: inset 3px 0 0 var(--gc-accent);
        color: var(--gc-primary-hover);
    }

    .gc-sidebar__link.active i {
        color: var(--gc-primary);
    }

    .courses-tabs,
    .market-tabs,
    .gc-segmented-tabs {
        --bs-nav-pills-link-active-bg: var(--gc-primary-light);
        --bs-nav-pills-link-active-color: var(--gc-primary-hover);
    }

    .courses-tabs .nav-link.active,
    .market-tabs .nav-link.active,
    .gc-segmented-tabs .nav-link.active {
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--gc-primary) 18%, transparent);
    }

    .nav-link.active .gc-tab-count {
        background: color-mix(in srgb, var(--gc-secondary) 24%, var(--gc-surface)) !important;
        border-color: color-mix(in srgb, var(--gc-secondary) 55%, var(--gc-border));
        color: var(--gc-on-surface);
    }

    .dropdown-item.active,
    .dropdown-item:active {
        background: var(--gc-primary-light);
        color: var(--gc-primary-hover);
    }

    .dropdown-item:focus,
    .dropdown-item:hover {
        background: color-mix(in srgb, var(--gc-primary) 7%, var(--gc-surface));
        color: var(--gc-primary-hover);
    }

    .nav-tabs .nav-link.active {
        border-color: var(--gc-border) var(--gc-border) var(--gc-primary);
        color: var(--gc-primary-hover);
    }

    .list-group-item.active {
        background: var(--gc-primary);
        border-color: var(--gc-primary);
        color: var(--gc-button-primary-text);
    }

    .accordion-button:not(.collapsed) {
        background: var(--gc-primary-light);
        box-shadow: inset 0 calc(-1 * var(--bs-accordion-border-width)) 0 color-mix(in srgb, var(--gc-primary) 24%, var(--gc-border));
        color: var(--gc-primary-hover);
    }

    .gc-icon-tile,
    .home-review-icon,
    .home-activity__head-icon,
    .pulse-feed__head-icon {
        background: color-mix(in srgb, var(--gc-primary) 9%, var(--gc-surface));
        border-color: color-mix(in srgb, var(--gc-primary) 22%, var(--gc-border));
        color: var(--gc-primary);
    }

    .home-card-arrow {
        background: color-mix(in srgb, var(--gc-primary) 5%, var(--gc-surface));
        border-color: color-mix(in srgb, var(--gc-primary) 20%, var(--gc-border));
        color: var(--gc-primary);
    }

    a.gc-card:hover {
        border-color: var(--gc-border);
        box-shadow: 0 8px 24px color-mix(in srgb, var(--gc-primary) 8%, transparent);
    }

    a.gc-card:focus-visible {
        border-color: color-mix(in srgb, var(--gc-primary) 28%, var(--gc-border));
        box-shadow: 0 8px 24px color-mix(in srgb, var(--gc-primary) 8%, transparent);
    }

    .home-review-notice {
        box-shadow: inset 3px 0 0 var(--gc-accent);
    }

    .gc-sidebar__user-btn:hover,
    .gc-topbar__toggle:hover {
        background: color-mix(in srgb, var(--gc-primary) 8%, var(--gc-surface));
        color: var(--gc-primary-hover);
    }

    .page-link {
        color: var(--gc-primary);
    }

    .page-link:hover {
        background: var(--gc-primary-light);
        border-color: var(--gc-border);
        color: var(--gc-primary-hover);
    }

    .page-link:focus {
        background: var(--gc-primary-light);
        border-color: color-mix(in srgb, var(--gc-primary) 24%, var(--gc-border));
        color: var(--gc-primary-hover);
    }

    .active > .page-link,
    .page-link.active {
        background: var(--gc-primary);
        border-color: var(--gc-primary);
        color: var(--gc-button-primary-text);
    }

    .form-control:focus,
    .form-select:focus,
    .form-check-input:focus {
        border-color: color-mix(in srgb, var(--gc-primary) 55%, var(--gc-border));
        box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--gc-primary) 14%, transparent);
    }

    .form-check-input:checked {
        background-color: var(--gc-primary);
        border-color: var(--gc-primary);
    }

    .form-range::-webkit-slider-thumb {
        background: var(--gc-primary);
    }

    .form-range::-moz-range-thumb {
        background: var(--gc-primary);
    }

    .bg-primary,
    .text-bg-primary {
        background-color: var(--gc-primary) !important;
    }

    .text-primary {
        color: var(--gc-primary) !important;
    }

    .text-info,
    .link-info {
        color: var(--gc-info) !important;
    }

    .text-success,
    .link-success {
        color: var(--gc-success) !important;
    }

    .text-warning,
    .link-warning {
        color: var(--gc-warning) !important;
    }

    .text-danger,
    .link-danger {
        color: var(--gc-danger) !important;
    }

    .bg-secondary,
    .text-bg-secondary {
        background-color: var(--gc-secondary) !important;
    }

    .text-bg-secondary {
        color: var(--gc-button-secondary-text) !important;
    }

    .bg-success:not(.progress-bar),
    .text-bg-success {
        background-color: var(--gc-success) !important;
    }

    .bg-info:not(.progress-bar),
    .text-bg-info {
        background-color: var(--gc-info) !important;
    }

    .text-bg-info {
        color: var(--gc-button-info-text) !important;
    }

    .text-bg-success {
        color: var(--gc-button-success-text) !important;
    }

    .bg-warning:not(.progress-bar),
    .text-bg-warning {
        background-color: var(--gc-warning) !important;
    }

    .text-bg-warning {
        color: var(--gc-button-warning-text) !important;
    }

    .bg-danger:not(.progress-bar),
    .text-bg-danger {
        background-color: var(--gc-danger) !important;
    }

    .text-bg-danger {
        color: var(--gc-button-danger-text) !important;
    }

    .border-primary {
        border-color: var(--gc-primary) !important;
    }

    .border-secondary {
        border-color: var(--gc-secondary) !important;
    }

    .border-success {
        border-color: var(--gc-success) !important;
    }

    .border-info {
        border-color: var(--gc-info) !important;
    }

    .border-warning {
        border-color: var(--gc-warning) !important;
    }

    .border-danger {
        border-color: var(--gc-danger) !important;
    }

    .alert-info {
        --bs-alert-bg: var(--bs-info-bg-subtle);
        --bs-alert-border-color: var(--bs-info-border-subtle);
        --bs-alert-color: var(--bs-info-text-emphasis);
    }

    .alert-success {
        --bs-alert-bg: color-mix(in srgb, var(--gc-success) 10%, var(--gc-surface));
        --bs-alert-border-color: color-mix(in srgb, var(--gc-success) 26%, var(--gc-border));
        --bs-alert-color: color-mix(in srgb, var(--gc-success) 72%, var(--gc-on-surface));
    }

    .alert-warning {
        --bs-alert-bg: color-mix(in srgb, var(--gc-warning) 10%, var(--gc-surface));
        --bs-alert-border-color: color-mix(in srgb, var(--gc-warning) 26%, var(--gc-border));
        --bs-alert-color: color-mix(in srgb, var(--gc-warning) 72%, var(--gc-on-surface));
    }

    .alert-danger {
        --bs-alert-bg: color-mix(in srgb, var(--gc-danger) 10%, var(--gc-surface));
        --bs-alert-border-color: color-mix(in srgb, var(--gc-danger) 26%, var(--gc-border));
        --bs-alert-color: color-mix(in srgb, var(--gc-danger) 72%, var(--gc-on-surface));
    }
    @endif
</style>
