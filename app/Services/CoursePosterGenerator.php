<?php

namespace App\Services;

use App\Course;
use App\Program;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CoursePosterGenerator
{
    private const WIDTH = 1024;
    private const HEIGHT = 1536;

    private ChatGptService $chatGpt;

    public function __construct(ChatGptService $chatGpt)
    {
        $this->chatGpt = $chatGpt;
    }

    public function generate(Program $program): string
    {
        $prompt = $this->buildPrompt($program);
        $image = $this->chatGpt->generateImage([
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $prompt],
        ], [
            'model' => $this->posterModel(),
            'size' => self::WIDTH . 'x' . self::HEIGHT,
            'quality' => 'high',
            'output_format' => 'png',
            'timeout' => 180,
        ]);

        $relativePath = $this->storeImage($program, $image);
        $oldPoster = $program->learning_avatar_poster;

        $program->learning_avatar_poster = $relativePath;
        $program->learning_avatar_poster_prompt = $prompt;
        $program->learning_avatar_poster_generated_at = now();
        $program->save();

        if ($oldPoster && $oldPoster !== $relativePath && Str::startsWith($oldPoster, 'course-posters/program-' . $program->id . '-')) {
            Storage::delete($oldPoster);
        }

        return $relativePath;
    }

    public function generateForCourse(Course $course): string
    {
        $program = $course->program ?: $course->program()->first();
        if (!$program) {
            throw new \RuntimeException('Course does not have a program for poster generation');
        }

        return $this->generate($program);
    }

    private function systemPrompt(): string
    {
        return 'Ты иллюстратор образовательной платформы по программированию. '
            . 'Сгенерируй именно растровое PNG-изображение, не SVG и не текстовое описание. '
            . 'Нужен вертикальный постер 1024x1536 для рамки на стене в детской/учебной комнате. '
            . 'Стиль: дружелюбная polished 2D game illustration, чистые формы, мягкий свет, технологичная тема, подходит школьникам. '
            . 'Не используй мелкий читаемый текст, логотипы, бренды, водяные знаки и реалистичные фотографии.';
    }

    private function buildPrompt(Program $program): string
    {
        $description = trim(strip_tags(html_entity_decode((string) $program->description)));

        return "Сгенерируй постер программы для комнаты ученика.\n"
            . 'Название программы: ' . Str::limit((string) $program->name, 160, '') . "\n"
            . 'Описание программы: ' . Str::limit($description, 1800, '') . "\n\n"
            . 'Сделай образ по смыслу программы: предметы программирования, схемы, интерфейсы, алгоритмические формы, персонаж-маскот или абстрактная сцена. '
            . 'Композиция должна хорошо читаться в узком настенном постере, без рамки, без прозрачных областей, без текста и без водяных знаков. '
            . 'Финальный результат должен быть вертикальным растровым PNG 1024x1536.';
    }

    private function storeImage(Program $program, array $image): string
    {
        $basename = 'program-' . $program->id . '-' . now()->format('YmdHis');
        $extension = $image['extension'] ?? 'png';
        $relativePath = 'course-posters/' . $basename . '.' . $extension;

        if (empty($image['bytes']) || !Storage::put($relativePath, $image['bytes'])) {
            throw new \RuntimeException('Could not store generated course poster image');
        }

        return $relativePath;
    }

    private function posterModel(): string
    {
        return config('services.chatgpt.poster_model')
            ?: config('services.chatgpt.image_model')
            ?: config('services.chatgpt.model');
    }
}
