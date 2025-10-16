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

    'yandexgpt' => [
        'api_key' => env('YANDEX_GPT_API_KEY'),
        'folder_id' => env('YANDEX_GPT_FOLDER_ID'),
        'model' => env('YANDEX_GPT_MODEL', 'yandexgpt-lite'),
        'url' => env('YANDEX_GPT_URL', 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion'),
    ],

];
