<?php

return [
    'courses' => array_filter(array_map('trim', explode(',', (string) env('TUTOR_COURSES', ''))), 'strlen'),
    'queue' => env('TUTOR_QUEUE'),
    'enabled' => env('TUTOR_ENABLED', false),
    'guided_only' => env('TUTOR_GUIDED_ONLY', false),
    'question_coin_chance' => (int) env('TUTOR_QUESTION_COIN_CHANCE', 20),
    'model' => env('TUTOR_MODEL', 'gpt-6.1-terra'),
    'check_model' => env('TUTOR_CHECK_MODEL') ?: env('TUTOR_MODEL', 'gpt-6.1-terra'),
    'effort' => env('TUTOR_REASONING_EFFORT', 'medium'),
    'daily_tokens' => (int) (env('TUTOR_DAILY_TOKENS') === '' ? 160000 : env('TUTOR_DAILY_TOKENS', 160000)),
    'daily_questions' => (int) env('TUTOR_DAILY_QUESTIONS', 15),
    'platform_daily_tokens' => (int) env('TUTOR_PLATFORM_DAILY_TOKENS', 1000000),
    'platform_monthly_tokens' => (int) env('TUTOR_PLATFORM_MONTHLY_TOKENS', 10000000),
    'daily_coins' => (int) env('TUTOR_DAILY_COINS', 2),
    'platform_daily_coins' => (int) env('TUTOR_PLATFORM_DAILY_COINS', 40),
    'daily_rechecks' => (int) env('TUTOR_DAILY_RECHECKS', 1),
    'platform_daily_rechecks' => (int) env('TUTOR_PLATFORM_DAILY_RECHECKS', 12),
    'context_chars' => (int) env('TUTOR_CONTEXT_CHARS', 1800),
];

