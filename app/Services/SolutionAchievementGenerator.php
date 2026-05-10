<?php

namespace App\Services;

use App\Achievement;
use App\CourseActivity;
use App\Solution;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SolutionAchievementGenerator
{
    protected ChatGptService $chatGpt;
    protected GeekPasteClient $geekPaste;

    public function __construct(ChatGptService $chatGpt, GeekPasteClient $geekPaste)
    {
        $this->chatGpt = $chatGpt;
        $this->geekPaste = $geekPaste;
    }

    public function generateForSolution(Solution $solution): ?Achievement
    {
        $solution->loadMissing('task.step.lesson', 'course', 'user');

        if (!$solution->isEligibleForAiAchievement()) {
            return null;
        }

        if (Achievement::where('user_id', $solution->user_id)->where('task_id', $solution->task_id)->exists()) {
            return null;
        }

        $context = $this->buildContext($solution);
        if ($context === null || trim($context['solution_text']) === '') {
            return null;
        }

        $result = $this->generateAchievementPayload($solution, $context);

        try {
            $achievement = Achievement::create([
                'user_id' => $solution->user_id,
                'course_id' => $solution->course_id,
                'task_id' => $solution->task_id,
                'solution_id' => $solution->id,
                'source' => Achievement::SOURCE_AI_TASK_SOLUTION,
                'status' => Achievement::STATUS_PUBLISHED,
                'title' => $result['title'],
                'description' => $result['description'],
                'icon_key' => $result['icon_key'],
                'payload' => [
                    'tone' => $result['tone'] ?? null,
                    'solution_source' => $context['source'],
                    'language' => $context['language'] ?? null,
                    'model' => config('services.chatgpt.model'),
                    'prompt_version' => 1,
                ],
                'published_at' => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            Log::info('AI achievement was not created, probably duplicate', [
                'solution_id' => $solution->id,
                'user_id' => $solution->user_id,
                'task_id' => $solution->task_id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        CourseActivity::recordAchievementEarned($achievement);

        return $achievement;
    }

    protected function buildContext(Solution $solution): ?array
    {
        if ($solution->task->is_code) {
            return $this->buildGeekPasteContext($solution);
        }

        return [
            'source' => 'local_solution',
            'solution_text' => trim(strip_tags((string) $solution->text)),
            'language' => null,
        ];
    }

    protected function buildGeekPasteContext(Solution $solution): ?array
    {
        $payload = $this->geekPaste->taskSolutions($solution->task_id, 500);
        if (!is_array($payload)) {
            throw new \RuntimeException('GeekPaste did not return task solutions');
        }

        $solutionText = trim((string) $solution->text);
        $items = collect($payload['solutions'] ?? [])
            ->filter(function ($item) use ($solution) {
                if (!is_array($item)) {
                    return false;
                }

                if (!empty($item['user_id']) && (int) $item['user_id'] !== (int) $solution->user_id) {
                    return false;
                }

                if (array_key_exists('course_id', $item) && $item['course_id'] !== null && $item['course_id'] !== '') {
                    return (int) $item['course_id'] === (int) $solution->course_id;
                }

                return true;
            })
            ->values();

        $matched = $items->first(function ($item) use ($solutionText) {
            if ($solutionText === '') {
                return false;
            }

            foreach (['solution', 'solution_text', 'url', 'link', 'paste_url'] as $key) {
                $candidate = trim((string) ($item[$key] ?? ''));
                if ($candidate !== '' && ($candidate === $solutionText || Str::contains($candidate, $solutionText) || Str::contains($solutionText, $candidate))) {
                    return true;
                }
            }

            return false;
        }) ?: $items->first();

        if (!$matched) {
            return null;
        }

        $code = trim((string) ($matched['raw_code'] ?? ''));
        $text = trim((string) ($matched['solution_text'] ?? ''));
        $solutionBody = $code !== '' ? $code : $text;

        return [
            'source' => 'geekpaste',
            'solution_text' => $solutionBody,
            'language' => $matched['lang'] ?? null,
        ];
    }

    protected function generateAchievementPayload(Solution $solution, array $context): array
    {
        $task = $solution->task;
        $course = $solution->course;
        $instruction = trim((string) $task->ai_achievement_instruction);
        $iconKeys = implode(', ', array_keys(Achievement::iconOptions()));

        $prompt = 'Ты придумываешь персональное достижение ученику за одно сильное решение задачи. '
            . 'Хвали только наблюдаемое качество решения, не личность ученика. Не сравнивай с другими учениками. '
            . 'Не раскрывай полный код и приватные детали. Не выдумывай факты, которых нет в решении. '
            . 'Верни только JSON без markdown: {"title":"...","description":"...","icon_key":"...","tone":"..."}. '
            . 'title до 55 символов, description 1-2 коротких предложения, icon_key только один из списка: ' . $iconKeys . '.';

        if ($task->is_code) {
            $prompt .= ' Это решение с кодом: можно отмечать идею, архитектуру, аккуратность, обработку случаев или читаемость, но не утверждай, что код идеален без оснований.';
        }

        if ($instruction !== '') {
            $prompt .= ' Фокус учителя: ' . $instruction;
        }

        $content = "Курс: {$course->name}\n"
            . "Задача: {$task->name}\n"
            . "Максимальный балл: {$task->max_mark}\n"
            . "Тип решения: " . ($task->is_code ? 'код' : 'текст') . "\n";

        if (!empty($context['language'])) {
            $content .= "Язык: {$context['language']}\n";
        }

        $content .= "Условие задачи:\n" . Str::limit(strip_tags((string) $task->text), 2500)
            . "\n\nРешение ученика:\n" . Str::limit($context['solution_text'], 5000);

        $response = $this->chatGpt->generate([
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => $content],
        ], ['timeout' => 90]);

        return $this->normalizePayload($response);
    }

    protected function normalizePayload(string $response): array
    {
        $json = trim($response);
        if (Str::startsWith($json, '```')) {
            $json = preg_replace('/^```(?:json)?\s*/', '', $json);
            $json = preg_replace('/\s*```$/', '', $json);
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException('AI achievement response is not valid JSON');
        }

        $iconKey = $data['icon_key'] ?? 'sparkles';
        if (!array_key_exists($iconKey, Achievement::iconOptions())) {
            $iconKey = 'sparkles';
        }

        $title = trim(strip_tags((string) ($data['title'] ?? '')));
        $description = trim(strip_tags((string) ($data['description'] ?? '')));

        return [
            'title' => Str::limit($title !== '' ? $title : 'Сильное решение', 55, ''),
            'description' => Str::limit($description !== '' ? $description : 'Решение набрало максимум и заслужило отдельную отметку.', 240, ''),
            'icon_key' => $iconKey,
            'tone' => Str::limit(trim(strip_tags((string) ($data['tone'] ?? 'technical'))), 40, ''),
        ];
    }
}
