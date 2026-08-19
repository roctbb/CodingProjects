<?php

return [
    /*
     * An absolute HTTP(S) URL or a path relative to the public directory.
     * An empty value keeps the current GeekClass logo.
     */
    'logo_url' => env('BRAND_LOGO_URL'),

    'colors' => [
        'primary' => env('BRAND_PRIMARY_COLOR'),
        'primary_hover' => env('BRAND_PRIMARY_HOVER_COLOR'),
        'primary_light' => env('BRAND_PRIMARY_LIGHT_COLOR'),
        'secondary' => env('BRAND_SECONDARY_COLOR'),
        'accent' => env('BRAND_ACCENT_COLOR'),
        'info' => env('BRAND_INFO_COLOR'),
        'success' => env('BRAND_SUCCESS_COLOR'),
        'warning' => env('BRAND_WARNING_COLOR'),
        'danger' => env('BRAND_DANGER_COLOR'),
    ],

    'dark_colors' => [
        'primary' => env('BRAND_DARK_PRIMARY_COLOR'),
        'primary_hover' => env('BRAND_DARK_PRIMARY_HOVER_COLOR'),
        'primary_light' => env('BRAND_DARK_PRIMARY_LIGHT_COLOR'),
        'secondary' => env('BRAND_DARK_SECONDARY_COLOR'),
        'accent' => env('BRAND_DARK_ACCENT_COLOR'),
        'info' => env('BRAND_DARK_INFO_COLOR'),
        'success' => env('BRAND_DARK_SUCCESS_COLOR'),
        'warning' => env('BRAND_DARK_WARNING_COLOR'),
        'danger' => env('BRAND_DARK_DANGER_COLOR'),
    ],
];
