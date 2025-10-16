<?php

if (!function_exists('parsedown_math')) {
    /**
     * Parse markdown text with mathematical formulas support
     * This function processes markdown and then unescapes mathematical formulas
     *
     * @param string $text
     * @return string
     */
    function parsedown_math($text)
    {
        $parser = new Parsedown();
        $parser->setMarkupEscaped(false);
        $parser->setSafeMode(false);

        $html = $parser->text($text);

        // Post-process to unescape mathematical formulas
        // This handles both inline ($...$) and display ($$...$$) formulas
        $html = preg_replace_callback('/\$\$([^$]+)\$\$/', function($matches) {
            return '$$' . html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '$$';
        }, $html);

        $html = preg_replace_callback('/\$([^$]+)\$/', function($matches) {
            return '$' . html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '$';
        }, $html);

        return $html;
    }
}
