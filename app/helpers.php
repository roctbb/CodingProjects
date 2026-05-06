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

if (!function_exists('parsedown')) {
    function parsedown($text)
    {
        return parsedown_math($text);
    }
}

if (!function_exists('video_embed_url')) {
    function video_embed_url($url)
    {
        if (!$url) {
            return $url;
        }

        $parts = parse_url($url);

        if (!$parts || empty($parts['host'])) {
            return $url;
        }

        $host = strtolower(preg_replace('/^www\./', '', $parts['host']));
        $path = $parts['path'] ?? '';
        $query = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $videoId = null;

        if ($host === 'youtu.be') {
            $videoId = trim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com'], true)) {
            if (!empty($query['v'])) {
                $videoId = $query['v'];
            } elseif (preg_match('#^/(embed|shorts|live)/([^/?]+)#', $path, $matches)) {
                $videoId = $matches[2];
            }
        }

        if (!$videoId) {
            return $url;
        }

        $embedQuery = [];
        $parseStartTime = function ($value) {
            if (is_numeric($value)) {
                return (int) $value;
            }

            preg_match_all('/(\d+)(h|m|s)/', (string) $value, $matches, PREG_SET_ORDER);
            $seconds = 0;

            foreach ($matches as $match) {
                $amount = (int) $match[1];

                if ($match[2] === 'h') {
                    $seconds += $amount * 3600;
                } elseif ($match[2] === 'm') {
                    $seconds += $amount * 60;
                } else {
                    $seconds += $amount;
                }
            }

            return $seconds;
        };
        $start = $query['start'] ?? $query['t'] ?? null;

        if ($start !== null) {
            $startSeconds = $parseStartTime($start);

            if ($startSeconds > 0) {
                $embedQuery['start'] = $startSeconds;
            }
        }

        return 'https://www.youtube.com/embed/' . rawurlencode($videoId) . ($embedQuery ? '?' . http_build_query($embedQuery) : '');
    }
}
