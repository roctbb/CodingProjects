<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    'geekpaste_url' => env('GEEKPASTE_URL', 'https://paste.geekclass.ru/'),
    'geekpaste_api_key' => env('GEEKPASTE_API_KEY'),

    'yandexgpt' => [
        'api_key' => env('YANDEX_GPT_API_KEY'),
        'folder_id' => env('YANDEX_GPT_FOLDER_ID'),
        'model' => env('YANDEX_GPT_MODEL', 'yandexgpt-lite'),
        'url' => env('YANDEX_GPT_URL', 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion'),
    ],

    'chatgpt' => [
        'key' => env('GPT_KEY'),
        'model' => env('GPT_MODEL', 'gpt-5-mini'),
        'image_model' => env('GPT_IMAGE_MODEL', env('GPT_MODEL', 'gpt-5-mini')),
        'poster_model' => env('GPT_POSTER_MODEL', env('GPT_IMAGE_MODEL', env('GPT_MODEL', 'gpt-5-mini'))),
        'achievement_model' => env('GPT_ACHIEVEMENT_MODEL', 'gpt-5.5'),
        'achievement_image_model' => env('GPT_ACHIEVEMENT_IMAGE_MODEL', env('GPT_IMAGE_MODEL', env('GPT_ACHIEVEMENT_MODEL', 'gpt-5.5'))),
        'gateway' => env('GPT_GATEWAY', 'https://gpt-gateway.ai.medsenger.ru:4443/v1/responses'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),
        'proxy' => env('TELEGRAM_PROXY'),
        'timeout' => env('TELEGRAM_TIMEOUT', 10),
    ],

];
