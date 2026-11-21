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

    'geekexam_alert_secret' => env('GEEKEXAM_ALERT_SECRET'),
    'geekexecutor_url' => env('GEEKEXECUTOR_URL', 'http://geekexecutor:8086'),
    'task_chat_socket' => env('TASK_CHAT_SOCKET_URL', '/task-chat/socket'),
    'geekexecutor_public_url' => env('GEEKEXECUTOR_PUBLIC_URL'),
    'geekexecutor_socket_url' => env('GEEKEXECUTOR_SOCKET_URL'),
    'geekexecutor_secret' => env('GEEKEXECUTOR_SECRET'),
    'code_executor_callback_url' => env('CODE_EXECUTOR_CALLBACK_URL'),

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),
        'proxy' => env('TELEGRAM_PROXY'),
        'timeout' => env('TELEGRAM_TIMEOUT', 10),
    ],

    'silaeder_oidc' => [
        'enabled' => env('CRM_OIDC_ENABLED', false),
        'issuer' => rtrim(env('CRM_OIDC_ISSUER', 'https://lk.silaeder.ru'), '/'),
        'client_id' => env('CRM_OIDC_CLIENT_ID'),
        'client_secret' => env('CRM_OIDC_CLIENT_SECRET'),
        'redirect_uri' => env(
            'CRM_OIDC_REDIRECT_URI',
            rtrim(env('APP_URL', 'http://localhost'), '/') . '/auth/silaeder/callback'
        ),
        'post_logout_redirect_uri' => env(
            'CRM_OIDC_POST_LOGOUT_REDIRECT_URI',
            rtrim(env('APP_URL', 'http://localhost'), '/') . '/auth/silaeder/logout/callback'
        ),
    ],

];
