<?php

namespace App\Support;

class SafeMarkdown extends \Parsedown
{
    public function __construct()
    {
        $this->InlineTypes['$'] = ['Math'];
        array_unshift($this->InlineTypes['\\'], 'Math');
        $this->inlineMarkerList .= '$';

        $this->BlockTypes['$'][] = 'Math';
        $this->BlockTypes['\\'][] = 'Math';
    }

    /**
     * Consume TeX before Markdown can interpret underscores or backslashes.
     * A text element retains Parsedown's HTML escaping and safe mode.
     */
    protected function inlineMath($excerpt)
    {
        // Keep an escaped dollar together so it cannot open a math expression.
        if (str_starts_with($excerpt['text'], '\$')) {
            return ['extent' => 2, 'element' => ['text' => '\$']];
        }

        foreach (['$$' => '$$', '$' => '$', '\(' => '\)', '\[' => '\]'] as $open => $close) {
            if (!str_starts_with($excerpt['text'], $open)) {
                continue;
            }

            $offset = strlen($open);

            while (($end = strpos($excerpt['text'], $close, $offset)) !== false) {
                $backslashes = 0;
                for ($i = $end - 1; $i >= 0 && $excerpt['text'][$i] === '\\'; $i--) {
                    $backslashes++;
                }

                if ($backslashes % 2 === 0) {
                    $length = $end + strlen($close);

                    return [
                        'extent' => $length,
                        'element' => ['text' => substr($excerpt['text'], 0, $length)],
                    ];
                }

                $offset = $end + strlen($close);
            }

            return;
        }
    }

    /**
     * Standalone display delimiters also protect blank lines and list markers.
     */
    protected function blockMath($line)
    {
        $open = rtrim($line['text']);
        $delimiters = ['$$' => '$$', '\[' => '\]'];

        if (!isset($delimiters[$open])) {
            return;
        }

        return [
            'close' => $delimiters[$open],
            'element' => ['name' => 'p', 'text' => $open],
        ];
    }

    protected function blockMathContinue($line, $block)
    {
        if (isset($block['complete'])) {
            return;
        }

        $block['element']['text'] .= str_repeat("\n", 1 + ($block['interrupted'] ?? 0)).$line['body'];
        unset($block['interrupted']);

        if (rtrim($line['text']) === $block['close']) {
            $block['complete'] = true;
        }

        return $block;
    }

    /**
     * Allow an explicit line break without enabling arbitrary raw HTML.
     */
    protected function inlineMarkup($excerpt)
    {
        if (isset($excerpt['text'])
            && preg_match('/^<br\s*\/?>/i', $excerpt['text'], $matches)
        ) {
            return [
                'extent' => strlen($matches[0]),
                'element' => ['name' => 'br'],
            ];
        }

        return parent::inlineMarkup($excerpt);
    }
}
