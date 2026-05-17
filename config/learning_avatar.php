<?php

return [
    'room_system' => [
        'layouts_path' => env('LEARNING_AVATAR_ROOM_SYSTEM_LAYOUTS_PATH'),
    ],

    'weather' => [
        'enabled' => env('LEARNING_AVATAR_WEATHER_ENABLED', true),
        'latitude' => env('LEARNING_AVATAR_WEATHER_LATITUDE', 55.7558),
        'longitude' => env('LEARNING_AVATAR_WEATHER_LONGITUDE', 37.6173),
        'timezone' => env('LEARNING_AVATAR_WEATHER_TIMEZONE', 'Europe/Moscow'),
        'cache_minutes' => env('LEARNING_AVATAR_WEATHER_CACHE_MINUTES', 30),
    ],
];
