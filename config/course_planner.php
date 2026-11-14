<?php

return [
    'enabled' => env('COURSE_PLANNER_ENABLED', true),
    'timezone' => 'Europe/Moscow',
    'page_size' => 25,
    'max_page_size' => 50,
    'providers' => [
        'codingprojects' => ['name' => 'Программа CodingProjects', 'driver' => 'native'],
        'geekexam' => [
            'name' => 'GeekExam', 'driver' => 'http',
            'url' => env('PLANNER_GEEKEXAM_URL'), 'secret' => env('PLANNER_GEEKEXAM_SECRET'),
        ],
        'geekbattle' => [
            'name' => 'GeekBattle', 'driver' => 'http',
            'url' => env('PLANNER_GEEKBATTLE_URL'), 'secret' => env('PLANNER_GEEKBATTLE_SECRET'),
        ],
        'ai-code-battle' => [
            'name' => 'AI Code Battle', 'driver' => 'http',
            'url' => env('PLANNER_AI_CODE_BATTLE_URL'), 'secret' => env('PLANNER_AI_CODE_BATTLE_SECRET'),
        ],
    ],
];
