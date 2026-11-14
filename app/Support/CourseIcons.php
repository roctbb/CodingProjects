<?php

namespace App\Support;

use App\Course;

final class CourseIcons
{
    // Curated Lucide Static 1.72.0 assets. See resources/icons/courses/LICENSE.
    private const OPTIONS = [
        'graduation-cap' => 'Обучение',
        'file-code-2' => 'Код и файлы',
        'code' => 'Программирование',
        'terminal' => 'Консоль',
        'blocks' => 'Мир из блоков',
        'cpu' => 'Компьютеры',
        'globe' => 'Веб',
        'network' => 'Сети',
        'brain-circuit' => 'Нейросети',
        'workflow' => 'Алгоритмы',
        'database' => 'Данные',
        'shield' => 'Безопасность',
        'gamepad-2' => 'Игры',
        'trophy' => 'Соревнования',
        'bot' => 'Роботы',
        'rocket' => 'Проекты',
    ];

    private static array $svg = [];

    public static function options(): array
    {
        return self::OPTIONS;
    }

    public static function resolve(?string $key, string $name = ''): string
    {
        if (isset(self::OPTIONS[$key ?? ''])) {
            return $key;
        }

        $name = mb_strtolower($name);
        $patterns = [
            'blocks' => '/minecraft|майнкрафт/u',
            'brain-circuit' => '/нейро|neural|machine learning|\bml\b|\bnn\b/u',
            'network' => '/\brsa\b|сети|сетев|network|сокет/u',
            'shield' => '/безопас|крипто|шифр|security|crypt/u',
            'trophy' => '/хакатон|соревн|олимпиад|battle|hackathon/u',
            'globe' => '/веб|\bweb\b|html|css|javascript|frontend|backend/u',
            'database' => '/\bsql\b|баз.{0,8}данн|database/u',
            'bot' => '/робот|robot/u',
            'workflow' => '/алгоритм|algorithm/u',
            'cpu' => '/архитект|аппарат|\bcpu\b|компьютер/u',
            'gamepad-2' => '/игр|game/u',
            'file-code-2' => '/python|питон/u',
            'code' => '/c\+\+|программир|coding|\bcode\b/u',
        ];
        foreach ($patterns as $icon => $pattern) {
            if (preg_match($pattern, $name)) {
                return $icon;
            }
        }

        return 'graduation-cap';
    }

    public static function forCourse(Course $course): string
    {
        return self::resolve($course->icon_key, (string) $course->name);
    }

    public static function svg(string $key): string
    {
        // Only repository-owned SVGs can be rendered, never a path or SVG from a request.
        $key = isset(self::OPTIONS[$key]) ? $key : 'graduation-cap';

        return self::$svg[$key] ??= file_get_contents(resource_path('icons/courses/'.$key.'.svg'));
    }
}
