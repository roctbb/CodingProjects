<?php

return [
    'provider' => 'openai',
    // Older installations retain per-command overrides until the simplified form is saved.
    'shared_models' => env('AI_SHARED_MODELS', false),

    // A compatible API uses one base URL. Explicit old URLs remain supported.
    'providers' => [
        'openai' => [
            'key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL'),
            'endpoint' => env('OPENAI_RESPONSES_URL', 'https://api.openai.com/v1/responses'),
            'proxy' => env('OPENAI_PROXY'),
            'image_api' => env('OPENAI_IMAGE_API', 'responses'),
            'images_url' => env('OPENAI_IMAGES_URL'),
        ],
    ],

    'defaults' => [
        'text' => [
            'model' => env('GPT_MODEL', 'gpt-5-mini'),
            'timeout' => 90,
        ],
        'image' => [
            'model' => env('GPT_IMAGE_MODEL') ?: env('GPT_MODEL', 'gpt-5-mini'),
            'timeout' => 180,
        ],
        // Explicitly configured: ordinary checks keep the task's frozen model.
        'recheck' => [
            'model' => env('GPT_RECHECK_MODEL'),
            'timeout' => 180,
        ],
    ],

    // An empty command provider inherits AI_PROVIDER. Image models must match
    // the selected image_api: a Responses model or a dedicated Images model.
    'commands' => [
        'text_improvement' => [
            'type' => 'text',
            'provider' => env('AI_TEXT_IMPROVEMENT_PROVIDER'),
            'model' => env('GPT_TEXT_IMPROVEMENT_MODEL'),
            'timeout' => 60,
        ],
        'task_summary' => [
            'type' => 'text',
            'provider' => env('AI_TASK_SUMMARY_PROVIDER'),
            'model' => env('GPT_TASK_SUMMARY_MODEL'),
        ],
        'achievement_variants' => [
            'type' => 'text',
            'provider' => env('AI_ACHIEVEMENT_PROVIDER'),
            'model' => env('GPT_ACHIEVEMENT_MODEL', 'gpt-5.5'),
            'timeout' => 120,
        ],
        'achievement_image' => [
            'type' => 'image',
            'provider' => env('AI_ACHIEVEMENT_IMAGE_PROVIDER'),
            'model' => env('GPT_ACHIEVEMENT_IMAGE_MODEL', 'gpt-5.5'),
        ],
        'course_poster' => [
            'type' => 'image',
            'provider' => env('AI_COURSE_POSTER_PROVIDER'),
            'model' => env('GPT_POSTER_MODEL'),
        ],
        'room_character' => [
            'type' => 'image',
            'provider' => env('AI_ROOM_CHARACTER_PROVIDER'),
            'model' => env('GPT_ROOM_CHARACTER_MODEL'),
        ],
    ],
];
