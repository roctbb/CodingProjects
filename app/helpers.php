<?php

if (!function_exists('parsedown_math')) {
    /**
     * Parse markdown text with mathematical formulas support
     * This function disables HTML escaping to allow MathJax to process mathematical formulas
     *
     * @param string $text
     * @return string
     */
    function parsedown_math($text)
    {
        $parser = new Parsedown();
        $parser->setMarkupEscaped(false);
        $parser->setSafeMode(false);
        
        return $parser->text($text);
    }
}