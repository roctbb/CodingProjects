<?php

namespace App\Services;

use App\Achievement;
use App\CoinTransaction;
use App\CourseActivity;
use App\Solution;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SolutionAchievementGenerator
{
    protected ChatGptService $chatGpt;
    protected GeekPasteClient $geekPaste;
    protected AchievementTrophyGenerator $trophyGenerator;

    public function __construct(ChatGptService $chatGpt, GeekPasteClient $geekPaste, AchievementTrophyGenerator $trophyGenerator)
    {
        $this->chatGpt = $chatGpt;
        $this->geekPaste = $geekPaste;
        $this->trophyGenerator = $trophyGenerator;
    }

    public function generateForSolution(Solution $solution, bool $ignoreEligibility = false): ?Achievement
    {
        $preview = $this->previewForSolution($solution, $ignoreEligibility);

        if (!$preview) {
            return null;
        }

        $variant = $preview['variants'][0] ?? $preview;

        return $this->createForSolution($solution, $variant, $ignoreEligibility);
    }

    public function previewForSolution(Solution $solution, bool $ignoreEligibility = false): ?array
    {
        $solution->loadMissing('task.step.lesson', 'course', 'user');

        if (Achievement::where('user_id', $solution->user_id)->where('task_id', $solution->task_id)->exists()) {
            return null;
        }

        if (!$ignoreEligibility && !$solution->isEligibleForAiAchievement()) {
            return null;
        }

        $context = $this->buildContext($solution);
        if ($context === null || trim($context['solution_text']) === '') {
            return null;
        }

        $result = $this->generateAchievementPayload($solution, $context);
        $result['solution_source'] = $context['source'];
        $result['language'] = $context['language'] ?? null;
        $result['model'] = $this->achievementModel();

        if (!empty($result['variants'])) {
            $result['variants'] = collect($result['variants'])->map(function ($variant) use ($context) {
                $variant['solution_source'] = $context['source'];
                $variant['language'] = $context['language'] ?? null;
                $variant['model'] = $this->achievementModel();

                return $variant;
            })->values()->all();
        }

        return $result;
    }

    public function createForSolution(Solution $solution, array $result, bool $manual = false): ?Achievement
    {
        $solution->loadMissing('task.step.lesson', 'course', 'user');

        if (Achievement::where('user_id', $solution->user_id)->where('task_id', $solution->task_id)->exists()) {
            return null;
        }

        $iconKey = $result['icon_key'] ?? 'sparkles';
        if (!array_key_exists($iconKey, Achievement::iconOptions())) {
            $iconKey = 'sparkles';
        }
        $visualKey = $result['visual_key'] ?? null;
        if (!is_string($visualKey) || $visualKey === '' || !array_key_exists($visualKey, Achievement::visualOptions())) {
            $visualKey = null;
        }
        $svgIcon = Achievement::sanitizeSvgIcon($result['svg_icon'] ?? null);

        $title = Str::limit(trim(strip_tags((string) ($result['title'] ?? 'Сильное решение'))), 120, '');
        $description = Str::limit(trim(strip_tags((string) ($result['description'] ?? 'Решение заслужило отдельную отметку.'))), 1000, '');
        $coinReward = $manual ? max(0, (int) ($result['coin_reward'] ?? 0)) : 0;

        try {
            $achievement = Achievement::create([
                'user_id' => $solution->user_id,
                'course_id' => $solution->course_id,
                'task_id' => $solution->task_id,
                'solution_id' => $solution->id,
                'source' => Achievement::SOURCE_AI_TASK_SOLUTION,
                'status' => Achievement::STATUS_PUBLISHED,
                'title' => $title,
                'description' => $description,
                'icon_key' => $iconKey,
                'payload' => [
                    'tone' => $result['tone'] ?? null,
                    'visual_key' => $visualKey,
                    'svg_icon' => $svgIcon,
                    'solution_source' => $result['solution_source'] ?? null,
                    'language' => $result['language'] ?? null,
                    'model' => $result['model'] ?? $this->achievementModel(),
                    'coin_reward' => $coinReward,
                    'prompt_version' => 2,
                    'manual' => $manual,
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

        try {
            $this->trophyGenerator->generateForAchievement($achievement);
        } catch (\Throwable $e) {
            Log::warning('AI achievement trophy generation failed', [
                'achievement_id' => $achievement->id,
                'solution_id' => $solution->id,
                'message' => $e->getMessage(),
            ]);
        }

        CourseActivity::recordAchievementEarned($achievement);
        $this->awardManualAchievementCoins($achievement, $coinReward);

        return $achievement;
    }

    protected function awardManualAchievementCoins(Achievement $achievement, int $coinReward): void
    {
        if ($coinReward <= 0 || !($achievement->payload['manual'] ?? false)) {
            return;
        }

        CoinTransaction::registerOnce(
            $achievement->user_id,
            $coinReward,
            'Achievement Task #' . $achievement->task_id . ' User #' . $achievement->user_id,
            '🏅 За достижение «' . $achievement->title . '» начислено ' . $coinReward . ' GC.',
            'success',
            'fas fa-award'
        );
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
        $solutionText = trim((string) $solution->text);
        $codeId = $this->extractGeekPasteCodeId($solutionText);

        if ($codeId !== null) {
            $payload = $this->geekPaste->solution($codeId);
            $matched = is_array($payload) ? $this->extractGeekPasteItem($payload) : null;

            if ($matched && $this->matchesGeekPasteSolution($matched, $solution)) {
                $context = $this->contextFromGeekPasteItem($matched);
                if ($context !== null) {
                    return $context;
                }
            }
        }

        $payload = $this->geekPaste->taskSolutions($solution->task_id, 500, null, null);
        if (!is_array($payload)) {
            throw new \RuntimeException('GeekPaste did not return task solutions');
        }

        $items = collect($payload['solutions'] ?? [])
            ->filter(function ($item) use ($solution) {
                if (!is_array($item)) {
                    return false;
                }

                return $this->matchesGeekPasteSolution($item, $solution);
            })
            ->values();

        $matched = $items->first(function ($item) use ($solutionText, $codeId) {
            if ($solutionText === '') {
                return false;
            }

            foreach (['id', 'code_id', 'solution', 'solution_text', 'url', 'link', 'paste_url'] as $key) {
                $candidate = trim((string) ($item[$key] ?? ''));
                if ($candidate !== '' && (
                    $candidate === $solutionText
                    || ($codeId !== null && $candidate === $codeId)
                    || Str::contains($candidate, $solutionText)
                    || Str::contains($solutionText, $candidate)
                )) {
                    return true;
                }
            }

            return false;
        }) ?: $items->first();

        if (!$matched) {
            Log::warning('AI achievement GeekPaste solution was not found', [
                'solution_id' => $solution->id,
                'task_id' => $solution->task_id,
                'course_id' => $solution->course_id,
                'user_id' => $solution->user_id,
                'geekpaste_code_id' => $codeId,
                'solutions_count' => $items->count(),
            ]);

            return null;
        }

        $context = $this->contextFromGeekPasteItem($matched);
        if ($context === null) {
            Log::warning('AI achievement GeekPaste solution is empty', [
                'solution_id' => $solution->id,
                'task_id' => $solution->task_id,
                'course_id' => $solution->course_id,
                'user_id' => $solution->user_id,
                'geekpaste_code_id' => $codeId,
                'geekpaste_solution_id' => $matched['id'] ?? null,
                'geekpaste_check_state' => $matched['check_state'] ?? null,
            ]);
        }

        return $context;
    }

    protected function extractGeekPasteItem(array $payload): ?array
    {
        foreach (['solution', 'data'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        foreach (['id', 'code_id', 'solution_text', 'raw_code', 'task_id'] as $key) {
            if (array_key_exists($key, $payload)) {
                return $payload;
            }
        }

        return null;
    }

    protected function matchesGeekPasteSolution(array $item, Solution $solution): bool
    {
        foreach (['task_id', 'course_id', 'user_id'] as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null && $item[$key] !== '') {
                if ((int) $item[$key] !== (int) $solution->{$key}) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function contextFromGeekPasteItem(array $item): ?array
    {
        $code = $this->stringifyGeekPasteValue($item['raw_code'] ?? '');
        $text = $this->stringifyGeekPasteValue($item['solution_text'] ?? '');
        $solutionBody = $code !== '' ? $code : $text;

        if ($solutionBody === '') {
            return null;
        }

        return [
            'source' => 'geekpaste',
            'solution_text' => $solutionBody,
            'language' => $item['lang'] ?? null,
        ];
    }

    protected function stringifyGeekPasteValue($value): string
    {
        if (is_array($value)) {
            return trim((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return trim((string) $value);
    }

    protected function extractGeekPasteCodeId(string $solutionText): ?string
    {
        if ($solutionText === '') {
            return null;
        }

        if (preg_match('/[?&]id=([A-Za-z0-9_-]+)/', $solutionText, $matches)) {
            return $matches[1];
        }

        if (preg_match('#paste\.geekclass\.ru/(?:raw/|view/)?([A-Za-z0-9_-]+)#', $solutionText, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^[A-Za-z0-9_-]{4,}$/', $solutionText)) {
            return $solutionText;
        }

        return null;
    }

    protected function generateAchievementPayload(Solution $solution, array $context): array
    {
        $task = $solution->task;
        $course = $solution->course;
        $instruction = trim((string) $task->ai_achievement_instruction);
        $iconKeys = implode(', ', array_keys(Achievement::iconOptions()));
        $visualKeys = implode(', ', array_keys(array_filter(Achievement::visualOptions(), fn ($label, $key) => $key !== '', ARRAY_FILTER_USE_BOTH)));

        $prompt = 'Ты придумываешь игровое достижение-бейдж ученику за одно сильное решение задачи. '
            . 'Это не ревью и не технический отчет. Нужен короткий запоминающийся бейдж как в игре. '
            . 'Сгенерируй ровно 3 разных варианта: забавный, образный и более технический. '
            . 'title: 1-3 слова, образное прозвище, роль или предмет; можно с легкой иронией. '
            . 'Не называй title сухо по технологии вроде "3D-память и циклы"; лучше "Квантовый сантехник", "Хранитель петли", "Архитектор труб". '
            . 'description: строго начни со слова "За ". Объясни, за какую конкретную идею/механику решения выдан бейдж, желательно связав ее с названием или смыслом задачи. '
            . 'Пиши понятно для человека, который не читал код: "За разработку языка программирования, где..." вместо "Отмечаю авторскую идею...". '
            . 'Запрещенные начала description: "Отмечаю", "Оригинальная", "Язык задаёт", "Решение демонстрирует", "Авторская идея". '
            . 'Хвали только наблюдаемое качество решения, не личность ученика. Не сравнивай с другими учениками. '
            . 'Не раскрывай полный код и приватные детали. Не выдумывай факты, которых нет в решении. '
            . 'Для каждого варианта нарисуй простую уникальную SVG-иконку в поле svg_icon: ровно один тег <svg viewBox="0 0 48 48">...</svg>, монохромно через currentColor, без style/class/id/text/image/foreignObject/filter/mask/defs/animate. '
            . 'Используй только svg, g, path, circle, rect, line, polyline, polygon, ellipse и безопасные геометрические атрибуты. Иконка должна визуально поддерживать название достижения. '
            . 'Верни только JSON без markdown: {"variants":[{"title":"...","description":"...","icon_key":"...","visual_key":"...","svg_icon":"<svg viewBox=\"0 0 48 48\">...</svg>","tone":"funny|metaphor|technical"}]}. '
            . 'title до 45 символов, description 1 короткое предложение до 220 символов, icon_key только один из списка: ' . $iconKeys . '. '
            . 'visual_key выбери один из списка, если подходит: ' . $visualKeys . '; если ни один не подходит, верни пустую строку.';

        if ($task->is_code) {
            $prompt .= ' Это решение с кодом: сначала найди главную метафору/механику программы, языка или алгоритма, и бейдж посвяти ей. Не утверждай, что код идеален без оснований.';
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

        $content .= "Условие задачи:\n" . Str::limit(strip_tags((string) $task->text), 10000)
            . "\n\nРешение ученика:\n" . Str::limit($context['solution_text'], 60000);

        $response = $this->chatGpt->generate([
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => $content],
        ], [
            'model' => $this->achievementModel(),
            'timeout' => 120,
        ]);

        return $this->normalizePayload($response);
    }

    protected function achievementModel(): string
    {
        return config('services.chatgpt.achievement_model') ?: config('services.chatgpt.model');
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

        $variants = [];
        if (!empty($data['variants']) && is_array($data['variants'])) {
            $variants = collect($data['variants'])
                ->filter(fn ($variant) => is_array($variant))
                ->take(3)
                ->map(fn ($variant) => $this->normalizeVariantPayload($variant))
                ->values()
                ->all();
        }

        if (empty($variants)) {
            $variants = [$this->normalizeVariantPayload($data)];
        }

        $first = $variants[0];
        $first['variants'] = $variants;

        return $first;
    }

    protected function normalizeVariantPayload(array $data): array
    {
        $iconKey = $data['icon_key'] ?? 'sparkles';
        if (!array_key_exists($iconKey, Achievement::iconOptions())) {
            $iconKey = 'sparkles';
        }
        $visualKey = $data['visual_key'] ?? null;
        if (!is_string($visualKey) || $visualKey === '' || !array_key_exists($visualKey, Achievement::visualOptions())) {
            $visualKey = null;
        }

        $title = trim(strip_tags((string) ($data['title'] ?? '')));
        $description = trim(strip_tags((string) ($data['description'] ?? '')));

        if ($description !== '' && !Str::startsWith(mb_strtolower($description), 'за ')) {
            $description = 'За ' . lcfirst($description);
        }

        return [
            'title' => Str::limit($title !== '' ? $title : 'Сильное решение', 55, ''),
            'description' => Str::limit($description !== '' ? $description : 'Решение набрало максимум и заслужило отдельную отметку.', 240, ''),
            'icon_key' => $iconKey,
            'visual_key' => $visualKey,
            'svg_icon' => Achievement::sanitizeSvgIcon($data['svg_icon'] ?? null),
            'tone' => Str::limit(trim(strip_tags((string) ($data['tone'] ?? 'technical'))), 40, ''),
        ];
    }
}
