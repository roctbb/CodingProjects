<?php

namespace App\Services\Tutor;

final class Dialogue
{
    public static function lastAnswer(array $history): ?array
    {
        foreach (array_reverse($history) as $turn) {
            if (($turn['role'] ?? null) === 'teacher') return null;
            if (($turn['role'] ?? null) === 'assistant') return $turn;
            if (isset($turn['answer'])) return ['text'=>$turn['answer']];
        }
        return null;
    }

    public static function focus(array $history, array $last): array
    {
        $step = $last['next_step'] ?? null;
        if (!$step) {
            $sentences = preg_split('/(?<=[.!?])\s+|\n+/u', $last['text']);
            foreach (array_reverse($sentences) as $sentence) {
                if (preg_match('/проверь|сравни|проследи|подумай|попробуй|\?$/iu', $sentence)) {
                    $step = mb_substr($sentence, 0, 300);
                    break;
                }
            }
        }
        $student = null;
        foreach (array_reverse($history) as $turn) {
            if (($turn['role'] ?? null) === 'student') {
                $student = mb_substr($turn['text'], -400);
                break;
            }
        }
        return array_filter(['proposed_step'=>$step, 'previous_student_words'=>$student, 'attempt'=>$last['attempt'] ?? null]);
    }

    public static function continues(string $question): bool
    {
        return mb_strlen($question) <= 240 && !str_contains($question, "\n")
            && preg_match('/^\s*(?:а\b|это\b|то\s*есть\b|тогда\b|значит\b|повтори\b|почему\b|исправь\b|где\b|разве\b|всё ещё\b|тот же\b|столько же\b|поясни\b|если\b|правильно\b)|(?:по.?прежнему|не изменил|не поменял|столько же|тот же балл|предыдущ\w* (?:вопрос|ответ|комментар))|^\s*[A-Za-z_]\w*\s*\?$/iu', $question);
    }

    public static function aboutGrade(string $question): bool
    {
        return (bool) preg_match('/балл|оценк|провер[кя]|перепровер|зач[её]т|критери|столько же|не засчит|почему у меня\s+\d/iu', $question);
    }

    public static function screenHelp(string $question, ?string $layout): ?string
    {
        if (preg_match('/^[a-z ,.;\[\]\x27?]+$/iD', $question)) {
            $keyboard = array_combine(str_split("qwertyuiop[]asdfghjkl;'zxcvbnm,."),
                mb_str_split('йцукенгшщзхъфывапролджэячсмитьбю'));
            $question = strtr(strtolower($question), $keyboard);
        }
        if (!preg_match('/(?:закрыть|свернуть|спрятать).{0,20}(?:чат|тебя)|(?:чат|тебя).{0,12}(?:закрыть|свернуть)|(?:где|какой).{0,18}крестик|вернуться к задаче/iu', $question)) return null;
        return match ($layout) {
            'dialog' => 'Нажми «К задаче» в верхней части чата. Переписка останется в задаче.',
            'panel' => 'Чат встроен в страницу задачи. Условие и кнопка «Решение» доступны на этой же странице.',
            default => 'Если чат открыт отдельным окном, нажми «К задаче» вверху. На широком экране чат расположен рядом с задачей.',
        };
    }

    public static function followupHint(array $data, array $last): string
    {
        $source = $data['review_basis']['source'] ?? $data['latest_submission'] ?? [];
        $texts = array_merge([$source['text'] ?? ''], array_column($source['files'] ?? [], 'text'));
        foreach (array_reverse($data['history'] ?? []) as $turn) {
            if (($turn['role'] ?? null) !== 'student') continue;
            array_unshift($texts, $turn['text']);
            break;
        }
        // Explain the declared parameter using only a signature actually discussed in this dialogue.
        preg_match_all('/\x60([A-Za-z_]\w*)\x60/u', $last['text'], $names);
        if (str_contains(mb_strtolower($last['text']), 'параметр') && preg_match('/\b([A-Za-z_]\w*)\s*[?!.]*$/u', $data['question'], $hypothesis)) {
            foreach ($names[1] as $name) {
                foreach ($texts as $text) {
                    if (!preg_match('/^\s*def\s+'.preg_quote($name, '/').'\s*\(\s*([A-Za-z_]\w*)\s*[,):]/m', $text, $signature)) continue;
                    if (!in_array($signature[1], $names[1], true)) continue;
                    $description = $hypothesis[1] === $signature[1] ? 'Имена совпадают.' : "Имя $hypothesis[1] пишется иначе.";
                    return "В объявлении $name первый параметр называется $signature[1]. $description Проверь, какое из этих имён используется внутри функции.";
                }
            }
        }
        if ($step = $data['dialogue_focus']['proposed_step'] ?? null) {
            return 'Мы проверяли этот шаг: «'.$step.'» Какой результат получается с твоим вариантом?';
        }
        return 'Вернёмся к предыдущему шагу. Какой результат ты ожидаешь от предложенного варианта и что получается при проверке на твоём примере?';
    }
}
