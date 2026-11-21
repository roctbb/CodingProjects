<?php

namespace App\Services\Tutor;

use App\ProgramStep;
use DOMDocument;
use DOMElement;

final class Reading
{
    public static function revision(ProgramStep $step): string
    {
        return substr(hash('sha256', (string) $step->theory), 0, 12);
    }

    /** Student-facing theory including authored examples. Keep published notebook inputs and text outputs, omit binary attachments. */
    public static function fullText(ProgramStep $step): string
    {
        $text = (string) $step->theory;
        if ($step->is_notebook) {
            $book = json_decode($text, true);
            $cells = $book['cells'] ?? array_merge([], ...array_column($book['worksheets'] ?? [], 'cells'));
            $sections = [];
            foreach ($cells as $cell) {
                $kind = $cell['cell_type'] ?? '';
                if (!in_array($kind, ['markdown', 'code', 'heading', 'raw'], true)) continue;
                $source = $cell['source'] ?? $cell['input'] ?? '';
                $body = is_array($source) ? implode('', $source) : (string) $source;
                $sections[] = $kind === 'code' ? "~~~\n".$body."\n~~~" : $body;
                if ($kind !== 'code') continue;
                foreach ($cell['outputs'] ?? [] as $output) {
                    if (!is_array($output)) continue;
                    $value = match ($output['output_type'] ?? '') {
                        'stream', 'pyout' => $output['text'] ?? '',
                        'execute_result', 'display_data' => $output['data']['text/plain'] ?? $output['text'] ?? '',
                        'pyerr', 'error' => implode("\n", $output['traceback'] ?? []),
                        default => '',
                    };
                    if (!$value && isset($output['data']['text/html'])) {
                        $html = $output['data']['text/html'];
                        $html = is_array($html) ? implode('', $html) : $html;
                        $html = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $html);
                        $value = html_entity_decode(strip_tags(preg_replace('~</(td|th|tr|p)>~i', ' ', $html)), ENT_QUOTES | ENT_HTML5);
                    }
                    if (is_array($value)) $value = implode('', $value);
                    if ($value !== '') $sections[] = "Вывод ячейки:\n".$value;
                }
            }
            $text = implode("\n\n", $sections);
        }
        $text = preg_replace('~data:image/[\w.+-]+;base64,[A-Za-z0-9+/=\r\n]+~i', '[изображение]', $text);
        if (filled($step->video_url)) $text .= "\n[На ступени есть учебное видео.]";
        return $text;
    }

    public static function summary(ProgramStep $step, array $parts): array
    {
        // Keep each sentence tied to its own paragraph for deep links.
        $excerpts = collect($parts)->unique('heading')->take(3)->map(function ($part) {
            $sentence = preg_split('/(?<=[.!?])\s+/u', $part['text'], 2)[0];
            $part['text'] = mb_strimwidth($sentence, 0, 200, '…');
            return $part;
        })->values();
        $text = $excerpts->isEmpty() ? mb_strimwidth(trim(strip_tags(self::fullText($step))), 0, 400, '…')
            : $excerpts->pluck('text')->implode(' ');
        return [$text, $excerpts->all()];
    }

    /** Indices refer to rendered Markdown blocks or original notebook cells. */
    public static function fragments(ProgramStep $step): array
    {
        $blocks = [];
        $heading = $step->name;
        $prefix = 'read-'.self::revision($step).'-';
        if ($step->is_notebook) {
            $book = json_decode((string) $step->theory, true);
            $cells = $book['cells'] ?? array_merge([], ...array_column($book['worksheets'] ?? [], 'cells'));
            foreach ($cells as $i => $cell) {
                if (($cell['cell_type'] ?? '') !== 'markdown') continue;
                $text = $cell['source'] ?? '';
                $text = is_array($text) ? implode('', $text) : (string) $text;
                $text = preg_replace('/^\s*(\x60{3,}|~{3,})[^\n]*\n.*?^\s*\1\s*$|!\[[^\]]*\]\([^)]*\)/msu', '', $text);
                if (preg_match('/^#{1,6}\s+(.+)$/mu', $text, $match)) $heading = trim($match[1]);
                self::add($blocks, $step, $prefix.$i, $heading, $text);
            }
            return $blocks;
        }

        $doc = new DOMDocument();
        $doc->loadHTML('<meta charset="utf-8"><div id="reading">'.parsedown_math((string) $step->theory).'</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $i = 0;
        foreach ($doc->getElementById('reading')->childNodes as $node) {
            if (!$node instanceof DOMElement) continue;
            $anchor = $prefix.$i++;
            $text = trim($node->textContent);
            if (preg_match('/^h[1-6]$/', $node->tagName)) {
                $heading = $text;
            } elseif (in_array($node->tagName, ['p', 'ul', 'ol', 'blockquote', 'table'], true)) {
                self::add($blocks, $step, $anchor, $heading, $text);
            }
        }
        return $blocks;
    }

    private static function add(array &$blocks, ProgramStep $step, string $anchor, string $heading, string $text): void
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') return;
        $blocks[] = ['id' => 's'.$step->id.'-'.$anchor, 'step' => (int) $step->id, 'anchor' => $anchor,
            'title' => $step->name, 'heading' => mb_substr($heading, 0, 160), 'text' => $text];
    }

    /** Match API names literally and Russian word forms by a shared prefix. */
    public static function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}_]{3,}/u', mb_strtolower($text), $matches);
        $stop = ['что','как','это','для','или','при','почему','такое','такой','меня','нужно','должен','задача','программа','напишите'];
        return array_values(array_unique(array_map(fn ($w) => mb_substr($w, 0, 5), array_diff($matches[0], $stop))));
    }

    public static function select(array $blocks, string $question, string $assignment, int $stepId, int $budget, ?string $focus = null): array
    {
        $terms = self::words($question);
        $background = self::words(mb_substr($assignment, 0, 1600));
        foreach ($blocks as &$block) {
            $title = self::words($block['heading'].' '.$block['title']);
            $body = self::words($block['text']);
            $block['score'] = count(array_intersect($terms, $title)) * 4
                + count(array_intersect($terms, $body)) * 3
                + count(array_intersect($background, $title))
                + min(4, count(array_intersect($background, $body)))
                + ($block['step'] === $stepId ? 2 : 0)
                + ($focus === $block['id'] ? 1000 : 0);
        }
        unset($block);
        usort($blocks, fn ($a, $b) => $b['score'] <=> $a['score']);
        $selected = [];
        foreach ($blocks as $block) {
            if (!$block['score'] || $budget < 80 || count($selected) >= 12) break;
            $cost = mb_strlen($block['title']) + mb_strlen($block['heading']);
            $length = min(950, $budget - $cost);
            if ($length < 40) continue;
            $block['text'] = mb_substr($block['text'], 0, $length);
            $budget -= $cost + mb_strlen($block['text']);
            unset($block['score']);
            $selected[] = $block;
        }
        return $selected;
    }
}

