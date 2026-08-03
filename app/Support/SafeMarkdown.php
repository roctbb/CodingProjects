<?php

namespace App\Support;

class SafeMarkdown extends \Parsedown
{
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
